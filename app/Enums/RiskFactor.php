<?php

namespace App\Enums;

/**
 * The cardiac risk factors a physician can tick on a patient record.
 */
enum RiskFactor: string
{
    case Diabetes = 'diabetes';
    case Hypertension = 'hypertension';
    case Smoking = 'smoking';
    case Dyslipidemia = 'dyslipidemia';
    case FamilyHistory = 'family_history';

    public function label(): string
    {
        return match ($this) {
            self::Diabetes => 'Diabetes',
            self::Hypertension => 'Hypertension',
            self::Smoking => 'Smoking',
            self::Dyslipidemia => 'Dyslipidemia',
            self::FamilyHistory => 'Family history of heart disease',
        };
    }
}
