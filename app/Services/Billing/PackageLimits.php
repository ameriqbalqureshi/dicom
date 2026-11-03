<?php

namespace App\Services\Billing;

use App\Enums\Quota;
use App\Models\DicomInstance;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the limits of a user's current package. Administrators are not limited.
 */
class PackageLimits
{
    /**
     * The user's current subscription: the most recently started active one that has not ended.
     */
    public function current(User $user): ?Subscription
    {
        return $user->subscriptions()->current()->with('package')->latest('starts_at')->latest('id')->first();
    }

    /**
     * Whether the user could use the quota once more.
     */
    public function allows(User $user, Quota $quota): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $subscription = $this->current($user);

        return $subscription !== null && $subscription->remaining($quota) !== 0;
    }

    /**
     * @throws QuotaExceededException
     */
    public function ensure(User $user, Quota $quota): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $this->ensureWithin($this->current($user), $quota);
    }

    /**
     * Use the quota once, counting it against the current package. Quotas on the studies kept have no counter: the
     * study must be created in the same transaction as this call, which holds the lock until it commits.
     *
     * @throws QuotaExceededException
     */
    public function spend(User $user, Quota $quota): void
    {
        if ($user->isAdmin()) {
            return;
        }

        DB::transaction(function () use ($user, $quota): void {
            $subscription = $user->subscriptions()->current()->with('package')->latest('starts_at')->latest('id')->lockForUpdate()->first();

            $this->ensureWithin($subscription, $quota);

            if (! $quota->countsStudiesKept()) {
                $subscription->increment($quota->usageColumn());
            }
        });
    }

    /**
     * Make sure the user has room for another file of the given size.
     *
     * @throws QuotaExceededException
     */
    public function ensureStorageFor(User $user, int $bytes): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $subscription = $this->current($user) ?? throw QuotaExceededException::noPackage();
        $limit = $subscription->storageLimitBytes();

        if ($limit !== null && $this->storageUsed($user) + $bytes > $limit) {
            throw QuotaExceededException::storageFull($subscription);
        }
    }

    /**
     * The size in bytes of the DICOM files the user has uploaded and still keeps.
     */
    public function storageUsed(User $user): int
    {
        return (int) DicomInstance::where('uploaded_by', $user->id)->sum('file_size');
    }

    /**
     * @throws QuotaExceededException
     */
    private function ensureWithin(?Subscription $subscription, Quota $quota): void
    {
        if ($subscription === null) {
            throw QuotaExceededException::noPackage();
        }

        if ($subscription->remaining($quota) === 0) {
            throw QuotaExceededException::limitReached($quota, $subscription);
        }
    }
}
