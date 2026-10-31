<?php

namespace App\Services\Dicom;

use Carbon\CarbonInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Lays out DICOM files on disk as uploader / study date / study / series / instance, mirroring the DICOM hierarchy.
 */
class DicomStorage
{
    public const DISK = 'dicom';

    /**
     * The path of an instance's file, e.g. users/7/2024/03/15/{study uid}/{series uid}/{sop instance uid}.dcm.
     */
    public function pathFor(?int $uploaderId, string $studyInstanceUid, string $seriesInstanceUid, string $sopInstanceUid, ?CarbonInterface $studyDate): string
    {
        return implode('/', [
            $uploaderId === null ? 'unowned' : "users/{$uploaderId}",
            $studyDate?->format('Y/m/d') ?? 'undated',
            $this->directoryName($studyInstanceUid),
            $this->directoryName($seriesInstanceUid),
            $this->directoryName($sopInstanceUid).'.dcm',
        ]);
    }

    /**
     * Delete files and any directories they leave empty.
     *
     * @param  list<string>  $paths
     */
    public function delete(array $paths): void
    {
        $this->disk()->delete($paths);

        foreach (array_unique(array_map(dirname(...), $paths)) as $directory) {
            $this->pruneEmptyDirectories($directory);
        }
    }

    /**
     * Move a file to a new path, removing directories the move leaves empty.
     */
    public function move(string $from, string $to): void
    {
        $this->disk()->move($from, $to);
        $this->pruneEmptyDirectories(dirname($from));
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk(self::DISK);
    }

    /**
     * Use a UID as a directory name only when it is a well-formed UID, so a malformed header cannot inject path segments.
     */
    private function directoryName(string $uid): string
    {
        return strlen($uid) <= 64 && preg_match('/^\d+(\.\d+)*$/', $uid) === 1 ? $uid : sha1($uid);
    }

    private function pruneEmptyDirectories(string $directory): void
    {
        $disk = $this->disk();

        while (! in_array($directory, ['.', '', '/'], true)
            && $disk->directoryExists($directory)
            && $disk->files($directory) === []
            && $disk->directories($directory) === []) {
            $disk->deleteDirectory($directory);
            $directory = dirname($directory);
        }
    }
}
