<?php

namespace App\Livewire\Marketing;

use App\Enums\CommissionType;
use App\Enums\Role;
use App\Models\MarketingProfile;
use App\Services\Marketing\CommissionManager;
use App\Services\Marketing\MarketingSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A marketing officer's own page: their referral link, clients, earnings and payouts. It only ever shows who their clients
 * are and what they paid, with patients' names shortened; never their studies, reports, records or contact details.
 */
#[Title('Marketing')]
class OfficerDashboard extends Component
{
    use WithPagination;

    public string $phone = '';

    public string $payoutDetails = '';

    public ?string $status = null;

    public function mount(): void
    {
        $this->authorize('use-marketing');

        $profile = $this->profile();
        $this->phone = (string) $profile->phone;
        $this->payoutDetails = (string) $profile->payout_details;
    }

    public function savePayoutDetails(): void
    {
        $this->authorize('use-marketing');

        $validated = $this->validate([
            'phone' => ['nullable', 'string', 'max:50'],
            'payoutDetails' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->profile()->update([
            'phone' => filled($validated['phone']) ? trim($validated['phone']) : null,
            'payout_details' => filled($validated['payoutDetails']) ? trim($validated['payoutDetails']) : null,
        ]);

        $this->status = 'Your details were saved.';
    }

    private function profile(): MarketingProfile
    {
        return auth()->user()->marketingProfile()->firstOrFail();
    }

    public function render(CommissionManager $commissions, MarketingSettings $settings): View
    {
        $officer = auth()->user();
        $profile = $this->profile();

        return view('livewire.marketing.officer-dashboard', [
            'profile' => $profile,
            'totals' => $commissions->totalsFor($officer),
            'rates' => collect([Role::Physician, Role::Technician, Role::Patient])->map(fn (Role $role): array => [
                'label' => $role->label(),
                'first' => $commissions->rateFor($profile, $role, CommissionType::FirstPayment),
                'renewal' => $commissions->rateFor($profile, $role, CommissionType::Renewal),
            ]),
            'rules' => $settings->all(),
            'clients' => $officer->referredClients()
                ->with(['subscriptions' => fn ($query) => $query->current()->with('package')->latest('starts_at')])
                ->latest()
                ->paginate(15, pageName: 'clients'),
            'earnings' => $officer->commissions()->latest()->latest('id')->paginate(15, pageName: 'earnings'),
            'payouts' => $officer->commissionPayouts()->latest('paid_at')->get(),
            'currency' => config('billing.currency'),
        ]);
    }
}
