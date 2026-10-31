<?php

namespace App\Services\Dicom;

use App\Models\DicomInstance;
use App\Models\Series;
use App\Models\Study;
use App\Models\User;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stores a DICOM file and files it under its study and series.
 */
class DicomImporter
{
    public const DISK = DicomStorage::DISK;

    public function __construct(private DicomParser $parser, private DicomStorage $storage) {}

    /**
     * @throws InvalidDicomFileException
     * @throws DuplicateDicomInstanceException
     */
    public function import(string $path, ?User $uploader = null): DicomInstance
    {
        $metadata = $this->parser->parse($path);

        $uploaderId = $uploader?->id;

        if (DicomInstance::where('uploaded_by', $uploaderId)->where('sop_instance_uid', $metadata->sopInstanceUid)->exists()) {
            throw new DuplicateDicomInstanceException("Instance {$metadata->sopInstanceUid} has already been uploaded.");
        }

        $storedPath = $this->storage->pathFor(
            $uploaderId,
            $metadata->studyInstanceUid,
            $metadata->seriesInstanceUid,
            $metadata->sopInstanceUid,
            Study::where('uploaded_by', $uploaderId)->where('study_instance_uid', $metadata->studyInstanceUid)->value('study_date') ?? $metadata->studyDate,
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
