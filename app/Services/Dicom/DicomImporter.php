<?php

namespace App\Services\Dicom;

use App\Enums\Quota;
use App\Enums\Role;
use App\Models\DicomInstance;
use App\Models\Series;
use App\Models\Study;
use App\Models\User;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\QuotaExceededException;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stores a DICOM file and files it under its study and series.
 */
class DicomImporter
{
    public const DISK = DicomStorage::DISK;

    public function __construct(
        private DicomParser $parser,
        private DicomStorage $storage,
        private StudyDeleter $deleter,
        private PackageLimits $limits,
    ) {}

    /**
     * @throws InvalidDicomFileException
     * @throws DuplicateDicomInstanceException
     * @throws StudyLimitReachedException
     * @throws QuotaExceededException
     */
    public function import(string $path, ?User $uploader = null): DicomInstance
    {
        $metadata = $this->parser->parse($path);

        $uploaderId = $uploader?->id;

        if ($uploader?->role === Role::Patient) {
            $this->ensurePatientCanUpload($uploader, $metadata->studyInstanceUid);
        }

        if (DicomInstance::where('uploaded_by', $uploaderId)->where('sop_instance_uid', $metadata->sopInstanceUid)->exists()) {
            throw new DuplicateDicomInstanceException("Instance {$metadata->sopInstanceUid} has already been uploaded.");
        }

        $existingStudy = Study::where('uploaded_by', $uploaderId)->where('study_instance_uid', $metadata->studyInstanceUid)->first(['id', 'study_date']);

        if ($uploader !== null) {
            $this->limits->ensureStorageFor($uploader, filesize($path));

            if ($existingStudy === null) {
                $this->limits->ensure($uploader, Quota::Uploads);
            }
        }

        $storedPath = $this->storage->pathFor(
            $uploaderId,
            $metadata->studyInstanceUid,
            $metadata->seriesInstanceUid,
            $metadata->sopInstanceUid,
            $existingStudy->study_date ?? $metadata->studyDate,
        );

        $this->storage->disk()->putFileAs(dirname($storedPath), new File($path), basename($storedPath));

        try {
            return DB::transaction(fn (): DicomInstance => $this->persist($metadata, $storedPath, filesize($path), $uploader));
        } catch (Throwable $exception) {
            if (! DicomInstance::where('file_path', $storedPath)->exists()) {
                $this->storage->delete([$storedPath]);
            }

            throw $exception;
        }
    }

    /**
     * Patients keep one study at a time: files of their current study are accepted, other studies only once it has
     * expired. Expired studies still waiting for the scheduled clean-up are deleted now, so they do not block the upload.
     *
     * @throws StudyLimitReachedException
     */
    private function ensurePatientCanUpload(User $patient, string $studyInstanceUid): void
    {
        $patient->uploadedStudies()->expired()->get()->each(fn (Study $study) => $this->deleter->delete($study));

        $current = $patient->uploadedStudies()->first();

        if ($current !== null && $current->study_instance_uid !== $studyInstanceUid) {
            throw new StudyLimitReachedException(
                "This file belongs to another study. You can upload a new study after your current one expires on {$current->expires_at->toFormattedDateString()}.",
            );
        }
    }

    private function persist(DicomMetadata $metadata, string $storedPath, int $fileSize, ?User $uploader): DicomInstance
    {
        $study = Study::firstOrCreate(
            ['uploaded_by' => $uploader?->id, 'study_instance_uid' => $metadata->studyInstanceUid],
            [
                'patient_name' => $metadata->patientName,
                'patient_id' => $metadata->patientId,
                'patient_birth_date' => $metadata->patientBirthDate,
                'patient_sex' => $metadata->patientSex,
                'study_date' => $metadata->studyDate,
                'study_description' => $metadata->studyDescription,
                'accession_number' => $metadata->accessionNumber,
                'expires_at' => $uploader?->role === Role::Patient ? now()->addDays(Study::PATIENT_STUDY_LIFETIME_DAYS) : null,
            ],
        );

        if ($uploader !== null && $study->wasRecentlyCreated) {
            $this->limits->spend($uploader, Quota::Uploads);
        }

        $series = Series::firstOrCreate(
            ['study_id' => $study->id, 'series_instance_uid' => $metadata->seriesInstanceUid],
            [
                'modality' => $metadata->modality,
                'series_number' => $metadata->seriesNumber,
                'series_description' => $metadata->seriesDescription,
            ],
        );

        return $series->instances()->create([
            'sop_instance_uid' => $metadata->sopInstanceUid,
            'sop_class_uid' => $metadata->sopClassUid,
            'transfer_syntax_uid' => $metadata->transferSyntaxUid,
            'instance_number' => $metadata->instanceNumber,
            'rows' => $metadata->rows,
            'columns' => $metadata->columns,
            'number_of_frames' => $metadata->numberOfFrames,
            'file_path' => $storedPath,
            'file_size' => $fileSize,
            'uploaded_by' => $uploader?->id,
        ]);
    }
}
