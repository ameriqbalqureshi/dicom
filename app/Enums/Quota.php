<?php

namespace App\Enums;

/**
 * The countable things a package limits. Uploads and patient records limit how many the user keeps at a time, whichever
 * package they were created under; the others count what is done during the package and have a usage counter on the
 * subscription.
 */
enum Quota: string
{
    case Uploads = 'uploads';
    case PatientRecords = 'patient_records';
    case Reports = 'reports';
    case Shares = 'shares';
    case GuestLinks = 'guest_links';

    /**
     * The quotas that mean something to users of a role.
     *
     * @return list<self>
     */
    public static function forRole(Role $role): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $quota): bool => $quota !== self::PatientRecords || $role === Role::Physician,
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::Uploads => 'Studies stored',
            self::PatientRecords => 'Patient records',
            self::Reports => 'Reports signed',
            self::Shares => 'Shares',
            self::GuestLinks => 'Guest links',
        };
    }

    /**
     * Whether the quota limits what the user keeps, so deleting frees it and renewing does not reset it.
     */
    public function countsItemsKept(): bool
    {
        return in_array($this, [self::Uploads, self::PatientRecords], true);
    }

    /**
     * What one of the kept items is called, for quotas that limit what the user keeps.
     */
    public function keptItemName(): string
    {
        return $this === self::PatientRecords ? 'patient record' : 'study';
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
