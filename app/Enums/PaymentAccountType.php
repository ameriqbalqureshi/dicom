<?php

namespace App\Enums;

enum PaymentAccountType: string
{
    case Bank = 'bank';

    /** A mobile account paid by phone number, such as JazzCash or Easypaisa. */
    case MobileWallet = 'mobile_wallet';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank account',
            self::MobileWallet => 'Mobile wallet',
        };
    }

    public function numberLabel(): string
    {
        return match ($this) {
            self::Bank => 'Account number',
            self::MobileWallet => 'Mobile number',
        };
    }
}
