<?php

namespace App\Services\Marketing;

use App\Enums\CommissionType;
use App\Enums\Role;
use App\Models\Setting;

/**
 * The commission rules administrators manage. Each commission keeps a copy of the rate it used, so changing these only
 * affects payments confirmed afterwards.
 */
class MarketingSettings
{
    private const KEY = 'marketing.commission_rules';

    /**
     * The rules until administrators change them.
     *
     * @var array{rates: array<string, array{first: float|int, renewal: float|int}>, renewal_months: int, hold_days: int}
     */
    public const DEFAULTS = [
        'rates' => [
            'physician' => ['first' => 20, 'renewal' => 10],
            'technician' => ['first' => 20, 'renewal' => 10],
            'patient' => ['first' => 10, 'renewal' => 0],
        ],
        'renewal_months' => 12,
        'hold_days' => 14,
    ];

    /**
     * @return array{rates: array<string, array{first: float|int, renewal: float|int}>, renewal_months: int, hold_days: int}
     */
    public function all(): array
    {
        $saved = json_decode((string) Setting::find(self::KEY)?->value, true);

        return is_array($saved) ? array_replace_recursive(self::DEFAULTS, $saved) : self::DEFAULTS;
    }

    /**
     * The standard percentage for a kind of payment by a client with the given role.
     */
    public function rateFor(Role $clientRole, CommissionType $type): float
    {
        $rates = $this->all()['rates'][$clientRole->value] ?? null;

        return (float) match ($type) {
            CommissionType::FirstPayment => $rates['first'] ?? 0,
            CommissionType::Renewal => $rates['renewal'] ?? 0,
            default => 0,
        };
    }

    /**
     * For how many months after a client's first payment their renewals earn commission.
     */
    public function renewalMonths(): int
    {
        return $this->all()['renewal_months'];
    }

    /**
     * How many days a commission waits before it can be paid, in case the payment turns out to be a mistake.
     */
    public function holdDays(): int
    {
        return $this->all()['hold_days'];
    }

    /**
     * @param  array{rates: array<string, array{first: float|int, renewal: float|int}>, renewal_months: int, hold_days: int}  $rules
     */
    public function update(array $rules): void
    {
        Setting::updateOrCreate(['key' => self::KEY], ['value' => json_encode($rules)]);
    }
}
