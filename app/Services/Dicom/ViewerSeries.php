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
     * entry, because it is a separate recording such as an angiography cine run. For images stored in a zip, archiveEntries
     * tells the viewer, by file URL, which part of the zip to read.
     *
     * @param  Closure(DicomInstance): string  $fileUrl  The URL the viewer downloads an instance's file from.
     * @return list<array{id: string, label: string, isRun: bool, slices: list<array{url: string, frame: int, instanceNumber: int|null}>, archiveEntries?: array<string, array{archive: int, offset: int, length: int, size: int, method: int}>}>
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
                    $entries[] = self::entry("series-{$item->id}", $label, false, $stack, $fileUrl);
                }

                foreach ($runs->values() as $index => $instance) {
                    $entries[] = self::entry(
                        "instance-{$instance->id}",
                        "{$label} · Run ".($instance->instance_number ?? $index + 1),
                        true,
                        collect([$instance]),
                        $fileUrl,
                    );
                }

                return $entries;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, DicomInstance>  $instances
     * @param  Closure(DicomInstance): string  $fileUrl
     * @return array{id: string, label: string, isRun: bool, slices: list<array{url: string, frame: int, instanceNumber: int|null}>, archiveEntries?: array<string, array{archive: int, offset: int, length: int, size: int, method: int}>}
     */
    private static function entry(string $id, string $label, bool $isRun, Collection $instances, Closure $fileUrl): array
    {
        $archiveEntries = $instances
            ->filter(fn (DicomInstance $instance): bool => $instance->dicom_archive_id !== null)
            ->mapWithKeys(fn (DicomInstance $instance): array => [$fileUrl($instance) => [
                'archive' => $instance->dicom_archive_id,
                'offset' => $instance->archive_entry_offset,
                'length' => $instance->archive_entry_length,
                'size' => $instance->archive_entry_size,
                'method' => $instance->archive_entry_method,
            ]])
            ->all();

        return [
            'id' => $id,
            'label' => $label,
            'isRun' => $isRun,
            'slices' => self::slices($instances, $fileUrl),
            ...($archiveEntries === [] ? [] : ['archiveEntries' => $archiveEntries]),
        ];
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
