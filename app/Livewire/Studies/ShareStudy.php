<?php

namespace App\Livewire\Studies;

use App\Enums\ShareOutcome;
use App\Models\Study;
use App\Models\User;
use App\Services\Billing\QuotaExceededException;
use App\Services\StudySharing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Lets a study's owner, or a physician it was shared with, find physicians by name or email and share the study with
 * them. The owner sees and can revoke every share of the study; a forwarding physician only the shares they made.
 */
class ShareStudy extends Component
{
    public const MIN_SEARCH_LENGTH = 3;

    public const MAX_RESULTS = 8;

    public Study $study;

    public string $search = '';

    public ?string $status = null;

    public function mount(Study $study): void
    {
        $this->authorize('share', $study);

        $this->study = $study;
    }

    public function share(int $userId, StudySharing $sharing): void
    {
        $this->authorize('share', $this->study);

        $recipient = User::findOrFail($userId);

        try {
            $outcome = $sharing->share($this->study, auth()->user(), $recipient);
        } catch (InvalidArgumentException|QuotaExceededException $exception) {
            $this->addError('search', $exception->getMessage());

            return;
        }

        $this->status = match ($outcome) {
            ShareOutcome::Shared => "Shared with {$recipient->name}.",
            ShareOutcome::AwaitingAcceptance => "Sent to {$recipient->name}. They can open the study once they accept it.",
        };

        $this->reset('search');
    }

    public function revoke(int $shareId, StudySharing $sharing): void
    {
        $share = $this->study->shares()->findOrFail($shareId);
        $this->authorize('delete', $share);

        $sharing->revoke($share);

        $this->status = "{$share->recipient->name} can no longer open this study.";
    }

    public function render(): View
    {
        $user = auth()->user();
        $isOwner = $this->study->uploader()->is($user);

        return view('livewire.studies.share-study', [
            'isOwner' => $isOwner,
            'shares' => $this->study->shares()
                ->unless($isOwner, fn ($query) => $query->whereBelongsTo($user, 'sharer'))
                ->with('recipient:id,name,email', 'sharer:id,name')
                ->get()
                ->sortBy('recipient.name'),
            'matches' => $this->matches($user),
            'idsWithAccess' => $this->study->shares()->pluck('user_id')->push($this->study->uploaded_by)->all(),
        ]);
    }

    /**
     * Verified physicians other than the current user whose name or email contains the search term.
     *
     * @return Collection<int, User>
     */
    private function matches(User $user): Collection
    {
        $term = trim($this->search);

        if (mb_strlen($term) < self::MIN_SEARCH_LENGTH) {
            return collect();
        }

        $like = '%'.$term.'%';

        return User::query()
            ->whereKeyNot($user->id)
            ->verifiedPhysicians()
            ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')
            ->limit(self::MAX_RESULTS)
            ->get(['id', 'name', 'email']);
    }
}
