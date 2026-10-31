<?php

namespace App\Enums;

/**
 * The countable actions a package limits. Each has a limit and a usage counter on the subscription.
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
            self::Uploads => 'Study uploads',
            self::Reports => 'Reports signed',
            self::Shares => 'Shares',
            self::GuestLinks => 'Guest links',
        };
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
