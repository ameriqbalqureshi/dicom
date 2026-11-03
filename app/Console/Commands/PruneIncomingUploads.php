<?php

namespace App\Console\Commands;

use App\Services\Dicom\DicomStorage;
use Illuminate\Console\Command;

class PruneIncomingUploads extends Command
{
    /**
     * Hours an upload may wait in the incoming folder before it counts as abandoned.
     */
    public const MAX_AGE_HOURS = 24;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uploads:prune-incoming';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete files browsers uploaded straight to storage but never finished importing';

    /**
     * Execute the console command.
     */
    public function handle(DicomStorage $storage): int
    {
        $disk = $storage->disk();
        $cutoff = now()->subHours(self::MAX_AGE_HOURS)->getTimestamp();

        $abandoned = collect($disk->allFiles(DicomStorage::INCOMING_DIRECTORY))
            ->filter(fn (string $path) => $disk->lastModified($path) < $cutoff)
            ->values()
            ->all();

        $storage->delete($abandoned);

        $this->info('Deleted '.count($abandoned).' abandoned '.str('upload')->plural(count($abandoned)).'.');

        return self::SUCCESS;
    }
}
