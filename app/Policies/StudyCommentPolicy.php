<?php

namespace App\Policies;

use App\Models\Study;
use App\Models\StudyComment;
use App\Models\User;

class StudyCommentPolicy
{
    /**
     * Determine whether the user can comment on a study: anyone who can open it.
     */
    public function create(User $user, Study $study): bool
    {
        return $user->can('view', $study);
    }

    /**
     * Determine whether the user can edit a comment: its author, while they can still open the study.
     */
    public function update(User $user, StudyComment $comment): bool
    {
        return $comment->author_id === $user->id && $user->can('view', $comment->study);
    }

    /**
     * Determine whether the user can delete a comment: its author, or the study's owner or an administrator.
     */
    public function delete(User $user, StudyComment $comment): bool
    {
        return $this->update($user, $comment) || $user->can('delete', $comment->study);
    }
}
