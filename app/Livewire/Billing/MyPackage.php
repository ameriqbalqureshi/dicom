<?php

namespace App\Livewire\Billing;

use App\Enums\Quota;
use App\Models\Package;
use App\Models\PaymentAccount;
use App\Models\Subscription;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Shows users their current package and what they have used of it, and lets them buy another package. Paid packages are
 * activated once an administrator confirms the payment.
 */
#[Title('Package')]
class MyPackage extends Component
{
    use WithFileUploads;

    public ?int $selectedPackageId = null;

    public string $paymentAccountId = '';

    public string $paymentReference = '';

    /** @var TemporaryUploadedFile|null */
    public $receipt = null;

    public string $notes = '';

    public ?string $status = null;

    public function mount(): void
    {
        abort_if(auth()->user()->isAdmin(), 403);
    }

    public function choose(int $packageId): void
    {
        $this->selectedPackageId = $this->purchasablePackage($packageId)->id;
        $this->reset('paymentAccountId', 'paymentReference', 'receipt', 'notes', 'status');
        $this->resetValidation();
    }

    public function cancelChoice(): void
    {
        $this->reset('selectedPackageId', 'paymentAccountId', 'paymentReference', 'receipt', 'notes');
        $this->resetValidation();
    }

    public function purchase(SubscriptionManager $subscriptions): void
    {
        $package = $this->purchasablePackage((int) $this->selectedPackageId);

        $validated = $this->validate($package->isFree() ? ['notes' => ['nullable', 'string', 'max:1000']] : [
            'paymentAccountId' => ['required', Rule::exists(PaymentAccount::class, 'id')->where('is_active', true)],
            'paymentReference' => ['required_without:receipt', 'nullable', 'string', 'max:100'],
            'receipt' => ['required_without:paymentReference', 'nullable', ...Subscription::receiptFileRules()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'paymentReference.required_without' => 'Attach your receipt or enter the transaction reference.',
            'receipt.required_without' => 'Attach your receipt or enter the transaction reference.',
        ]);

        try {
            $subscription = $subscriptions->purchase(
                auth()->user(),
                $package,
                isset($validated['paymentAccountId']) ? PaymentAccount::findOrFail($validated['paymentAccountId'])->name : null,
                filled($validated['paymentReference'] ?? null) ? trim($validated['paymentReference']) : null,
                filled($validated['notes']) ? $validated['notes'] : null,
                $validated['receipt'] ?? null,
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

    public function render(PackageLimits $limits, BillingSettings $settings): View
    {
        $user = auth()->user();

        return view('livewire.billing.my-package', [
            'current' => $limits->current($user),
            'quotas' => Quota::forRole($user->role),
            'storageUsedMb' => round($limits->storageUsed($user) / 1024 / 1024, 1),
            'packages' => Package::query()->purchasableBy($user)->get(),
            'selectedPackage' => $this->selectedPackageId ? Package::find($this->selectedPackageId) : null,
            'pending' => $user->subscriptions()->pending()->with('package')->latest()->first(),
            'history' => $user->subscriptions()->with('package')->latest()->latest('id')->limit(10)->get(),
            'currency' => config('billing.currency'),
            'accounts' => PaymentAccount::query()->active()->inDisplayOrder()->get(),
            'paymentInstructions' => $settings->paymentInstructions(),
            'receiptEmail' => $settings->receiptEmail(),
        ]);
    }
}
