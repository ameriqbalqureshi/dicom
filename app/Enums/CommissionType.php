<?php

namespace App\Enums;

enum CommissionType: string
{
    /** Earned on the first payment a client makes. */
    case FirstPayment = 'first_payment';

    /** Earned on a client's later payments, for a limited time. */
    case Renewal = 'renewal';

    /** Added by an administrator, with a reason. */
    case Bonus = 'bonus';

    /** Takes back a commission that was reversed after it had been paid. */
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::FirstPayment => 'First payment',
            self::Renewal => 'Renewal',
            self::Bonus => 'Bonus',
            self::Adjustment => 'Adjustment',
        };
    }
}
