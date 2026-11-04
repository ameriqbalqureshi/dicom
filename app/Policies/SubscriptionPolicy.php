<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    /**
     * The buyer and administrators can see the receipt for a confirmed payment.
     */
    public function viewReceipt(User $user, Subscription $subscription): bool
    {
        return $subscription->isPaymentConfirmed()
            && ($user->isAdmin() || $subscription->user_id === $user->id);
    }
}
