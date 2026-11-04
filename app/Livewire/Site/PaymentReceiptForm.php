<?php

namespace App\Livewire\Site;

use App\Models\Package;
use App\Models\PaymentAccount;
use App\Models\PaymentSubmission;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentReceiptSubmitted;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The public form for sending a payment receipt without signing in. It never says whether an account exists: every
 * receipt waits for an administrator to link it to an account.
 */
class PaymentReceiptForm extends Component
{
    use WithFileUploads;

    /**
     * Receipts one visitor may send before having to wait.
     */
    public const MAX_ATTEMPTS = 5;

    public string $name = '';

    public string $accountIdentifier = '';

    public string $phone = '';

    public string $packageId = '';

    public string $paymentAccountId = '';

    public string $paymentReference = '';

    public string $notes = '';

    /** @var TemporaryUploadedFile|null */
    public $receipt = null;

    /**
     * A field hidden from people; bots that fill it in are ignored.
     */
    public string $website = '';

    public bool $sent = false;

    public function mount(): void
    {
        if (auth()->check()) {
            $this->name = auth()->user()->name;
            $this->accountIdentifier = auth()->user()->email;
        }
    }

    public function submit(SubscriptionManager $subscriptions): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'accountIdentifier' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'packageId' => ['required', Rule::in($this->packages()->modelKeys())],
            'paymentAccountId' => ['required', Rule::exists(PaymentAccount::class, 'id')->where('is_active', true)],
            'paymentReference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['required', ...Subscription::receiptFileRules()],
        ]);

        $throttleKey = 'payment-receipt-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'receipt' => 'You have sent several receipts already. Please try again in '.ceil(RateLimiter::availableIn($throttleKey) / 60).' minutes.',
            ]);
        }

        RateLimiter::hit($throttleKey, 3600);

        if ($this->website === '') {
            PaymentSubmission::create([
                'name' => trim($validated['name']),
                'account_identifier' => trim($validated['accountIdentifier']),
                'phone' => filled($validated['phone']) ? trim($validated['phone']) : null,
                'package_id' => (int) $validated['packageId'],
                'payment_method' => PaymentAccount::findOrFail($validated['paymentAccountId'])->name,
                'payment_reference' => filled($validated['paymentReference']) ? trim($validated['paymentReference']) : null,
                'receipt_path' => $subscriptions->storeReceipt($this->receipt),
                'notes' => filled($validated['notes']) ? $validated['notes'] : null,
            ]);

            Notification::send(User::admins()->get(), new PaymentReceiptSubmitted);
        }

        $this->reset('name', 'accountIdentifier', 'phone', 'packageId', 'paymentAccountId', 'paymentReference', 'notes', 'receipt');
        $this->sent = true;
    }

    /**
     * The packages on sale for any role. Who is sending the form is not known yet.
     *
     * @return Collection<int, Package>
     */
    private function packages(): Collection
    {
        return Package::query()->active()->where('is_trial', false)->where('price', '>', 0)->orderBy('role')->orderBy('price')->get();
    }

    public function render(): View
    {
        return view('livewire.site.payment-receipt-form', [
            'packages' => $this->packages(),
            'accounts' => PaymentAccount::query()->active()->inDisplayOrder()->get(),
            'currency' => config('billing.currency'),
        ]);
    }
}
