<?php

namespace App\Console\Commands;

use App\Models\Study;
use App\Services\StudyHandover;
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
    protected $description = 'Hand over or delete studies past their expiry date (patient uploads), and delete studies nobody kept after their owner gave them up';

    /**
     * Execute the console command.
     */
    public function handle(StudyHandover $handover): int
    {
        $handedOver = 0;
        $deleted = 0;

        Study::query()->expired()->lazyById()->each(function (Study $study) use ($handover, &$handedOver, &$deleted): void {
            $handover->release($study) ? $handedOver++ : $deleted++;
        });

        $deleted += $handover->deleteAbandoned();

        $this->info("Offered {$handedOver} expired ".str('study')->plural($handedOver).' to physicians to keep.');
        $this->info("Deleted {$deleted} ".str('study')->plural($deleted).'.');

        return self::SUCCESS;
    }
}
