<?php

namespace App\Policies;

use App\Models\PatientRecord;
use App\Models\User;

/**
 * Patient records are private to the physician who created them: administrators cannot open them either.
 */
class PatientRecordPolicy
{
    /**
     * Determine whether the user can browse patient records (which only lists their own).
     */
    public function viewAny(User $user): bool
    {
        return $user->isPhysician();
    }

    public function view(User $user, PatientRecord $patientRecord): bool
    {
        return $patientRecord->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isPhysician();
    }

    public function update(User $user, PatientRecord $patientRecord): bool
    {
        return $patientRecord->owner_id === $user->id;
    }

    public function delete(User $user, PatientRecord $patientRecord): bool
    {
        return $patientRecord->owner_id === $user->id;
    }
}
