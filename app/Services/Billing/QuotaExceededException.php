<?php

namespace App\Services\Billing;

use App\Enums\Quota;
use App\Models\Subscription;
use Exception;
use Illuminate\Support\Str;

/**
 * Thrown when a user's package does not allow an action: they have no current package, or have used up its limit.
 */
class QuotaExceededException extends Exception
{
    public static function noPackage(): self
    {
        return new self('You do not have an active package. Choose one on the Package page to continue.');
    }

    public static function limitReached(Quota $quota, Subscription $subscription): self
    {
        $limit = $subscription->limit($quota);

        if ($limit === 0) {
            return new self("Your {$subscription->package->name} package does not include ".Str::lower($quota->label()).'. Upgrade on the Package page.');
        }

        return new self("You have used all {$limit} ".Str::lower($quota->label())." in your {$subscription->package->name} package. Upgrade on the Package page.");
    }

    public static function storageFull(Subscription $subscription): self
    {
        return new self("This would exceed the {$subscription->max_storage_mb} MB of storage in your {$subscription->package->name} package. Delete studies or upgrade on the Package page.");
    }
}
