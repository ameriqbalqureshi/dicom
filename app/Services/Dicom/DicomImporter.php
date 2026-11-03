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
use App\Services\StudyHandover;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stores a DICOM file and files it under its study and series.
 */
class DicomImporter
{
    public const DISK = DicomStorage::DISK;

    /**
     * The largest file that can be imported, in bytes.
     */
    public const MAX_FILE_SIZE = 100 * 1024 * 1024;

    /**
     * How much of an uploaded file is read for its header at first. Headers are usually a few kilobytes.
     */
    public const HEADER_BYTES = 1024 * 1024;

    public function __construct(
        private DicomParser $parser,
        private DicomStorage $storage,
        private StudyHandover $handover,
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
        return $this->importFile(
            $this->parser->parse($path),
            filesize($path),
            $uploader,
            fn (string $storedPath) => $this->storage->disk()->putFileAs(dirname($storedPath), new File($path), basename($storedPath)),
        );
    }

    /**
     * Import a file a browser uploaded straight to the disk at an incoming path. Only the start of the file is read, for
     * its header, and it is moved into place on the disk, so a file on S3 never passes through the application. The
     * upload is removed whatever the outcome.
     *
     * @throws InvalidDicomFileException
     * @throws DuplicateDicomInstanceException
     * @throws StudyLimitReachedException
     * @throws QuotaExceededException
     */
    public function importUploaded(string $uploadPath, ?User $uploader = null): DicomInstance
    {
        $disk = $this->storage->disk();

        try {
            if (! $disk->exists($uploadPath)) {
                throw new InvalidDicomFileException('The upload did not finish. Please try this file again.');
            }

            $size = $disk->size($uploadPath);

            if ($size > self::MAX_FILE_SIZE) {
                throw new InvalidDicomFileException('The file is larger than 100 MB.');
            }

            return $this->importFile(
                $this->parseUploadedHeader($uploadPath, $size),
                $size,
                $uploader,
                fn (string $storedPath) => $disk->move($uploadPath, $storedPath),
            );
        } finally {
            if ($disk->exists($uploadPath)) {
                $disk->delete($uploadPath);
            }
        }
    }

    /**
     * Parse the header from the first part of an uploaded file, reading the whole file only if the header is longer.
     *
     * @throws InvalidDicomFileException
     */
    private function parseUploadedHeader(string $path, int $size): DicomMetadata
    {
        try {
            return $this->parseStart($path, self::HEADER_BYTES);
        } catch (InvalidDicomFileException $exception) {
            if ($size <= self::HEADER_BYTES) {
                throw $exception;
            }

            return $this->parseStart($path, null);
        }
    }

    /**
     * Copy the first $length bytes of a file on the disk (all of it when null) to a local temporary file and parse it.
     *
     * @throws InvalidDicomFileException
     */
    private function parseStart(string $path, ?int $length): DicomMetadata
    {
        $source = $this->storage->disk()->readStream($path) ?? throw new InvalidDicomFileException('The uploaded file could not be read.');
        $copy = tmpfile();

        try {
            stream_copy_to_stream($source, $copy, $length);

            return $this->parser->parse(stream_get_meta_data($copy)['uri'], isPartial: $length !== null);
        } finally {
            fclose($source);
            fclose($copy);
        }
    }

    /**
     * File a parsed instance under its study and series, storing its file with $store(path).
     *
     * @param  callable(string): mixed  $store
     *
     * @throws DuplicateDicomInstanceException
     * @throws StudyLimitReachedException
     * @throws QuotaExceededException
     */
    private function importFile(DicomMetadata $metadata, int $fileSize, ?User $uploader, callable $store): DicomInstance
    {
        $uploaderId = $uploader?->id;

        if ($uploader?->role === Role::Patient) {
            $this->ensurePatientCanUpload($uploader, $metadata->studyInstanceUid);
        }

        if (DicomInstance::where('uploaded_by', $uploaderId)->where('sop_instance_uid', $metadata->sopInstanceUid)->exists()) {
            throw new DuplicateDicomInstanceException("Instance {$metadata->sopInstanceUid} has already been uploaded.");
        }

        $existingStudy = Study::where('uploaded_by', $uploaderId)->where('study_instance_uid', $metadata->studyInstanceUid)->first(['id', 'study_date']);

        if ($uploader !== null) {
            $this->limits->ensureStorageFor($uploader, $fileSize);

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

        $store($storedPath);

        try {
            return DB::transaction(fn (): DicomInstance => $this->persist($metadata, $storedPath, $fileSize, $uploader));
        } catch (Throwable $exception) {
            if (! DicomInstance::where('file_path', $storedPath)->exists()) {
                $this->storage->delete([$storedPath]);
            }

            throw $exception;
        }
    }

    /**
     * Patients keep one study at a time: files of their current study are accepted, other studies only once it has
     * expired. Expired studies still waiting for the scheduled clean-up are handed over (or deleted) now, so they do not
     * block the upload.
     *
     * @throws StudyLimitReachedException
     */
    private function ensurePatientCanUpload(User $patient, string $studyInstanceUid): void
    {
        $patient->uploadedStudies()->expired()->get()->each(fn (Study $study) => $this->handover->release($study));

        $current = $patient->uploadedStudies()->first();

        if ($current !== null && $current->study_instance_uid !== $studyInstanceUid) {
            throw new StudyLimitReachedException(
                "This file belongs to another study. You can upload a new study after your current one expires on {$current->expires_at->toFormattedDateString()}.",
            );
        }
    }

    private function persist(DicomMetadata $metadata, string $storedPath, int $fileSize, ?User $uploader): DicomInstance
    {
        if ($uploader !== null && ! Study::where('uploaded_by', $uploader->id)->where('study_instance_uid', $metadata->studyInstanceUid)->exists()) {
            $this->limits->spend($uploader, Quota::Uploads);
        }

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
