<?php

namespace App\Enums;

enum ShareOutcome: string
{
    /** The physician can open the study now. */
    case Shared = 'shared';

    /** The physician has not accepted a share from this sender before, so they must accept it before they can open it. */
    case AwaitingAcceptance = 'awaiting_acceptance';

    /** The physician has blocked or suspended the sender, so nothing was shared. The sender is not told why. */
    case NotDelivered = 'not_delivered';
}
