<?php

namespace App\Policies;

use App\Models\StudyShare;
use App\Models\User;

class StudySharePolicy
{
    /**
     * Determine whether the user can accept or decline a share: only its recipient, while it is pending.
     */
    public function respond(User $user, StudyShare $share): bool
    {
        return $share->user_id === $user->id && ! $share->isAccepted();
    }

    /**
     * Determine whether the user can revoke a share: whoever made it, or the study's owner.
     */
    public function delete(User $user, StudyShare $share): bool
    {
        return $share->shared_by === $user->id || $share->study->uploaded_by === $user->id;
    }
}
