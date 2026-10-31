<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Technician = 'technician';
    case Physician = 'physician';
    case Patient = 'patient';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Technician => 'Technician',
            self::Physician => 'Physician',
            self::Patient => 'Patient',
        };
    }
}
