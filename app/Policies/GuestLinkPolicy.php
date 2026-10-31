<?php

namespace App\Policies;

use App\Models\GuestLink;
use App\Models\User;

class GuestLinkPolicy
{
    /**
     * Determine whether the user can revoke a guest link: whoever created it, or the study's owner.
     */
    public function delete(User $user, GuestLink $guestLink): bool
    {
        return $guestLink->created_by === $user->id || $guestLink->study->uploaded_by === $user->id;
    }
}
