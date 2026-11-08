<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Study;
use App\Models\User;

class StudyPolicy
{
    /**
     * Determine whether the user can browse the study list (which only lists studies they may view). Marketing officers
     * have nothing to do with studies.
     */
    public function viewAny(User $user): bool
    {
        return ! $user->isMarketingOfficer();
    }

    /**
     * Determine whether the user can open a study and its images. Expired studies cannot be opened.
     */
    public function view(User $user, Study $study): bool
    {
        if ($study->isExpired()) {
            return false;
        }

        return $this->ownsOrAdministers($user, $study) || $this->isSharedWith($user, $study);
    }

    /**
     * Determine whether the user can upload DICOM files.
     */
    public function create(User $user): bool
    {
        return ! $user->isMarketingOfficer();
    }

    /**
     * Determine whether the user can delete a study and its files.
     */
    public function delete(User $user, Study $study): bool
    {
        return $this->ownsOrAdministers($user, $study);
    }

    /**
     * Determine whether the user can share a study with physicians: its owner, or a physician it was shared with,
     * who may forward it.
     */
    public function share(User $user, Study $study): bool
    {
        if ($study->isExpired() || $study->isAwaitingHandover()) {
            return false;
        }

        return $study->uploader()->is($user) || ($user->isPhysician() && $this->isSharedWith($user, $study));
    }

    /**
     * Determine whether the user can remove a study shared with them from their list, giving up their access to it.
     */
    public function removeFromList(User $user, Study $study): bool
    {
        return ! $study->uploader()->is($user) && $this->isSharedWith($user, $study);
    }

    /**
     * Determine whether the user can keep a study its owner gave up, becoming its owner.
     */
    public function keep(User $user, Study $study): bool
    {
        return $study->isAwaitingHandover() && $this->isSharedWith($user, $study);
    }

    /**
     * Determine whether the user can create guest links to a study: a physician for any study they can open, or a
     * patient for their own studies.
     */
    public function createGuestLink(User $user, Study $study): bool
    {
        if ($study->isExpired() || $study->isAwaitingHandover()) {
            return false;
        }

        return match ($user->role) {
            Role::Physician => $study->uploader()->is($user) || $this->isSharedWith($user, $study),
            Role::Patient => $study->uploader()->is($user),
            default => false,
        };
    }

    /**
     * Determine whether the user can see and revoke a study's guest links: anyone who can create them (their own
     * links), and the study's owner (every link).
     */
    public function manageGuestLinks(User $user, Study $study): bool
    {
        return $study->uploader()->is($user) || $this->createGuestLink($user, $study);
    }

    private function ownsOrAdministers(User $user, Study $study): bool
    {
        return $user->isAdmin() || $study->uploader()->is($user);
    }

    private function isSharedWith(User $user, Study $study): bool
    {
        return $study->shares()->accepted()->whereBelongsTo($user, 'recipient')->exists();
    }
}
