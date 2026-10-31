<?php

namespace App\Console\Commands;

use App\Models\Study;
use App\Services\Dicom\StudyDeleter;
use Illuminate\Console\Command;

class DeleteExpiredStudies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'studies:delete-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete studies past their expiry date (patient uploads) together with their files';

    /**
     * Execute the console command.
     */
    public function handle(StudyDeleter $deleter): int
    {
        $count = 0;

        Study::query()->expired()->lazyById()->each(function (Study $study) use ($deleter, &$count): void {
            $deleter->delete($study);
            $count++;
        });

        $this->info("Deleted {$count} expired ".str('study')->plural($count).'.');

        return self::SUCCESS;
    }
}
