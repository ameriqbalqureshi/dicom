<?php

namespace App\Livewire\Billing;

use App\Enums\Quota;
use App\Models\Package;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Shows users their current package and what they have used of it, and lets them buy another package. Paid packages are
 * activated once an administrator confirms the payment.
 */
#[Title('Package')]
class MyPackage extends Component
{
    public ?int $selectedPackageId = null;

    public string $paymentMethod = '';

    public string $paymentReference = '';

    public string $notes = '';

    public ?string $status = null;

    public function mount(): void
    {
        abort_if(auth()->user()->isAdmin(), 403);
    }

    public function choose(int $packageId): void
    {
        $this->selectedPackageId = $this->purchasablePackage($packageId)->id;
        $this->reset('paymentMethod', 'paymentReference', 'notes', 'status');
        $this->resetValidation();
    }

    public function cancelChoice(): void
    {
        $this->reset('selectedPackageId', 'paymentMethod', 'paymentReference', 'notes');
        $this->resetValidation();
    }

    public function purchase(SubscriptionManager $subscriptions): void
    {
        $package = $this->purchasablePackage((int) $this->selectedPackageId);

        $validated = $this->validate($package->isFree() ? ['notes' => ['nullable', 'string', 'max:1000']] : [
            'paymentMethod' => ['required', Rule::in(array_keys(config('billing.payment_methods')))],
            'paymentReference' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $subscription = $subscriptions->purchase(
                auth()->user(),
                $package,
                $validated['paymentMethod'] ?? null,
                isset($validated['paymentReference']) ? trim($validated['paymentReference']) : null,
                filled($validated['notes']) ? $validated['notes'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('purchase', $exception->getMessage());

            return;
        }

        $this->cancelChoice();
        $this->status = $subscription->isPending()
            ? "Thank you. Your {$package->name} package will start as soon as we confirm your payment."
            : "Your {$package->name} package has started.";
    }

    public function cancelPending(int $subscriptionId, SubscriptionManager $subscriptions): void
    {
        $subscription = auth()->user()->subscriptions()->pending()->findOrFail($subscriptionId);

        $subscriptions->cancel($subscription);

        $this->status = 'Purchase cancelled.';
    }

    private function purchasablePackage(int $packageId): Package
    {
        return Package::query()->purchasableBy(auth()->user())->findOrFail($packageId);
    }

    public function render(PackageLimits $limits): View
    {
        $user = auth()->user();

        return view('livewire.billing.my-package', [
            'current' => $limits->current($user),
            'quotas' => Quota::cases(),
            'storageUsedMb' => round($limits->storageUsed($user) / 1024 / 1024, 1),
            'packages' => Package::query()->purchasableBy($user)->get(),
            'selectedPackage' => $this->selectedPackageId ? Package::find($this->selectedPackageId) : null,
            'pending' => $user->subscriptions()->pending()->with('package')->latest()->first(),
            'history' => $user->subscriptions()->with('package')->latest()->latest('id')->limit(10)->get(),
            'currency' => config('billing.currency'),
            'paymentMethods' => config('billing.payment_methods'),
            'paymentInstructions' => config('billing.payment_instructions'),
        ]);
    }
}
