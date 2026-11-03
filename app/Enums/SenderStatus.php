<?php

namespace App\Enums;

/**
 * What a physician has decided about someone who shares studies with them. A sender without a decision is new: their
 * next share waits for the physician to accept it.
 */
enum SenderStatus: string
{
    /** Shares arrive straight away. */
    case Allowed = 'allowed';

    /** New shares are not delivered; studies shared earlier stay. */
    case Suspended = 'suspended';

    /** New shares are not delivered, and the studies they shared were removed when they were blocked. */
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Allowed => 'Allowed',
            self::Suspended => 'Suspended',
            self::Blocked => 'Blocked',
        };
    }

    /**
     * Whether new shares from a sender with this status reach the physician.
     */
    public function receivesShares(): bool
    {
        return $this === self::Allowed;
    }
}
