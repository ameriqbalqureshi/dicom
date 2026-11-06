<?php

namespace App\Services\Dicom;

use App\Enums\Quota;
use App\Enums\Role;
use App\Models\DicomArchive;
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

    /**
     * The largest image inside a zip, once extracted, in bytes. These never pass through the application, so only the
     * viewer's memory limits them. Angiography runs reach well beyond MAX_FILE_SIZE.
     */
    public const MAX_ARCHIVE_ENTRY_SIZE = 1024 * 1024 * 1024;

    /**
     * The compression methods of zip entries that viewers can read: stored and deflated.
     */
    private const ARCHIVE_METHODS = [0, 8];

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
     * Import an image inside a zip already in storage, from the header and location the browser read out of the zip.
     * Its share of the zip's stored size, the compressed length, is what counts against the uploader's storage.
     *
     * @param  array<array-key, mixed>  $entry  {attributes: array, offset: int, length: int, size: int, method: int}
     *
     * @throws InvalidDicomFileException
     * @throws DuplicateDicomInstanceException
     * @throws StudyLimitReachedException
     * @throws QuotaExceededException
     */
    public function importArchiveEntry(DicomArchive $archive, array $entry, User $uploader): DicomInstance
    {
        foreach (['offset', 'length', 'size', 'method'] as $field) {
            if (! is_int($entry[$field] ?? null) || $entry[$field] < 0) {
                throw new InvalidDicomFileException('The zip entry could not be read.');
            }
        }

        if ($archive->file_size < $entry['offset'] + $entry['length'] || ! in_array($entry['method'], self::ARCHIVE_METHODS, true)) {
            throw new InvalidDicomFileException('The zip entry could not be read.');
        }

        if ($entry['size'] > self::MAX_ARCHIVE_ENTRY_SIZE) {
            throw new InvalidDicomFileException('The image is larger than 1 GB.');
        }

        $metadata = $this->parser->metadataFromAttributes(is_array($entry['attributes'] ?? null) ? $entry['attributes'] : []);

        $this->ensureCanImport($metadata, $entry['length'], $uploader);

        return DB::transaction(fn (): DicomInstance => $this->persist($metadata, [
            'dicom_archive_id' => $archive->id,
            'file_size' => $entry['length'],
            'archive_entry_offset' => $entry['offset'],
            'archive_entry_length' => $entry['length'],
            'archive_entry_size' => $entry['size'],
            'archive_entry_method' => $entry['method'],
        ], $uploader));
    }

    /**
     * Refuse a zip before its upload starts when a study in it could not be imported anyway.
     *
     * @param  list<string>  $studyInstanceUids
     *
     * @throws StudyLimitReachedException
     * @throws QuotaExceededException
     */
    public function ensureCanImportStudies(User $uploader, array $studyInstanceUids): void
    {
        foreach ($studyInstanceUids as $studyInstanceUid) {
            if ($uploader->role === Role::Patient) {
                $this->ensurePatientCanUpload($uploader, $studyInstanceUid);
            }

            if (! Study::where('uploaded_by', $uploader->id)->where('study_instance_uid', $studyInstanceUid)->exists()) {
                $this->limits->ensure($uploader, Quota::Uploads);
            }
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
        $existingStudy = $this->ensureCanImport($metadata, $fileSize, $uploader);

        $storedPath = $this->storage->pathFor(
            $uploader?->id,
            $metadata->studyInstanceUid,
            $metadata->seriesInstanceUid,
            $metadata->sopInstanceUid,
            $existingStudy->study_date ?? $metadata->studyDate,
        );

        $store($storedPath);

        try {
            return DB::transaction(fn (): DicomInstance => $this->persist($metadata, ['file_path' => $storedPath, 'file_size' => $fileSize], $uploader));
        } catch (Throwable $exception) {
            if (! DicomInstance::where('file_path', $storedPath)->exists()) {
                $this->storage->delete([$storedPath]);
            }

            throw $exception;
        }
    }

    /**
     * Apply the rules every new instance must pass, returning the uploader's study it belongs to, if they have it already.
     *
     * @throws DuplicateDicomInstanceException
     * @throws StudyLimitReachedException
     * @throws QuotaExceededException
     */
    private function ensureCanImport(DicomMetadata $metadata, int $fileSize, ?User $uploader): ?Study
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

        return $existingStudy;
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

    /**
     * @param  array<string, int|string>  $fileColumns  Where the instance's bytes are.
     */
    private function persist(DicomMetadata $metadata, array $fileColumns, ?User $uploader): DicomInstance
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
            ...$fileColumns,
            'uploaded_by' => $uploader?->id,
        ]);
    }
}
