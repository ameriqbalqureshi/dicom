<?php

namespace App\Services\Dicom;

use App\Models\DicomArchive;
use App\Models\User;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\QuotaExceededException;
use Illuminate\Support\Carbon;

/**
 * Keeps zips of DICOM files in storage, whole. The browser reads the zip and reports what it holds, so the server never
 * unpacks one and a study is imported without its images passing through the application.
 */
class DicomArchives
{
    /**
     * The largest zip accepted, in bytes: the most a single upload to S3 can hold.
     */
    public const MAX_SIZE = 5 * 1024 * 1024 * 1024;

    /**
     * How many bytes the browser sends per request when uploading to the application rather than straight to S3, which
     * stays below the upload limits common on shared hosting.
     */
    public const CHUNK_SIZE = 5 * 1024 * 1024;

    /**
     * Hours a zip without any images may wait before it counts as abandoned.
     */
    public const ABANDONED_AFTER_HOURS = 24;

    private const ZIP_SIGNATURE = "PK\x03\x04";

    public function __construct(private DicomStorage $storage, private PackageLimits $limits) {}

    /**
     * Register a zip the browser is about to upload.
     *
     * @throws QuotaExceededException
     * @throws InvalidDicomFileException
     */
    public function prepare(User $uploader, string $name, int $size): DicomArchive
    {
        if ($size < 1 || $size > self::MAX_SIZE) {
            throw new InvalidDicomFileException('A zip must be 5 GB or smaller.');
        }

        $this->limits->ensureStorageFor($uploader, $size);

        return DicomArchive::create([
            'uploaded_by' => $uploader->id,
            'original_name' => $name,
            'file_path' => $this->storage->archivePathFor($uploader->id),
            'file_size' => $size,
        ]);
    }

    /**
     * Where the browser sends the zip: a signed S3 link, or else the application a chunk at a time.
     *
     * @return array{url: string, headers: array<string, string>, chunkSize: int|null}
     */
    public function uploadTarget(DicomArchive $archive): array
    {
        if ($this->storage->supportsDirectUploads()) {
            return [...$this->storage->uploadTarget($archive->file_path), 'chunkSize' => null];
        }

        return [
            'url' => route('archives.chunks', $archive),
            'headers' => ['X-CSRF-TOKEN' => csrf_token()],
            'chunkSize' => self::CHUNK_SIZE,
        ];
    }

    /**
     * Check what actually arrived, as the browser's word for its size or type cannot be relied on.
     *
     * @throws InvalidDicomFileException
     * @throws QuotaExceededException
     */
    public function confirmUpload(DicomArchive $archive, User $uploader): void
    {
        $disk = $this->storage->disk();

        if (! $disk->exists($archive->file_path)) {
            throw new InvalidDicomFileException('The upload did not finish. Please try this zip again.');
        }

        $size = $disk->size($archive->file_path);

        if ($size > self::MAX_SIZE) {
            throw new InvalidDicomFileException('A zip must be 5 GB or smaller.');
        }

        $this->limits->ensureStorageFor($uploader, $size);

        $stream = $disk->readStream($archive->file_path);
        $signature = $stream === null ? false : stream_get_contents($stream, strlen(self::ZIP_SIGNATURE));

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($signature !== self::ZIP_SIGNATURE) {
            throw new InvalidDicomFileException('The file is not a zip.');
        }

        $archive->update(['file_size' => $size, 'uploaded_at' => Carbon::now()]);
    }

    /**
     * Finish importing a zip. A zip that added no images is removed. Otherwise the whole zip stays in storage, including
     * anything in it that is not an image, so that remainder counts against the storage of its first image.
     */
    public function finish(DicomArchive $archive): void
    {
        $first = $archive->instances()->orderBy('id')->first();

        if ($first === null) {
            $this->deleteIfUnused($archive);

            return;
        }

        $remainder = $archive->file_size - (int) $archive->instances()->sum('file_size');

        if ($remainder > 0) {
            $first->increment('file_size', $remainder);
        }
    }

    /**
     * Remove a zip once no images are read from it. Other studies in the same zip keep it in place.
     */
    public function deleteIfUnused(DicomArchive $archive): void
    {
        if ($archive->instances()->exists()) {
            return;
        }

        $this->storage->delete([$archive->file_path]);
        $archive->delete();
    }

    /**
     * Remove zips whose import was never finished, for example because the browser was closed.
     *
     * @return int How many were removed.
     */
    public function pruneAbandoned(): int
    {
        $abandoned = DicomArchive::whereDoesntHave('instances')
            ->where('created_at', '<', now()->subHours(self::ABANDONED_AFTER_HOURS))
            ->get();

        $abandoned->each(fn (DicomArchive $archive) => $this->deleteIfUnused($archive));

        return $abandoned->count();
    }
}
