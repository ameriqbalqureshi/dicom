<?php

namespace App\Services\Marketing;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\Role;
use App\Models\Commission;
use App\Models\CommissionPayout;
use App\Models\MarketingProfile;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\CommissionEarned;
use App\Notifications\CommissionPaidOut;
use App\Services\BestEffortNotifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Keeps the ledger of what marketing officers earn: commission on confirmed payments, bonuses, reversals and payouts.
 */
class CommissionManager
{
    public function __construct(private MarketingSettings $settings) {}

    /**
     * Record what the client's officer earns on a confirmed payment, if anything. Only real money earns commission, so
     * trials and packages given by an administrator never do. Renewals earn for a limited time after the client's first
     * payment, whichever officer the client belongs to by then.
     */
    public function recordForPayment(Subscription $subscription): ?Commission
    {
        $client = $subscription->user;
        $officer = $client->referrer;
        $profile = $officer?->marketingProfile;

        if ($subscription->amount <= 0 || ! $officer?->isMarketingOfficer() || $profile === null || ! $profile->isActive()) {
            return null;
        }

        $firstCommission = Commission::query()
            ->where('client_id', $client->id)
            ->where('type', CommissionType::FirstPayment)
            ->whereNull('reversed_at')
            ->oldest()
            ->first();

        if ($firstCommission !== null && $firstCommission->created_at->addMonths($this->settings->renewalMonths())->isPast()) {
            return null;
        }

        $type = $firstCommission === null ? CommissionType::FirstPayment : CommissionType::Renewal;
        $rate = $this->rateFor($profile, $client->role, $type);
        $amount = (int) round($subscription->amount * $rate / 100);

        if ($amount <= 0) {
            return null;
        }

        $commission = Commission::create([
            'officer_id' => $officer->id,
            'officer_name' => $officer->name,
            'client_id' => $client->id,
            'client_name' => $client->nameForMarketing(),
            'client_role' => $client->role,
            'subscription_id' => $subscription->id,
            'type' => $type,
            'status' => CommissionStatus::Pending,
            'base_amount' => $subscription->amount,
            'rate_percent' => $rate,
            'amount' => $amount,
            'available_at' => now()->addDays($this->settings->holdDays()),
        ]);

        BestEffortNotifier::send($officer, new CommissionEarned($commission));

        return $commission;
    }

    /**
     * The percentage an officer earns on a kind of payment. Rates agreed with an officer alone replace the standard rates
     * for physicians and technicians; patients always use the standard rate.
     */
    public function rateFor(MarketingProfile $profile, Role $clientRole, CommissionType $type): float
    {
        $customRate = match ($type) {
            CommissionType::FirstPayment => $profile->first_payment_rate,
            CommissionType::Renewal => $profile->renewal_rate,
            default => null,
        };

        return $clientRole !== Role::Patient && $customRate !== null
            ? $customRate
            : $this->settings->rateFor($clientRole, $type);
    }

    /**
     * Add a one-off bonus. It has no hold, because it does not depend on a client's payment.
     */
    public function addBonus(User $officer, int $amount, string $reason, User $admin): Commission
    {
        return Commission::create([
            'officer_id' => $officer->id,
            'officer_name' => $officer->name,
            'type' => CommissionType::Bonus,
            'status' => CommissionStatus::Pending,
            'amount' => $amount,
            'note' => $reason,
            'created_by' => $admin->id,
            'available_at' => now(),
        ]);
    }

    /**
     * Cancel a line. If it was already paid, the amount comes off the officer's next payout instead.
     *
     * @throws InvalidArgumentException
     */
    public function reverse(Commission $commission, User $admin, string $reason): void
    {
        DB::transaction(function () use ($commission, $admin, $reason): void {
            $commission = Commission::query()->lockForUpdate()->findOrFail($commission->id);

            if (! $commission->isReversible()) {
                throw new InvalidArgumentException('This line cannot be reversed.');
            }

            $wasPaid = $commission->status === CommissionStatus::Paid;

            if ($wasPaid && $commission->officer_id === null) {
                throw new InvalidArgumentException('This line was paid to an officer whose account has been deleted, so nothing can be taken back.');
            }

            $commission->forceFill([
                'status' => $wasPaid ? CommissionStatus::Paid : CommissionStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $admin->id,
                'reversal_reason' => $reason,
            ])->save();

            if ($wasPaid) {
                Commission::create([
                    'officer_id' => $commission->officer_id,
                    'officer_name' => $commission->officer_name,
                    'client_id' => $commission->client_id,
                    'client_name' => $commission->client_name,
                    'client_role' => $commission->client_role,
                    'reverses_id' => $commission->id,
                    'type' => CommissionType::Adjustment,
                    'status' => CommissionStatus::Pending,
                    'amount' => -$commission->amount,
                    'note' => $reason,
                    'created_by' => $admin->id,
                    'available_at' => now(),
                ]);
            }
        });
    }

    /**
     * Record that an officer was paid everything payable to them now.
     *
     * @throws InvalidArgumentException
     */
    public function pay(User $officer, string $paymentMethod, ?string $paymentReference, ?string $notes, User $admin): CommissionPayout
    {
        $payout = DB::transaction(function () use ($officer, $paymentMethod, $paymentReference, $notes, $admin): CommissionPayout {
            $lines = $officer->commissions()->payable()->lockForUpdate()->get();
            $total = (int) $lines->sum('amount');

            if ($total <= 0) {
                throw new InvalidArgumentException("Nothing is payable to {$officer->name} right now.");
            }

            $payout = CommissionPayout::create([
                'officer_id' => $officer->id,
                'officer_name' => $officer->name,
                'amount' => $total,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'notes' => $notes,
                'paid_by' => $admin->id,
                'paid_at' => now(),
            ]);

            Commission::query()->whereKey($lines->modelKeys())->update([
                'status' => CommissionStatus::Paid,
                'payout_id' => $payout->id,
            ]);

            return $payout;
        });

        BestEffortNotifier::send($officer, new CommissionPaidOut($payout));

        return $payout;
    }

    /**
     * An officer's money by stage. What is payable can be negative when a paid commission was reversed.
     *
     * @return array{onHold: int, payable: int, paid: int}
     */
    public function totalsFor(User $officer): array
    {
        return [
            'onHold' => (int) $officer->commissions()->onHold()->sum('amount'),
            'payable' => (int) $officer->commissions()->payable()->sum('amount'),
            'paid' => (int) $officer->commissionPayouts()->sum('amount'),
        ];
    }
}
