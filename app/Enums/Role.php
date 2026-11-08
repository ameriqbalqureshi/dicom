<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Technician = 'technician';
    case Physician = 'physician';
    case Patient = 'patient';
    case MarketingOfficer = 'marketing_officer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Technician => 'Technician',
            self::Physician => 'Physician',
            self::Patient => 'Patient',
            self::MarketingOfficer => 'Marketing Officer',
        };
    }
}
