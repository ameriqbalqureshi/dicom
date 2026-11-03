<?php

namespace App\Services;

use App\Enums\Quota;
use App\Enums\SenderStatus;
use App\Enums\ShareOutcome;
use App\Models\SenderPermission;
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
 * from it. Physicians decide who may share with them: the first share from a sender waits for acceptance, and blocked or
 * suspended senders' shares are not delivered.
 */
class StudySharing
{
    public function __construct(private PackageLimits $limits) {}

    /**
     * Share a study with a verified physician, counting it against the sharer's package. Shares from a sender the
     * physician has not allowed yet wait for them to accept; shares from a sender they blocked or suspended are not
     * delivered, cost nothing, and the sender is not told why.
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

        $senderStatus = SenderPermission::statusOf($recipient, $sharer);

        if ($senderStatus !== null && ! $senderStatus->receivesShares()) {
            return ShareOutcome::NotDelivered;
        }

        $share = DB::transaction(function () use ($study, $sharer, $recipient, $senderStatus): StudyShare {
            $this->limits->spend($sharer, Quota::Shares);

            return $study->shares()->create([
                'user_id' => $recipient->id,
                'shared_by' => $sharer->id,
                'accepted_at' => $senderStatus === SenderStatus::Allowed ? now() : null,
            ]);
        });

        $recipient->notify(new StudySharedWithYou($study, $sharer, awaitingAcceptance: ! $share->isAccepted()));

        return $share->isAccepted() ? ShareOutcome::Shared : ShareOutcome::AwaitingAcceptance;
    }

    /**
     * Accept a share and let the person who sent it know. Accepting a new sender's share allows them, so their later
     * shares arrive straight away.
     */
    public function accept(StudyShare $share): void
    {
        DB::transaction(function () use ($share): void {
            $share->update(['accepted_at' => now()]);

            if ($share->shared_by !== null) {
                SenderPermission::firstOrCreate(
                    ['recipient_id' => $share->user_id, 'sender_id' => $share->shared_by],
                    ['status' => SenderStatus::Allowed],
                );
            }
        });

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
     * Remove a study shared with the user from their list once they no longer need it. Only their own access ends:
     * whoever they forwarded it to keeps it, since those physicians may still be caring for the patient.
     */
    public function removeFromList(Study $study, User $recipient): void
    {
        $study->shares()->whereBelongsTo($recipient, 'recipient')->delete();
    }

    /**
     * Stop delivering the sender's shares to the physician and remove the studies the sender shared with them, pending
     * or accepted. Only the physician's own access ends: colleagues they forwarded those studies to keep them.
     */
    public function block(User $recipient, User $sender): void
    {
        DB::transaction(function () use ($recipient, $sender): void {
            $this->setSenderStatus($recipient, $sender, SenderStatus::Blocked);

            $recipient->receivedShares()->whereBelongsTo($sender, 'sharer')->delete();
        });
    }

    /**
     * Stop delivering the sender's shares to the physician, keeping the studies they already shared.
     */
    public function suspend(User $recipient, User $sender): void
    {
        $this->setSenderStatus($recipient, $sender, SenderStatus::Suspended);
    }

    /**
     * Deliver a suspended sender's shares straight away again.
     */
    public function resume(User $recipient, User $sender): void
    {
        $this->setSenderStatus($recipient, $sender, SenderStatus::Allowed);
    }

    /**
     * Treat a blocked sender as new again, so their next share waits for the physician to accept it.
     */
    public function unblock(User $recipient, User $sender): void
    {
        SenderPermission::query()->whereBelongsTo($recipient, 'recipient')->whereBelongsTo($sender, 'sender')->delete();
    }

    private function setSenderStatus(User $recipient, User $sender, SenderStatus $status): void
    {
        SenderPermission::updateOrCreate(
            ['recipient_id' => $recipient->id, 'sender_id' => $sender->id],
            ['status' => $status],
        );
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
