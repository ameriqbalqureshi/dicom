<?php

namespace App\Enums;

enum Sex: string
{
    case Male = 'M';
    case Female = 'F';
    case Other = 'O';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Other => 'Other',
        };
    }
}
