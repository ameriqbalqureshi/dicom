<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Upload DICOM</h1>
        <p class="mt-1 text-sm text-slate-400">
            Select one or more DICOM Part 10 files (.dcm). They are imported as soon as the upload finishes and grouped into studies and series automatically.
        </p>
    </div>

    <div x-data="{ uploading: false, progress: 0, failed: false }"
        x-on:livewire-upload-start="uploading = true; failed = false; progress = 0"
        x-on:livewire-upload-progress="progress = $event.detail.progress"
        x-on:livewire-upload-finish="uploading = false"
        x-on:livewire-upload-error="uploading = false; failed = true"
        class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <label class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-slate-700 px-6 py-10 text-center hover:border-sky-600">
            <span class="text-sm text-slate-300">Click to choose files</span>
            <span class="text-xs text-slate-500">Up to {{ App\Livewire\Studies\UploadStudies::MAX_FILES }} files, 100 MB each</span>
            <input wire:model="files" type="file" multiple class="sr-only">
        </label>

        <div x-show="uploading" x-cloak class="flex flex-col gap-1.5">
            <div class="text-sm text-slate-300">Uploading… <span x-text="progress + '%'"></span></div>
            <div class="h-2 overflow-hidden rounded bg-slate-800">
                <div class="h-full bg-sky-500 transition-all" :style="`width: ${progress}%`"></div>
            </div>
        </div>

        <div wire:loading wire:target="files" class="text-sm text-slate-300">Importing…</div>

        <p x-show="failed" x-cloak class="text-sm text-red-400">
            The upload failed. Check that the files are not larger than the server allows and try again.
        </p>

        @error('files') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        @foreach (collect($errors->get('files.*'))->flatten()->unique() as $fileError)
            <p wire:key="file-error-{{ $loop->index }}" class="text-sm text-red-400">{{ $fileError }}</p>
        @endforeach
    </div>

    @if (count($results) > 0)
        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">File</th>
                        <th class="px-4 py-3">Result</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach ($results as $index => $result)
                        <tr wire:key="result-{{ $index }}">
                            <td class="px-4 py-3 text-slate-300">{{ $result['name'] }}</td>
                            <td @class([
                                'px-4 py-3',
                                'text-emerald-400' => $result['status'] === 'imported',
                                'text-amber-400' => $result['status'] === 'skipped',
                                'text-red-400' => $result['status'] === 'failed',
                            ])>{{ $result['message'] }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($result['studyId'])
                                    <a href="{{ route('studies.show', $result['studyId']) }}" class="text-sky-400 hover:text-sky-300">Open study</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
