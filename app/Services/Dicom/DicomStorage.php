<?php

namespace App\Services\Dicom;

use App\Models\DicomInstance;
use Aws\Exception\AwsException;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lays out DICOM files on disk as uploader / study date / study / series / instance, mirroring the DICOM hierarchy.
 */
class DicomStorage
{
    public const DISK = 'dicom';

    /**
     * Where browsers upload files directly, before they are imported and moved to their place in the hierarchy.
     */
    public const INCOMING_DIRECTORY = 'incoming';

    /**
     * Minutes a signed link for viewing a file stays valid. The viewer fetches each file as soon as it gets the link.
     */
    public const DOWNLOAD_LINK_MINUTES = 10;

    /**
     * Minutes a signed link for uploading a file stays valid: long enough for a 100 MB file on a slow connection.
     */
    public const UPLOAD_LINK_MINUTES = 30;

    /**
     * Whether browsers can upload files straight to the disk (S3) rather than through the application.
     */
    public function supportsDirectUploads(): bool
    {
        return $this->usesDirectTransfers();
    }

    /**
     * Whether files go between browsers and the disk directly, through signed URLs, which the disk's configuration
     * turns on for S3. (Laravel's S3 adapter wrongly reports that it cannot sign upload URLs, so this is not detected.)
     */
    private function usesDirectTransfers(): bool
    {
        return (bool) ($this->disk()->getConfig()['direct_transfers'] ?? false);
    }

    /**
     * A new, unique path for a browser to upload one file to.
     */
    public function incomingPathFor(?int $uploaderId): string
    {
        return self::INCOMING_DIRECTORY.'/'.($uploaderId ?? 'unowned').'/'.Str::uuid().'.dcm';
    }

    /**
     * A new, unique path for a zip, which stays there as uploaded.
     */
    public function archivePathFor(?int $uploaderId): string
    {
        return ($uploaderId === null ? 'unowned' : "users/{$uploaderId}").'/archives/'.Str::uuid().'.zip';
    }

    /**
     * Whether the path is one handed out by incomingPathFor() for this uploader, so nobody can import another person's
     * upload or any other file on the disk.
     */
    public function isIncomingPathOf(string $path, ?int $uploaderId): bool
    {
        $prefix = preg_quote(self::INCOMING_DIRECTORY.'/'.($uploaderId ?? 'unowned').'/', '#');

        return preg_match('#^'.$prefix.'[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.dcm$#', $path) === 1;
    }

    /**
     * A signed URL and the headers a browser must send to PUT a file at the path.
     *
     * @return array{url: string, headers: array<string, string>}
     */
    public function uploadTarget(string $path): array
    {
        ['url' => $url, 'headers' => $headers] = $this->disk()->temporaryUploadUrl($path, now()->addMinutes(self::UPLOAD_LINK_MINUTES));

        return [
            'url' => $url,
            'headers' => collect($headers)
                ->reject(fn ($value, string $name) => in_array(strtolower($name), ['host', 'content-length'], true))
                ->map(fn ($value) => implode(', ', (array) $value))
                ->all(),
        ];
    }

    /**
     * The response that gets an instance's bytes to the viewer: its own file or, when it came in a zip, the zip, which
     * the viewer reads the instance out of with a range request.
     *
     * @param  array<string, string>  $headers  Headers for the file itself, e.g. Cache-Control.
     */
    public function instanceResponse(DicomInstance $instance, array $headers = []): Response
    {
        if ($instance->dicom_archive_id === null) {
            return $this->fileResponse($instance->file_path, "{$instance->sop_instance_uid}.dcm", $headers);
        }

        $path = $instance->archive->file_path;

        if ($this->usesDirectTransfers()) {
            return $this->signedLinkResponse($path, basename($path), 'application/zip', $headers);
        }

        abort_unless($this->disk()->exists($path), 404);

        return response()->file($this->disk()->path($path), ['Content-Type' => 'application/zip', ...$headers]);
    }

    /**
     * The response that gets a file to the browser: a redirect to a short-lived signed URL when the disk provides them
     * (S3), so the file does not pass through the application, otherwise the file itself.
     *
     * @param  array<string, string>  $headers  Headers for the file itself, e.g. Cache-Control.
     */
    public function fileResponse(string $path, string $downloadName, array $headers = []): Response
    {
        $disk = $this->disk();

        if ($this->usesDirectTransfers()) {
            return $this->signedLinkResponse($path, $downloadName, 'application/dicom', $headers);
        }

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $downloadName, ['Content-Type' => 'application/dicom', ...$headers]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function signedLinkResponse(string $path, string $downloadName, string $contentType, array $headers): Response
    {
        $url = $this->disk()->temporaryUrl($path, now()->addMinutes(self::DOWNLOAD_LINK_MINUTES), array_filter([
            'ResponseContentType' => $contentType,
            'ResponseContentDisposition' => 'inline; filename="'.$downloadName.'"',
            'ResponseCacheControl' => $headers['Cache-Control'] ?? null,
        ]));

        return redirect()->away($url)->withHeaders([
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

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
     * The first bytes of a file. S3 is asked for only those bytes, as a streamed read depends on how PHP is set up: PHP's
     * stream wrapper needs its own certificates, and without allow_url_fopen the whole file is downloaded instead.
     *
     * @throws UnableToReadFile
     */
    public function readStart(string $path, int $length): string
    {
        $disk = $this->disk();

        if ($disk instanceof AwsS3V3Adapter) {
            try {
                return (string) $disk->getClient()->getObject([
                    'Bucket' => $disk->getConfig()['bucket'],
                    'Key' => $disk->path($path),
                    'Range' => 'bytes=0-'.($length - 1),
                ])['Body'];
            } catch (AwsException $exception) {
                if ($exception->getStatusCode() === Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE) {
                    return '';
                }

                throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
            }
        }

        $stream = $disk->readStream($path) ?? throw UnableToReadFile::fromLocation($path);

        try {
            return (string) stream_get_contents($stream, $length);
        } finally {
            fclose($stream);
        }
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
