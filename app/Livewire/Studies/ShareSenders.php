<?php

namespace App\Livewire\Studies;

use App\Enums\SenderStatus;
use App\Models\SenderPermission;
use App\Models\User;
use App\Services\StudySharing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lists everyone who shares studies with the physician and lets them suspend, resume, block or unblock each sender.
 * Senders are never told about these decisions; their shares are simply not delivered.
 */
#[Title('Senders')]
class ShareSenders extends Component
{
    public ?string $status = null;

    public function suspend(int $senderId, StudySharing $sharing): void
    {
        $sender = $this->findSender($senderId);

        $sharing->suspend(auth()->user(), $sender);

        $this->status = "Suspended {$sender->name}. New shares from them will not reach you; studies they already shared stay in your list.";
    }

    public function resume(int $senderId, StudySharing $sharing): void
    {
        $sender = $this->findSender($senderId, SenderStatus::Suspended);

        $sharing->resume(auth()->user(), $sender);

        $this->status = "Resumed {$sender->name}. Their shares reach you straight away again.";
    }

    public function block(int $senderId, StudySharing $sharing): void
    {
        $sender = $this->findSender($senderId);

        $sharing->block(auth()->user(), $sender);

        $this->status = "Blocked {$sender->name}. Their shares will not reach you, and the studies they shared with you were removed.";
    }

    public function unblock(int $senderId, StudySharing $sharing): void
    {
        $sender = $this->findSender($senderId, SenderStatus::Blocked);

        $sharing->unblock(auth()->user(), $sender);

        $this->status = "Unblocked {$sender->name}. Their next share will wait for you to accept it.";
    }

    /**
     * Find someone who has shared with the physician or has a status with them, optionally requiring that status.
     */
    private function findSender(int $senderId, ?SenderStatus $requiredStatus = null): User
    {
        $row = $this->senderRows()->firstWhere('sender.id', $senderId);

        abort_if($row === null || ($requiredStatus !== null && $row['status'] !== $requiredStatus), 404);

        return $row['sender'];
    }

    /**
     * Everyone who has shared with the physician or has a status with them, with their status and the number of studies
     * they currently share with the physician.
     *
     * @return Collection<int, array{sender: User, status: ?SenderStatus, studies: int, pending: int}>
     */
    private function senderRows(): Collection
    {
        $user = auth()->user();

        $statuses = SenderPermission::query()->whereBelongsTo($user, 'recipient')->pluck('status', 'sender_id');

        $shareCounts = $user->receivedShares()
            ->whereNotNull('shared_by')
            ->groupBy('shared_by')
            ->selectRaw('shared_by, count(*) as studies, sum(case when accepted_at is null then 1 else 0 end) as pending')
            ->get()
            ->keyBy('shared_by');

        return User::query()
            ->whereKey($statuses->keys()->merge($shareCounts->keys())->unique()->all())
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $sender): array => [
                'sender' => $sender,
                'status' => $statuses->get($sender->id),
                'studies' => (int) $shareCounts->get($sender->id)?->studies,
                'pending' => (int) $shareCounts->get($sender->id)?->pending,
            ]);
    }

    public function render(): View
    {
        return view('livewire.studies.share-senders', [
            'rows' => $this->senderRows(),
        ]);
    }
}
