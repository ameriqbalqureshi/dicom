<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Upload DICOM</h1>
        <p class="mt-1 text-sm text-slate-400">
            Select one or more DICOM Part 10 files (.dcm). They are imported as soon as the upload finishes and grouped into studies and series automatically.
        </p>
    </div>

    @if (! $hasPackage)
        <p class="rounded-md border border-red-800 bg-red-950 px-4 py-3 text-sm text-red-300">
            You do not have an active package. <a href="{{ route('package.show') }}" class="font-medium underline">Choose a package</a> to upload studies.
        </p>
    @elseif ($subscription)
        <p class="text-sm text-slate-400">
            {{ $subscription->package->name }}:
            {{ $subscription->remaining(App\Enums\Quota::Uploads) === null ? 'unlimited' : $subscription->remaining(App\Enums\Quota::Uploads) }} new {{ Str::plural('study', $subscription->remaining(App\Enums\Quota::Uploads) ?? 2) }} left,
            {{ $storageUsedMb }}{{ $subscription->max_storage_mb === null ? ' MB stored' : ' of '.number_format($subscription->max_storage_mb).' MB used' }}.
            <a href="{{ route('package.show') }}" class="text-sky-400 hover:text-sky-300">Details</a>
        </p>
    @endif

    @if ($isPatient)
        <p class="rounded-md border border-amber-800 bg-amber-950/60 px-4 py-3 text-sm text-amber-200">
            @if ($currentStudy)
                Your study <a href="{{ route('studies.show', $currentStudy) }}" class="font-medium underline">{{ $currentStudy->study_description ?? $currentStudy->patient_name ?? 'Study' }}</a>
                is available until {{ $currentStudy->expires_at->toDayDateTimeString() }}. You can add missing images to it; a new study can be uploaded once it expires.
            @else
                You can upload one study. It stays available for {{ App\Models\Study::PATIENT_STUDY_LIFETIME_DAYS }} days and is then deleted automatically.
            @endif
        </p>
    @endif

    {{-- Files are sent one request at a time so a large study never exceeds the server's per-request size or file-count limits. --}}
    <div x-data="{
            maxFiles: {{ App\Livewire\Studies\UploadStudies::MAX_FILES }},
            uploading: false,
            progress: 0,
            current: 0,
            total: 0,
            tooMany: false,
            failedFiles: [],
            async uploadFiles(input) {
                const files = Array.from(input.files);
                input.value = '';

                this.tooMany = files.length > this.maxFiles;
                if (files.length === 0 || this.tooMany) {
                    return;
                }

                this.failedFiles = [];
                await this.$wire.clearResults();
                this.uploading = true;
                this.total = files.length;

                for (const [index, file] of files.entries()) {
                    this.current = index + 1;
                    await new Promise((resolve) => this.$wire.upload(
                        'files',
                        file,
                        resolve,
                        () => { this.failedFiles.push(file.name); resolve(); },
                        (event) => { this.progress = Math.round((index + event.detail.progress / 100) / files.length * 100); },
                        resolve,
                    ));
                }

                this.uploading = false;
            },
        }"
        class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <label class="flex flex-col items-center gap-2 rounded-lg border-2 border-dashed border-slate-700 px-6 py-10 text-center"
            :class="uploading ? 'cursor-wait opacity-60' : 'cursor-pointer hover:border-sky-600'">
            <span class="text-sm text-slate-300">Click to choose files</span>
            <span class="text-xs text-slate-500">Up to {{ App\Livewire\Studies\UploadStudies::MAX_FILES }} files, 100 MB each</span>
            <input x-on:change="uploadFiles($event.target)" :disabled="uploading" type="file" multiple class="sr-only">
        </label>

        <div x-show="uploading" x-cloak class="flex flex-col gap-1.5">
            <div class="text-sm text-slate-300">
                Uploading and importing file <span x-text="current"></span> of <span x-text="total"></span>… <span x-text="progress + '%'"></span>
            </div>
            <div class="h-2 overflow-hidden rounded bg-slate-800">
                <div class="h-full bg-sky-500 transition-all" :style="`width: ${progress}%`"></div>
            </div>
        </div>

        <p x-show="tooMany" x-cloak class="text-sm text-red-400">
            Upload at most {{ App\Livewire\Studies\UploadStudies::MAX_FILES }} files at a time.
        </p>

        <div x-show="failedFiles.length > 0" x-cloak class="text-sm text-red-400">
            <p>These files could not be uploaded. Check that each is 100 MB or smaller and try them again:</p>
            <p class="mt-1 text-red-300" x-text="failedFiles.join(', ')"></p>
        </div>

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
