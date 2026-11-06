<?php

namespace App\Console\Commands;

use App\Models\DicomInstance;
use App\Services\Dicom\DicomStorage;
use Illuminate\Console\Command;
use Throwable;

class ReorganizeDicomFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dicom:reorganize {--dry-run : List the moves without performing them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move stored DICOM files into the uploader / study date / study / series folder layout';

    /**
     * Execute the console command.
     */
    public function handle(DicomStorage $storage): int
    {
        $disk = $storage->disk();
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $skipped = 0;

        DicomInstance::with('series.study')->whereNull('dicom_archive_id')->chunkById(200, function ($instances) use ($storage, $disk, $dryRun, &$moved, &$skipped): void {
            foreach ($instances as $instance) {
                $study = $instance->series->study;
                $target = $storage->pathFor($study->uploaded_by, $study->study_instance_uid, $instance->series->series_instance_uid, $instance->sop_instance_uid, $study->study_date);

                if ($target === $instance->file_path) {
                    continue;
                }

                if (! $disk->exists($instance->file_path)) {
                    $this->warn("Missing file for instance {$instance->id}: {$instance->file_path}");
                    $skipped++;

                    continue;
                }

                if ($disk->exists($target)) {
                    $this->warn("Target already exists for instance {$instance->id}: {$target}");
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("{$instance->file_path} → {$target}");
                    $moved++;

                    continue;
                }

                $source = $instance->file_path;
                $storage->move($source, $target);

                try {
                    $instance->update(['file_path' => $target]);
                } catch (Throwable $exception) {
                    $storage->move($target, $source);

                    throw $exception;
                }

                $moved++;
            }
        });

        $this->info(($dryRun ? 'Would move' : 'Moved')." {$moved} file(s); skipped {$skipped}.");

        return $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }
}
