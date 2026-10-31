<?php

namespace App\Enums;

enum ShareOutcome: string
{
    /** The physician can open the study now. */
    case Shared = 'shared';

    /** The study was shared by a patient, so the physician must accept it before they can open it. */
    case AwaitingAcceptance = 'awaiting_acceptance';
}
