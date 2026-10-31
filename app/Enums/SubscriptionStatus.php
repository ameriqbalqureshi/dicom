<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    /** Paid for, waiting for an administrator to confirm the payment. */
    case Pending = 'pending';

    case Active = 'active';

    /** An administrator could not confirm the payment. */
    case Rejected = 'rejected';

    /** Withdrawn by the user before it was reviewed. */
    case Cancelled = 'cancelled';

    /** Ended early because another package was activated. */
    case Replaced = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment confirmation',
            self::Active => 'Active',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Replaced => 'Replaced',
        };
    }
}
