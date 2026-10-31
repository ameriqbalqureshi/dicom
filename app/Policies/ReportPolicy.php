<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\Study;
use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the user can read a report: a final report by anyone who can open the study, a draft only by
     * its author.
     */
    public function view(User $user, Report $report): bool
    {
        if (! $user->can('view', $report->study)) {
            return false;
        }

        return $report->isFinal() || $this->isAuthor($user, $report);
    }

    /**
     * Determine whether the user can write a report on a study: any physician who can open it.
     */
    public function create(User $user, Study $study): bool
    {
        return $user->isPhysician() && $user->can('view', $study);
    }

    /**
     * Determine whether the user can edit, finalize or discard a report: its author, while it is a draft.
     */
    public function update(User $user, Report $report): bool
    {
        return ! $report->isFinal() && $this->isAuthor($user, $report) && $this->create($user, $report->study);
    }

    /**
     * Determine whether the user can sign reports at all: only verified physicians.
     */
    public function sign(User $user): bool
    {
        return $user->isVerifiedPhysician();
    }

    /**
     * Determine whether the user can finalize (sign) a draft: its author, once verified.
     */
    public function finalize(User $user, Report $report): bool
    {
        return $this->update($user, $report) && $this->sign($user);
    }

    /**
     * Determine whether the user can discard a draft report.
     */
    public function delete(User $user, Report $report): bool
    {
        return $this->update($user, $report);
    }

    /**
     * Determine whether the user can add an addendum to a report: its author, once it is final.
     */
    public function addAddendum(User $user, Report $report): bool
    {
        return $report->isFinal() && $this->isAuthor($user, $report) && $this->create($user, $report->study);
    }

    private function isAuthor(User $user, Report $report): bool
    {
        return $report->author_id === $user->id;
    }
}
