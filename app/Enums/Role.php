<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Radiologist = 'radiologist';
    case Technician = 'technician';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Radiologist => 'Radiologist',
            self::Technician => 'Technician',
        };
    }
}
