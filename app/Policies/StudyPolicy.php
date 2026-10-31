<?php

namespace App\Policies;

use App\Models\Study;
use App\Models\User;

class StudyPolicy
{
    /**
     * Determine whether the user can browse the study list.
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
        return true;
    }

    /**
     * Determine whether the user can upload DICOM files.
     */
    public function create(User $user): bool
    {
        return $user->role->canUploadStudies();
    }

    /**
     * Determine whether the user can delete a study and its files.
     */
    public function delete(User $user, Study $study): bool
    {
        return $user->isAdmin();
    }
}
