<?php

namespace App\Livewire\Studies;

use App\Models\DicomInstance;
use App\Models\Series;
use App\Models\Study;
use App\Services\Dicom\DicomStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class StudyShow extends Component
{
    public Study $study;

    public function mount(Study $study): void
    {
        $this->authorize('view', $study);

        $this->study = $study;
    }

    public function delete(DicomStorage $storage): void
    {
        $this->authorize('delete', $this->study);

        $paths = $this->study->instances()->pluck('file_path')->all();

        $this->study->delete();
        $storage->delete($paths);

        session()->flash('status', 'Study deleted.');

        $this->redirectRoute('studies.index');
    }

    public function render(): View
    {
        $series = $this->study->series()
            ->with(['instances' => fn ($query) => $query->orderBy('instance_number')->orderBy('id')])
            ->orderBy('series_number')
            ->orderBy('id')
            ->get();

        return view('livewire.studies.study-show', [
            'series' => $series,
            'viewerSeries' => $this->viewerSeries($series),
        ])->title($this->study->patient_name ?? 'Study');
    }

    /**
     * Describe each series as an ordered list of image frames for the browser viewer.
     *
     * @param  Collection<int, Series>  $series
     * @return list<array{id: int, label: string, slices: list<array{url: string, frame: int, instanceNumber: int|null}>}>
     */
    private function viewerSeries(Collection $series): array
    {
        return $series->map(fn (Series $item): array => [
            'id' => $item->id,
            'label' => trim(($item->modality ?? '').' '.($item->series_description ?? 'Series '.$item->series_number)),
            'slices' => $item->instances->flatMap(fn (DicomInstance $instance): array => array_map(
                fn (int $frame): array => [
                    'url' => route('instances.file', $instance),
                    'frame' => $frame,
                    'instanceNumber' => $instance->instance_number,
                ],
                range(0, $instance->number_of_frames - 1),
            ))->values()->all(),
        ])->values()->all();
    }
}
