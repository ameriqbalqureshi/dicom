<?php

namespace App\Policies;

use App\Models\Study;
use App\Models\User;

class StudyPolicy
{
    /**
     * Determine whether the user can browse the study list (which only lists studies they may view).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can open a study and its images.
     */
    public function view(User $user, Study $study): bool
    {
        return $this->ownsOrAdministers($user, $study);
    }

    /**
     * Determine whether the user can upload DICOM files.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete a study and its files.
     */
    public function delete(User $user, Study $study): bool
    {
        return $this->ownsOrAdministers($user, $study);
    }

    private function ownsOrAdministers(User $user, Study $study): bool
    {
        return $user->isAdmin() || $study->uploader()->is($user);
    }
}
