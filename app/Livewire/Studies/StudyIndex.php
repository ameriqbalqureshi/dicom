<?php

namespace App\Livewire\Studies;

use App\Models\Series;
use App\Models\Study;
use Illuminate\Contracts\View\View;
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
        $studies = Study::query()
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
            'modalities' => Series::query()->whereNotNull('modality')->distinct()->orderBy('modality')->pluck('modality'),
        ]);
    }
}
