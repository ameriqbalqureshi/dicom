<?php

namespace App\Livewire\Billing;

use App\Models\Subscription;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lets administrators confirm the payments users report for packages, which activates them, or reject them.
 */
#[Title('Payments')]
class PaymentIndex extends Component
{
    /**
     * The purchase being rejected, and why.
     */
    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    public ?string $status = null;

    public function mount(): void
    {
        $this->authorize('manage-billing');
    }

    public function approve(int $subscriptionId, SubscriptionManager $subscriptions): void
    {
        $this->authorize('manage-billing');

        $subscription = Subscription::pending()->with(['user', 'package'])->findOrFail($subscriptionId);
        $subscriptions->approve($subscription, auth()->user());

        $this->status = "Payment confirmed. {$subscription->user->name}'s {$subscription->package->name} package has started.";
    }

    public function startRejecting(int $subscriptionId): void
    {
        $this->rejectingId = Subscription::pending()->findOrFail($subscriptionId)->id;
        $this->reset('rejectionReason');
        $this->resetValidation();
    }

    public function cancelRejecting(): void
    {
        $this->reset('rejectingId', 'rejectionReason');
    }

    public function reject(SubscriptionManager $subscriptions): void
    {
        $this->authorize('manage-billing');

        $subscription = Subscription::pending()->with('user')->findOrFail((int) $this->rejectingId);
        $validated = $this->validate(['rejectionReason' => ['required', 'string', 'max:255']]);

        $subscriptions->reject($subscription, auth()->user(), $validated['rejectionReason']);

        $this->cancelRejecting();
        $this->status = "Payment from {$subscription->user->name} rejected.";
    }

    public function render(): View
    {
        return view('livewire.billing.payment-index', [
            'pending' => Subscription::pending()->with(['user', 'package'])->oldest()->get(),
            'reviewed' => Subscription::query()
                ->whereNotNull('reviewed_at')
                ->where('amount', '>', 0)
                ->with(['user', 'package', 'reviewer'])
                ->latest('reviewed_at')
                ->limit(20)
                ->get(),
            'currency' => config('billing.currency'),
            'paymentMethods' => config('billing.payment_methods'),
        ]);
    }
}
