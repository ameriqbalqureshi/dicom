<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\PaymentSubmission;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PackageStarted;
use App\Notifications\PaymentRejected;
use App\Notifications\PaymentSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Starts trials, records package purchases and activates them. Payments are made outside the application for now, so a
 * paid purchase waits until an administrator confirms the payment.
 */
class SubscriptionManager
{
    /**
     * Give a new user their role's trial package, if there is one.
     */
    public function startTrial(User $user): ?Subscription
    {
        $trial = Package::trialFor($user->role);

        if ($trial === null) {
            return null;
        }

        return $this->activate($user->subscriptions()->create([
            'package_id' => $trial->id,
            'status' => SubscriptionStatus::Pending,
            'amount' => 0,
        ]));
    }

    /**
     * Record a purchase. A free package is activated at once; a paid one waits for an administrator to confirm the
     * payment made with the given reference or receipt.
     *
     * @throws InvalidArgumentException
     */
    public function purchase(User $user, Package $package, ?string $paymentMethod = null, ?string $paymentReference = null, ?string $notes = null, ?UploadedFile $receipt = null): Subscription
    {
        $this->ensureCanBuy($user, $package);

        $subscription = $user->subscriptions()->create([
            'package_id' => $package->id,
            'status' => SubscriptionStatus::Pending,
            'amount' => $package->price,
            'payment_method' => $package->isFree() ? null : $paymentMethod,
            'payment_reference' => $package->isFree() ? null : $paymentReference,
            'receipt_path' => $package->isFree() || $receipt === null ? null : $this->storeReceipt($receipt),
            'notes' => $notes,
        ]);

        if ($package->isFree()) {
            return $this->activate($subscription);
        }

        Notification::send(User::admins()->get(), new PaymentSubmitted($subscription));

        return $subscription;
    }

    /**
     * Keep a receipt a buyer uploaded on the private receipt disk, returning where it is.
     */
    public function storeReceipt(UploadedFile $receipt): string
    {
        return $receipt->store('receipts/'.now()->format('Y'), Subscription::RECEIPT_DISK);
    }

    /**
     * Turn a receipt sent through the public form into a purchase by the given user, awaiting confirmation like any
     * other.
     *
     * @throws InvalidArgumentException
     */
    public function linkSubmission(PaymentSubmission $submission, User $user, Package $package): Subscription
    {
        $this->ensureCanBuy($user, $package);

        return DB::transaction(function () use ($submission, $user, $package): Subscription {
            $subscription = $user->subscriptions()->create([
                'package_id' => $package->id,
                'status' => SubscriptionStatus::Pending,
                'amount' => $package->price,
                'payment_method' => $submission->payment_method,
                'payment_reference' => $submission->payment_reference,
                'receipt_path' => $submission->receipt_path,
                'notes' => $submission->notes,
            ]);

            $submission->delete();

            return $subscription;
        });
    }

    /**
     * Delete a receipt sent through the public form that will not become a purchase, such as spam.
     */
    public function discardSubmission(PaymentSubmission $submission): void
    {
        Storage::disk(Subscription::RECEIPT_DISK)->delete($submission->receipt_path);

        $submission->delete();
    }

    /**
     * Withdraw a purchase that has not been reviewed yet. Its receipt is no longer needed.
     */
    public function cancel(Subscription $subscription): void
    {
        $this->ensurePending($subscription);

        if ($subscription->receipt_path !== null) {
            Storage::disk(Subscription::RECEIPT_DISK)->delete($subscription->receipt_path);
        }

        $subscription->forceFill(['status' => SubscriptionStatus::Cancelled, 'receipt_path' => null])->save();
    }

    /**
     * Confirm the payment for a purchase and activate it.
     */
    public function approve(Subscription $subscription, User $reviewer): Subscription
    {
        $this->ensurePending($subscription);

        $subscription->forceFill(['reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
        $this->activate($subscription);

        $subscription->user->notify(new PackageStarted($subscription));

        return $subscription;
    }

    public function reject(Subscription $subscription, User $reviewer, string $reason): void
    {
        $this->ensurePending($subscription);

        $subscription->forceFill([
            'status' => SubscriptionStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $subscription->user->notify(new PaymentRejected($subscription));
    }

    /**
     * Give a user a package without payment, e.g. as a courtesy or after payment was received some other way.
     */
    public function assign(User $user, Package $package, User $admin): Subscription
    {
        $subscription = $user->subscriptions()->create([
            'package_id' => $package->id,
            'status' => SubscriptionStatus::Pending,
            'amount' => 0,
            'notes' => "Assigned by {$admin->name}",
        ]);

        $subscription->forceFill(['reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        $this->activate($subscription);

        $user->notify(new PackageStarted($subscription));

        return $subscription;
    }

    /**
     * Record a payment an administrator already checked, such as a receipt that arrived by email, and start the package.
     *
     * @throws InvalidArgumentException
     */
    public function recordPayment(User $user, Package $package, string $paymentMethod, int $amount, User $admin, ?string $paymentReference = null, ?string $notes = null, ?UploadedFile $receipt = null): Subscription
    {
        $this->ensureCanBuy($user, $package);

        $subscription = $user->subscriptions()->create([
            'package_id' => $package->id,
            'status' => SubscriptionStatus::Pending,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'receipt_path' => $receipt === null ? null : $this->storeReceipt($receipt),
            'notes' => $notes,
        ]);

        return $this->approve($subscription, $admin);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function ensureCanBuy(User $user, Package $package): void
    {
        if (! Package::query()->purchasableBy($user)->whereKey($package->id)->exists()) {
            throw new InvalidArgumentException('This package is not available to '.($user->is(auth()->user()) ? 'you' : $user->name).'.');
        }

        if ($user->subscriptions()->pending()->exists()) {
            throw new InvalidArgumentException($user->is(auth()->user())
                ? 'You already have a purchase waiting for confirmation. Cancel it first to choose another package.'
                : "{$user->name} already has a purchase waiting for confirmation. Confirm or reject it first.");
        }
    }

    /**
     * Start the subscription now with a copy of its package's limits. It replaces the user's previous package, and its
     * usage starts from zero.
     */
    private function activate(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription): Subscription {
            $package = $subscription->package;

            Subscription::query()
                ->whereBelongsTo($subscription->user)
                ->where('status', SubscriptionStatus::Active)
                ->whereKeyNot($subscription->id)
                ->update(['status' => SubscriptionStatus::Replaced, 'ends_at' => now()]);

            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'starts_at' => now(),
                'ends_at' => $package->duration_days === null ? null : now()->addDays($package->duration_days),
                ...$package->limits(),
            ])->save();

            return $subscription;
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    private function ensurePending(Subscription $subscription): void
    {
        if (! $subscription->isPending()) {
            throw new InvalidArgumentException('This purchase has already been reviewed.');
        }
    }
}
