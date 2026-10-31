<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\ShareOutcome;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Shares studies with physicians. Physicians can forward studies shared with them, so the shares of a study form a
 * tree rooted at its owner: each physician holds at most one share, and revoking a share revokes everything forwarded
 * from it.
 */
class StudySharing
{
    /**
     * Share a study with a physician. Shares from patients wait for the physician to accept them.
     *
     * @throws InvalidArgumentException
     */
    public function share(Study $study, User $sharer, User $recipient): ShareOutcome
    {
        if (! $recipient->isPhysician()) {
            throw new InvalidArgumentException('Studies can only be shared with physicians.');
        }

        if ($study->uploaded_by === $recipient->id || $study->shares()->whereBelongsTo($recipient, 'recipient')->exists()) {
            throw new InvalidArgumentException("{$recipient->name} already has access to this study.");
        }

        $share = $study->shares()->create([
            'user_id' => $recipient->id,
            'shared_by' => $sharer->id,
            'accepted_at' => $sharer->role === Role::Patient ? null : now(),
        ]);

        return $share->isAccepted() ? ShareOutcome::Shared : ShareOutcome::AwaitingAcceptance;
    }

    public function accept(StudyShare $share): void
    {
        $share->update(['accepted_at' => now()]);
    }

    /**
     * Revoke (or decline) a share along with every share forwarded from it, however far down the chain.
     */
    public function revoke(StudyShare $share): void
    {
        DB::transaction(function () use ($share): void {
            $shareIds = [$share->id];
            $recipientIds = [$share->user_id];

            while ($recipientIds !== []) {
                $forwarded = StudyShare::query()
                    ->where('study_id', $share->study_id)
                    ->whereIn('shared_by', $recipientIds)
                    ->whereKeyNot($shareIds)
                    ->get(['id', 'user_id']);

                $shareIds = [...$shareIds, ...$forwarded->modelKeys()];
                $recipientIds = $forwarded->pluck('user_id')->all();
            }

            StudyShare::whereKey($shareIds)->delete();
        });
    }

    /**
     * Revoke every share the user has received, along with everything they forwarded.
     */
    public function revokeReceivedBy(User $user): void
    {
        $user->receivedShares()->get()->each(fn (StudyShare $share) => $this->revoke($share));
    }

    /**
     * Revoke every share the user has received or made, along with everything forwarded from them, before the user is
     * removed.
     */
    public function revokeInvolving(User $user): void
    {
        $this->revokeReceivedBy($user);

        $user->sharesMade()->get()->each(fn (StudyShare $share) => $this->revoke($share));
    }
}
