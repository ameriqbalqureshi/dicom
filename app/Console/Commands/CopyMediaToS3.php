<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CopyMediaToS3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:copy-to-s3 {--dry-run : List the files without copying them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy locally stored DICOM files, signatures and payment receipts to the S3 bucket, leaving the local copies in place';

    /**
     * Folders under storage/app/private that are copied to the same paths in the bucket.
     *
     * @var list<string>
     */
    private const FOLDERS = ['dicom', 'signatures', 'receipts'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = Storage::build(['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true]);
        $target = Storage::disk('s3');
        $dryRun = (bool) $this->option('dry-run');
        $copied = 0;
        $skipped = 0;
        $failed = 0;

        foreach (self::FOLDERS as $folder) {
            foreach ($source->allFiles($folder) as $path) {
                if ($target->exists($path) && $target->size($path) === $source->size($path)) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line($path);
                    $copied++;

                    continue;
                }

                try {
                    $this->copy($source, $target, $path);
                    $copied++;
                } catch (Throwable $exception) {
                    $this->error("Failed to copy {$path}: {$exception->getMessage()}");
                    $failed++;
                }
            }
        }

        $this->info(($dryRun ? 'Would copy' : 'Copied')." {$copied} file(s), skipped {$skipped} already on S3, {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Stream one file to S3 and confirm the uploaded size matches the original.
     */
    private function copy(Filesystem $source, Filesystem $target, string $path): void
    {
        $stream = $source->readStream($path);

        try {
            if (! $target->writeStream($path, $stream)) {
                throw new RuntimeException('the upload was rejected');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($target->size($path) !== $source->size($path)) {
            throw new RuntimeException('uploaded size does not match the local file');
        }
    }
}
