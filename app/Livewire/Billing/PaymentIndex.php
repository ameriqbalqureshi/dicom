<?php

namespace App\Livewire\Billing;

use App\Models\Package;
use App\Models\PaymentAccount;
use App\Models\PaymentSubmission;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Lets administrators review payments: receipts sent through the public form, which they link to an account; purchases
 * users report, which they confirm to activate or reject; and payments that reached them another way, which they record.
 */
#[Title('Payments')]
class PaymentIndex extends Component
{
    use WithFileUploads;

    /**
     * The purchase being rejected, and why.
     */
    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    /**
     * The public form receipt being linked to an account.
     */
    public ?int $linkingId = null;

    public string $linkIdentifier = '';

    public string $linkPackageId = '';

    /**
     * Whether the form for recording a payment is open, and its fields.
     */
    public bool $recording = false;

    public string $recordIdentifier = '';

    public string $recordPackageId = '';

    public string $recordAccountId = '';

    public string $recordAmount = '';

    public string $recordReference = '';

    public string $recordNotes = '';

    /** @var TemporaryUploadedFile|null */
    public $recordReceipt = null;

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

    public function startLinking(int $submissionId): void
    {
        $this->authorize('manage-billing');

        $submission = PaymentSubmission::findOrFail($submissionId);

        $this->linkingId = $submission->id;
        $this->linkIdentifier = $submission->account_identifier;
        $this->linkPackageId = (string) $submission->package_id;
        $this->resetValidation();
    }

    public function cancelLinking(): void
    {
        $this->reset('linkingId', 'linkIdentifier', 'linkPackageId');
        $this->resetValidation();
    }

    /**
     * Make the receipt a purchase by the chosen account, ready to be confirmed or rejected.
     */
    public function linkSubmission(SubscriptionManager $subscriptions): void
    {
        $this->authorize('manage-billing');

        $submission = PaymentSubmission::findOrFail((int) $this->linkingId);
        $user = $this->resolveBuyer($this->linkIdentifier, 'linkIdentifier');

        if ($user === null) {
            return;
        }

        $this->validate(['linkPackageId' => ['required', Rule::in($this->paidPackagesFor($user)->modelKeys())]], [
            'linkPackageId.required' => 'Choose a package for this account.',
            'linkPackageId.in' => 'Choose a package for this account.',
        ]);

        try {
            $subscriptions->linkSubmission($submission, $user, Package::findOrFail($this->linkPackageId));
        } catch (InvalidArgumentException $exception) {
            $this->addError('linkIdentifier', $exception->getMessage());

            return;
        }

        $this->cancelLinking();
        $this->status = "Receipt linked to {$user->name}. Confirm or reject it below.";
    }

    public function discardSubmission(int $submissionId, SubscriptionManager $subscriptions): void
    {
        $this->authorize('manage-billing');

        $subscriptions->discardSubmission(PaymentSubmission::findOrFail($submissionId));

        if ($this->linkingId === $submissionId) {
            $this->cancelLinking();
        }

        $this->status = 'Receipt deleted.';
    }

    public function startRecording(): void
    {
        $this->authorize('manage-billing');

        $this->resetRecording();
        $this->recording = true;
    }

    public function cancelRecording(): void
    {
        $this->resetRecording();
    }

    /**
     * Suggest the package's price when an administrator chooses a package.
     */
    public function updatedRecordPackageId(string $packageId): void
    {
        $this->recordAmount = (string) Package::find((int) $packageId)?->price;
    }

    /**
     * Record a payment that reached an administrator another way, such as a receipt sent by email. The administrator has
     * checked it, so the package starts straight away.
     */
    public function recordPayment(SubscriptionManager $subscriptions): void
    {
        $this->authorize('manage-billing');

        $user = $this->resolveBuyer($this->recordIdentifier, 'recordIdentifier');

        if ($user === null) {
            return;
        }

        $validated = $this->validate([
            'recordPackageId' => ['required', Rule::in($this->paidPackagesFor($user)->modelKeys())],
            'recordAccountId' => ['required', Rule::exists(PaymentAccount::class, 'id')],
            'recordAmount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'recordReference' => ['nullable', 'string', 'max:100'],
            'recordNotes' => ['nullable', 'string', 'max:1000'],
            'recordReceipt' => ['nullable', ...Subscription::receiptFileRules()],
        ], [
            'recordPackageId.required' => 'Choose a package for this account.',
            'recordPackageId.in' => 'Choose a package for this account.',
            'recordAccountId.required' => 'Choose the account the payment was received in.',
        ]);

        try {
            $subscription = $subscriptions->recordPayment(
                $user,
                Package::findOrFail($validated['recordPackageId']),
                PaymentAccount::findOrFail($validated['recordAccountId'])->name,
                (int) $validated['recordAmount'],
                auth()->user(),
                filled($validated['recordReference']) ? trim($validated['recordReference']) : null,
                filled($validated['recordNotes']) ? $validated['recordNotes'] : null,
                $validated['recordReceipt'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('recordIdentifier', $exception->getMessage());

            return;
        }

        $this->resetRecording();
        $this->status = "Payment recorded. {$user->name}'s {$subscription->package->name} package has started.";
    }

    /**
     * Find the account a payment belongs to, or add an error to the given field.
     */
    private function resolveBuyer(string $identifier, string $field): ?User
    {
        $this->resetValidation();

        $user = filled($identifier) ? User::findAccountHolder($identifier) : null;

        if ($user === null) {
            $this->addError($field, 'No account has this user ID or email.');
        }

        return $user;
    }

    /**
     * @return Collection<int, Package>
     */
    private function paidPackagesFor(User $user): Collection
    {
        return Package::query()->purchasableBy($user)->where('price', '>', 0)->get();
    }

    private function resetRecording(): void
    {
        $this->reset('recording', 'recordIdentifier', 'recordPackageId', 'recordAccountId', 'recordAmount', 'recordReference', 'recordNotes', 'recordReceipt');
        $this->resetValidation();
    }

    public function render(): View
    {
        $linkUser = filled($this->linkIdentifier) ? User::findAccountHolder($this->linkIdentifier) : null;
        $recordUser = filled($this->recordIdentifier) ? User::findAccountHolder($this->recordIdentifier) : null;
        $submissions = PaymentSubmission::query()->with('package')->oldest()->get();

        return view('livewire.billing.payment-index', [
            'submissions' => $submissions,
            'suggestedUsers' => $submissions->mapWithKeys(fn (PaymentSubmission $submission) => [
                $submission->id => User::findAccountHolder($submission->account_identifier),
            ]),
            'linkUser' => $linkUser,
            'linkPackages' => $linkUser ? $this->paidPackagesFor($linkUser) : collect(),
            'recordUser' => $recordUser,
            'recordPackages' => $recordUser ? $this->paidPackagesFor($recordUser) : collect(),
            'accounts' => PaymentAccount::query()->inDisplayOrder()->get(),
            'pending' => Subscription::pending()->with(['user', 'package'])->oldest()->get(),
            'reviewed' => Subscription::query()
                ->whereNotNull('reviewed_at')
                ->where('amount', '>', 0)
                ->with(['user', 'package', 'reviewer'])
                ->latest('reviewed_at')
                ->limit(20)
                ->get(),
            'currency' => config('billing.currency'),
        ]);
    }
}
