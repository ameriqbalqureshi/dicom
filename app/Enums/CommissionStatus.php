<?php

namespace App\Enums;

enum CommissionStatus: string
{
    /** Not paid yet. It can be paid once its hold has ended. */
    case Pending = 'pending';

    case Paid = 'paid';

    /** Cancelled by an administrator before it was paid. */
    case Reversed = 'reversed';
}
