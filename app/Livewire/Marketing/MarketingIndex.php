<?php

namespace App\Livewire\Marketing;

use App\Enums\CommissionType;
use App\Enums\Role;
use App\Models\Commission;
use App\Models\MarketingApplication;
use App\Models\User;
use App\Services\Marketing\MarketingOfficers;
use App\Services\Marketing\MarketingSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lets administrators review applications, compare officers and set the commission rules.
 */
#[Title('Marketing')]
class MarketingIndex extends Component
{
    use WithPagination;

    public ?int $rejectingApplicationId = null;

    public string $rejectionReason = '';

    /**
     * The standard percentages, by client role.
     *
     * @var array<string, array{first: float|int|string, renewal: float|int|string}>
     */
    public array $rates = [];

    public int|string $renewalMonths = 12;

    public int|string $holdDays = 14;

    public ?string $status = null;

    public function mount(MarketingSettings $settings): void
    {
        $this->authorize('manage-marketing');

        $rules = $settings->all();
        $this->rates = $rules['rates'];
        $this->renewalMonths = $rules['renewal_months'];
        $this->holdDays = $rules['hold_days'];
    }

    public function approve(int $applicationId, MarketingOfficers $officers): void
    {
        $this->authorize('manage-marketing');

        try {
            $officer = $officers->approve(MarketingApplication::findOrFail($applicationId), auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('applications', $exception->getMessage());

            return;
        }

        $this->status = "{$officer->name} is now a marketing officer. We emailed them a link to set their password.";
    }

    public function startRejecting(int $applicationId): void
    {
        $this->authorize('manage-marketing');

        $this->rejectingApplicationId = MarketingApplication::findOrFail($applicationId)->id;
        $this->reset('rejectionReason');
    }

    public function cancelRejecting(): void
    {
        $this->reset('rejectingApplicationId', 'rejectionReason');
    }

    public function reject(MarketingOfficers $officers): void
    {
        $this->authorize('manage-marketing');

        $validated = $this->validate(['rejectionReason' => ['nullable', 'string', 'max:1000']]);
        $application = MarketingApplication::findOrFail($this->rejectingApplicationId);

        try {
            $officers->reject($application, auth()->user(), filled($validated['rejectionReason']) ? trim($validated['rejectionReason']) : null);
        } catch (InvalidArgumentException $exception) {
            $this->addError('applications', $exception->getMessage());

            return;
        }

        $this->cancelRejecting();
        $this->status = "{$application->name}'s application was rejected.";
    }

    public function saveRules(MarketingSettings $settings): void
    {
        $this->authorize('manage-marketing');

        $validated = $this->validate([
            'rates' => ['required', 'array:physician,technician,patient'],
            'rates.*.first' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'rates.*.renewal' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'renewalMonths' => ['required', 'integer', 'min:0', 'max:120'],
            'holdDays' => ['required', 'integer', 'min:0', 'max:365'],
        ], [], [
            'rates.*.first' => 'first payment rate',
            'rates.*.renewal' => 'renewal rate',
        ]);

        $settings->update([
            'rates' => array_map(fn (array $rate): array => [
                'first' => (float) $rate['first'],
                'renewal' => (float) $rate['renewal'],
            ], $validated['rates']),
            'renewal_months' => (int) $validated['renewalMonths'],
            'hold_days' => (int) $validated['holdDays'],
        ]);

        $this->status = 'Commission rules saved. They apply to payments confirmed from now on.';
    }

    /**
     * Download every officer's results as a CSV file.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('manage-marketing');

        $officers = $this->officersWithResults()->orderBy('name')->lazy();

        return response()->streamDownload(function () use ($officers): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Email', 'Referral code', 'Active', 'Clients', 'Paying clients', 'On hold', 'Payable', 'Paid']);

            foreach ($officers as $officer) {
                fputcsv($output, [
                    $officer->name,
                    $officer->email,
                    $officer->marketingProfile?->referral_code,
                    $officer->marketingProfile?->isActive() ? 'Yes' : 'No',
                    $officer->referred_clients_count,
                    $officer->paying_clients_count,
                    (int) $officer->on_hold_total,
                    (int) $officer->payable_total,
                    (int) $officer->paid_total,
                ]);
            }

            fclose($output);
        }, 'marketing-officers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Builder<User>
     */
    private function officersWithResults(): Builder
    {
        return User::query()
            ->marketingOfficers()
            ->with('marketingProfile')
            ->withCount('referredClients')
            ->withCount(['commissions as paying_clients_count' => fn (Builder $query) => $query
                ->where('type', CommissionType::FirstPayment)
                ->whereNull('reversed_at'),
            ])
            ->withSum(['commissions as on_hold_total' => fn (Builder $query) => $query->onHold()], 'amount')
            ->withSum(['commissions as payable_total' => fn (Builder $query) => $query->payable()], 'amount')
            ->withSum('commissionPayouts as paid_total', 'amount');
    }

    public function render(): View
    {
        return view('livewire.marketing.marketing-index', [
            'applications' => MarketingApplication::query()->pending()->oldest()->get(),
            'officers' => $this->officersWithResults()->orderByDesc('payable_total')->orderBy('name')->paginate(25),
            'clientRoles' => [Role::Physician, Role::Technician, Role::Patient],
            'currency' => config('billing.currency'),
        ]);
    }
}
