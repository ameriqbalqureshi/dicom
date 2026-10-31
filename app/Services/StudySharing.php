<?php

namespace App\Services;

use App\Enums\Quota;
use App\Enums\Role;
use App\Enums\ShareOutcome;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use App\Notifications\ShareAccepted;
use App\Notifications\StudySharedWithYou;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\QuotaExceededException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Shares studies with physicians. Physicians can forward studies shared with them, so the shares of a study form a
 * tree rooted at its owner: each physician holds at most one share, and revoking a share revokes everything forwarded
 * from it.
 */
class StudySharing
{
    public function __construct(private PackageLimits $limits) {}

    /**
     * Share a study with a verified physician, counting it against the sharer's package. Shares from patients wait for
     * the physician to accept them.
     *
     * @throws InvalidArgumentException
     * @throws QuotaExceededException
     */
    public function share(Study $study, User $sharer, User $recipient): ShareOutcome
    {
        if (! $recipient->isVerifiedPhysician()) {
            throw new InvalidArgumentException('Studies can only be shared with verified physicians.');
        }

        if ($study->uploaded_by === $recipient->id || $study->shares()->whereBelongsTo($recipient, 'recipient')->exists()) {
            throw new InvalidArgumentException("{$recipient->name} already has access to this study.");
        }

        $share = DB::transaction(function () use ($study, $sharer, $recipient): StudyShare {
            $this->limits->spend($sharer, Quota::Shares);

            return $study->shares()->create([
                'user_id' => $recipient->id,
                'shared_by' => $sharer->id,
                'accepted_at' => $sharer->role === Role::Patient ? null : now(),
            ]);
        });

        $recipient->notify(new StudySharedWithYou($study, $sharer, awaitingAcceptance: ! $share->isAccepted()));

        return $share->isAccepted() ? ShareOutcome::Shared : ShareOutcome::AwaitingAcceptance;
    }

    /**
     * Accept a share and let the person who sent it know.
     */
    public function accept(StudyShare $share): void
    {
        $share->update(['accepted_at' => now()]);

        $share->sharer?->notify(new ShareAccepted($share->study, $share->recipient));
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
