<?php

namespace App\Livewire\Studies;

use App\Models\Series;
use App\Models\Study;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Studies')]
class StudyIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $modality = '';

    /**
     * The user whose shared studies are listed, or null to list the current user's own studies.
     */
    #[Url(except: null)]
    public ?int $from = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Study::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedModality(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();
        $sharers = $this->sharers($user);
        $sharer = $this->from === null ? null : $sharers->firstWhere('id', $this->from);

        $studies = $this->listedStudies($user, $sharer)
            ->unexpired()
            ->search($this->search)
            ->when($this->modality !== '', fn ($query) => $query->whereHas(
                'series',
                fn ($query) => $query->where('modality', $this->modality),
            ))
            ->with('series:id,study_id,modality')
            ->withCount(['series', 'instances'])
            ->orderByDesc('study_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.studies.study-index', [
            'studies' => $studies,
            'sharers' => $sharers,
            'sharer' => $sharer,
            'pendingShareCount' => $user->receivedShares()->pending()->count(),
            'modalities' => Series::query()
                ->whereHas('study', fn ($query) => $query->visibleTo($user))
                ->whereNotNull('modality')->distinct()->orderBy('modality')->pluck('modality'),
        ]);
    }

    /**
     * The studies on the selected tab: those shared by the selected person, otherwise the user's own
     * (every study for administrators). An unknown `from`, or someone who shares nothing with the user, lists nothing.
     *
     * @return Builder<Study>
     */
    private function listedStudies(User $user, ?User $sharer): Builder
    {
        if ($this->from !== null) {
            return $sharer === null ? Study::query()->whereKey([]) : Study::query()->sharedWith($user, $sharer);
        }

        return $user->isAdmin() ? Study::query() : Study::query()->whereBelongsTo($user, 'uploader');
    }

    /**
     * People who have shared or forwarded at least one study with the user that they can open, with how many.
     *
     * @return Collection<int, User>
     */
    private function sharers(User $user): Collection
    {
        $acceptedByUser = fn ($query) => $query->accepted()->whereBelongsTo($user, 'recipient');

        return User::query()
            ->select(['id', 'name'])
            ->whereHas('sharesMade', $acceptedByUser)
            ->withCount(['sharesMade as shared_studies_count' => $acceptedByUser])
            ->orderBy('name')
            ->get();
    }
}
