<?php

namespace App\Enums;

/**
 * The countable things a package limits. Uploads limit how many studies the user keeps at a time, whichever package
 * they were uploaded under; the others count what is done during the package and have a usage counter on the
 * subscription.
 */
enum Quota: string
{
    case Uploads = 'uploads';
    case Reports = 'reports';
    case Shares = 'shares';
    case GuestLinks = 'guest_links';

    public function label(): string
    {
        return match ($this) {
            self::Uploads => 'Studies stored',
            self::Reports => 'Reports signed',
            self::Shares => 'Shares',
            self::GuestLinks => 'Guest links',
        };
    }

    /**
     * Whether the quota limits the studies the user keeps, so deleting a study frees it and renewing does not reset it.
     */
    public function countsStudiesKept(): bool
    {
        return $this === self::Uploads;
    }

    public function limitColumn(): string
    {
        return "max_{$this->value}";
    }

    public function usageColumn(): string
    {
        return "{$this->value}_used";
    }
}
