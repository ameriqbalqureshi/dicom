<?php

namespace App\Policies;

use App\Models\CommissionPayout;
use App\Models\User;

class CommissionPayoutPolicy
{
    /**
     * The officer who was paid and administrators can see a payout's statement.
     */
    public function view(User $user, CommissionPayout $commissionPayout): bool
    {
        return $user->isAdmin() || $commissionPayout->officer_id === $user->id;
    }
}
