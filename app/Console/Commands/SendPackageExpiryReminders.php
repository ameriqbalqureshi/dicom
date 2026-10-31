<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\PackageEndingSoon;
use Illuminate\Console\Command;

class SendPackageExpiryReminders extends Command
{
    /**
     * How many days before a package ends its owner is reminded.
     */
    public const DAYS_BEFORE = 3;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages:send-expiry-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email users whose package ends within '.self::DAYS_BEFORE.' days, once per package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = 0;

        Subscription::query()
            ->current()
            ->whereNull('expiry_reminder_sent_at')
            ->where('ends_at', '<=', now()->addDays(self::DAYS_BEFORE))
            ->with(['user', 'package'])
            ->lazyById()
            ->each(function (Subscription $subscription) use (&$count): void {
                $subscription->user->notify(new PackageEndingSoon($subscription));
                $subscription->forceFill(['expiry_reminder_sent_at' => now()])->save();
                $count++;
            });

        $this->info("Sent {$count} ".str('reminder')->plural($count).'.');

        return self::SUCCESS;
    }
}
