<?php

namespace App\Livewire\Billing;

use App\Enums\PaymentAccountType;
use App\Models\PaymentAccount;
use App\Services\Billing\BillingSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lets administrators manage the accounts buyers pay into, the instructions they see and the address for receipts.
 */
#[Title('Payment accounts')]
class PaymentAccountIndex extends Component
{
    /**
     * Whether the form is open, and the account being edited (null for a new one).
     */
    public bool $editing = false;

    public ?int $accountId = null;

    public string $name = '';

    public string $type = PaymentAccountType::Bank->value;

    public string $accountTitle = '';

    public string $accountNumber = '';

    public string $iban = '';

    public string $branch = '';

    public string $instructions = '';

    public string $sortOrder = '0';

    public bool $isActive = true;

    public string $paymentInstructions = '';

    public string $receiptEmail = '';

    public ?string $status = null;

    public function mount(BillingSettings $settings): void
    {
        $this->authorize('manage-billing');

        $this->paymentInstructions = $settings->paymentInstructions();
        $this->receiptEmail = (string) $settings->receiptEmail();
    }

    public function create(): void
    {
        $this->authorize('manage-billing');

        $this->resetForm();
        $this->editing = true;
    }

    public function edit(int $accountId): void
    {
        $this->authorize('manage-billing');

        $account = PaymentAccount::findOrFail($accountId);

        $this->resetForm();
        $this->editing = true;
        $this->accountId = $account->id;
        $this->name = $account->name;
        $this->type = $account->type->value;
        $this->accountTitle = (string) $account->account_title;
        $this->accountNumber = (string) $account->account_number;
        $this->iban = (string) $account->iban;
        $this->branch = (string) $account->branch;
        $this->instructions = (string) $account->instructions;
        $this->sortOrder = (string) $account->sort_order;
        $this->isActive = $account->is_active;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorize('manage-billing');

        $isBank = $this->type === PaymentAccountType::Bank->value;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique(PaymentAccount::class, 'name')->ignore($this->accountId)],
            'type' => ['required', Rule::enum(PaymentAccountType::class)],
            'accountTitle' => ['required', 'string', 'max:255'],
            'accountNumber' => [$isBank ? 'required_without:iban' : 'required', 'nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:50'],
            'branch' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:1000'],
            'isActive' => ['boolean'],
        ], [
            'accountNumber.required_without' => 'Enter an account number or an IBAN.',
        ]);

        PaymentAccount::updateOrCreate(['id' => $this->accountId], [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'account_title' => $validated['accountTitle'],
            'account_number' => filled($validated['accountNumber']) ? $validated['accountNumber'] : null,
            'iban' => $isBank && filled($validated['iban']) ? $validated['iban'] : null,
            'branch' => $isBank && filled($validated['branch']) ? $validated['branch'] : null,
            'instructions' => filled($validated['instructions']) ? $validated['instructions'] : null,
            'sort_order' => (int) $validated['sortOrder'],
            'is_active' => $validated['isActive'],
        ]);

        $this->status = $this->accountId ? 'Account updated.' : 'Account added.';
        $this->resetForm();
    }

    public function toggleActive(int $accountId): void
    {
        $this->authorize('manage-billing');

        $account = PaymentAccount::findOrFail($accountId);
        $account->update(['is_active' => ! $account->is_active]);

        $this->status = $account->is_active ? "{$account->name} is shown to buyers again." : "{$account->name} is hidden from buyers.";
    }

    /**
     * Delete an account. Past payments keep the account's name, so they are not affected.
     */
    public function delete(int $accountId): void
    {
        $this->authorize('manage-billing');

        $account = PaymentAccount::findOrFail($accountId);
        $account->delete();

        $this->status = "{$account->name} was deleted.";
    }

    public function saveSettings(BillingSettings $settings): void
    {
        $this->authorize('manage-billing');

        $validated = $this->validate([
            'paymentInstructions' => ['required', 'string', 'max:2000'],
            'receiptEmail' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $settings->update($validated['paymentInstructions'], filled($validated['receiptEmail']) ? $validated['receiptEmail'] : null);

        $this->status = 'Payment settings saved.';
    }

    private function resetForm(): void
    {
        $this->reset('editing', 'accountId', 'name', 'type', 'accountTitle', 'accountNumber', 'iban', 'branch', 'instructions', 'sortOrder', 'isActive');
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.billing.payment-account-index', [
            'accounts' => PaymentAccount::query()->inDisplayOrder()->get(),
            'types' => PaymentAccountType::cases(),
        ]);
    }
}
