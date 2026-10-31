<?php

namespace App\Services\Dicom;

use App\Models\DicomInstance;
use App\Models\Series;
use App\Models\Study;
use Closure;
use Illuminate\Support\Collection;

/**
 * Describes a study as browser viewer entries, each an ordered list of image frames.
 */
class ViewerSeries
{
    /**
     * The single-frame images of a series form one stack (e.g. CT slices), while every multi-frame image is its own
     * entry, because it is a separate recording such as an angiography cine run.
     *
     * @param  Closure(DicomInstance): string  $fileUrl  The URL the viewer downloads an instance's file from.
     * @return list<array{id: string, label: string, isRun: bool, slices: list<array{url: string, frame: int, instanceNumber: int|null}>}>
     */
    public static function forStudy(Study $study, Closure $fileUrl): array
    {
        return $study->series()
            ->with(['instances' => fn ($query) => $query->orderBy('instance_number')->orderBy('id')])
            ->orderBy('series_number')
            ->orderBy('id')
            ->get()
            ->flatMap(function (Series $item) use ($fileUrl): array {
                $label = trim(($item->modality ?? '').' '.($item->series_description ?? 'Series '.$item->series_number));
                [$runs, $stack] = $item->instances->partition(fn (DicomInstance $instance): bool => $instance->number_of_frames > 1);
                $entries = [];

                if ($stack->isNotEmpty()) {
                    $entries[] = ['id' => "series-{$item->id}", 'label' => $label, 'isRun' => false, 'slices' => self::slices($stack, $fileUrl)];
                }

                foreach ($runs->values() as $index => $instance) {
                    $entries[] = [
                        'id' => "instance-{$instance->id}",
                        'label' => "{$label} · Run ".($instance->instance_number ?? $index + 1),
                        'isRun' => true,
                        'slices' => self::slices(collect([$instance]), $fileUrl),
                    ];
                }

                return $entries;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, DicomInstance>  $instances
     * @param  Closure(DicomInstance): string  $fileUrl
     * @return list<array{url: string, frame: int, instanceNumber: int|null}>
     */
    private static function slices(Collection $instances, Closure $fileUrl): array
    {
        return $instances->flatMap(function (DicomInstance $instance) use ($fileUrl): array {
            $url = $fileUrl($instance);

            return array_map(
                fn (int $frame): array => ['url' => $url, 'frame' => $frame, 'instanceNumber' => $instance->instance_number],
                range(0, $instance->number_of_frames - 1),
            );
        })->values()->all();
    }
}
