<?php

namespace App\Livewire\Marketing;

use App\Models\Commission;
use App\Models\MarketingProfile;
use App\Models\User;
use App\Services\Marketing\CommissionManager;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * An administrator's view of one marketing officer: their results and clients, and the actions on them, such as paying
 * them, adding a bonus or reversing a commission.
 */
class OfficerShow extends Component
{
    use WithPagination;

    #[Locked]
    public int $officerId;

    public string $firstPaymentRate = '';

    public string $renewalRate = '';

    public string $notes = '';

    public string $bonusAmount = '';

    public string $bonusReason = '';

    public string $paymentMethod = '';

    public string $paymentReference = '';

    public string $paymentNotes = '';

    public ?int $reversingCommissionId = null;

    public string $reversalReason = '';

    public ?string $status = null;

    public function mount(User $user): void
    {
        $this->authorize('manage-marketing');

        abort_unless($user->isMarketingOfficer(), 404);

        $this->officerId = $user->id;

        $profile = $this->profile();
        $this->firstPaymentRate = (string) $profile->first_payment_rate;
        $this->renewalRate = (string) $profile->renewal_rate;
        $this->notes = (string) $profile->notes;
    }

    /**
     * Save the rates agreed with this officer alone. Empty rates mean the standard rates apply.
     */
    public function saveProfile(): void
    {
        $this->authorize('manage-marketing');

        $validated = $this->validate([
            'firstPaymentRate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'renewalRate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->profile()->update([
            'first_payment_rate' => filled($validated['firstPaymentRate']) ? $validated['firstPaymentRate'] : null,
            'renewal_rate' => filled($validated['renewalRate']) ? $validated['renewalRate'] : null,
            'notes' => filled($validated['notes']) ? $validated['notes'] : null,
        ]);

        $this->status = 'Saved. Custom rates apply to payments confirmed from now on.';
    }

    /**
     * Stop an officer's code and future commission, or start them again. What they already earned is not affected.
     */
    public function toggleActive(): void
    {
        $this->authorize('manage-marketing');

        $profile = $this->profile();
        $profile->forceFill(['deactivated_at' => $profile->isActive() ? now() : null])->save();

        $this->status = $profile->isActive()
            ? 'This officer is active again.'
            : 'This officer is now inactive. Their code no longer works and new payments earn them nothing. What they already earned can still be paid.';
    }

    public function addBonus(CommissionManager $commissions): void
    {
        $this->authorize('manage-marketing');

        $validated = $this->validate([
            'bonusAmount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'bonusReason' => ['required', 'string', 'max:255'],
        ]);

        $commissions->addBonus($this->officer(), (int) $validated['bonusAmount'], trim($validated['bonusReason']), auth()->user());

        $this->reset('bonusAmount', 'bonusReason');
        $this->status = 'Bonus added. It can be paid straight away.';
    }

    public function pay(CommissionManager $commissions): void
    {
        $this->authorize('manage-marketing');

        $validated = $this->validate([
            'paymentMethod' => ['required', 'string', 'max:100'],
            'paymentReference' => ['nullable', 'string', 'max:100'],
            'paymentNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $payout = $commissions->pay(
                $this->officer(),
                trim($validated['paymentMethod']),
                filled($validated['paymentReference']) ? trim($validated['paymentReference']) : null,
                filled($validated['paymentNotes']) ? $validated['paymentNotes'] : null,
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('paymentMethod', $exception->getMessage());

            return;
        }

        $this->reset('paymentMethod', 'paymentReference', 'paymentNotes');
        $this->status = 'Payment recorded: '.number_format($payout->amount).' '.config('billing.currency').". Statement {$payout->statementNumber()} is ready.";
    }

    public function startReversing(int $commissionId): void
    {
        $this->authorize('manage-marketing');

        $this->reversingCommissionId = $this->officer()->commissions()->findOrFail($commissionId)->id;
        $this->reset('reversalReason');
        $this->resetValidation();
    }

    public function cancelReversing(): void
    {
        $this->reset('reversingCommissionId', 'reversalReason');
    }

    public function reverse(CommissionManager $commissions): void
    {
        $this->authorize('manage-marketing');

        $validated = $this->validate(['reversalReason' => ['required', 'string', 'max:255']]);

        /** @var Commission $commission */
        $commission = $this->officer()->commissions()->findOrFail($this->reversingCommissionId);

        try {
            $commissions->reverse($commission, auth()->user(), trim($validated['reversalReason']));
        } catch (InvalidArgumentException $exception) {
            $this->addError('reversalReason', $exception->getMessage());

            return;
        }

        $this->cancelReversing();
        $this->status = 'Commission reversed.';
    }

    private function officer(): User
    {
        return User::query()->marketingOfficers()->findOrFail($this->officerId);
    }

    private function profile(): MarketingProfile
    {
        return $this->officer()->marketingProfile()->firstOrFail();
    }

    public function render(CommissionManager $commissions): View
    {
        $officer = $this->officer();

        return view('livewire.marketing.officer-show', [
            'officer' => $officer,
            'profile' => $this->profile(),
            'totals' => $commissions->totalsFor($officer),
            'clients' => $officer->referredClients()->latest()->paginate(15, pageName: 'clients'),
            'earnings' => $officer->commissions()->latest()->latest('id')->paginate(15, pageName: 'earnings'),
            'payouts' => $officer->commissionPayouts()->latest('paid_at')->get(),
            'currency' => config('billing.currency'),
        ])->title($officer->name);
    }
}
