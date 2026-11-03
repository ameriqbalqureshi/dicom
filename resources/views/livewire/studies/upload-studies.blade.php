<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Upload DICOM</h1>
        <p class="mt-1 text-sm text-slate-400">
            Choose DICOM files, or a whole folder such as the DICOM folder on a patient CD. Files are recognised by their content, so names without .dcm are fine, and anything else in the folder is skipped.
            They are imported as soon as the upload finishes and grouped into studies and series automatically.
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
                Physicians you have shared it with may choose to keep their own copy.
            @endif
        </p>
    @endif

    {{--
        With S3 storage, files go straight from the browser to S3 (a few at a time) and the server only imports them.
        Otherwise they are sent to the server one request at a time, so a large study never exceeds its per-request limits.
    --}}
    <div x-data="{
            maxFiles: {{ App\Livewire\Studies\UploadStudies::MAX_FILES }},
            directUploads: @js($directUploads),
            parallelUploads: 3,
            uploading: false,
            progress: 0,
            current: 0,
            total: 0,
            tooMany: false,
            scanning: false,
            dragging: false,
            skipped: 0,
            noDicomFound: false,
            failedFiles: [],
            async chooseFiles(input) {
                const files = Array.from(input.files);
                input.value = '';
                await this.uploadFiles(files);
            },
            async dropFiles(event) {
                this.dragging = false;
                if (this.uploading || this.scanning) {
                    return;
                }

                const entries = Array.from(event.dataTransfer.items)
                    .map((item) => item.webkitGetAsEntry?.())
                    .filter(Boolean);

                if (entries.length === 0) {
                    return this.uploadFiles(Array.from(event.dataTransfer.files));
                }

                this.scanning = true;
                const files = (await Promise.all(entries.map((entry) => this.filesIn(entry)))).flat();
                this.scanning = false;
                await this.uploadFiles(files);
            },
            async filesIn(entry) {
                if (entry.isFile) {
                    return [await new Promise((resolve, reject) => entry.file(resolve, reject))];
                }

                const reader = entry.createReader();
                const children = [];
                let batch;
                do {
                    batch = await new Promise((resolve, reject) => reader.readEntries(resolve, reject));
                    children.push(...batch);
                } while (batch.length > 0);

                return (await Promise.all(children.map((child) => this.filesIn(child)))).flat();
            },
            {{-- DICOM Part 10 files carry 'DICM' after a 128 byte preamble, whatever their name. DICOMDIR is the disc's index, not an image. --}}
            async isDicom(file) {
                if (file.name.toUpperCase() === 'DICOMDIR' || file.size < 132) {
                    return false;
                }

                try {
                    return (await file.slice(128, 132).text()) === 'DICM';
                } catch (error) {
                    return false;
                }
            },
            {{-- CD images are often named without an extension (IMG00001), which Livewire cannot store as a temporary upload. --}}
            withExtension(file) {
                return /\.[^.]+$/.test(file.name) ? file : new File([file], file.name + '.dcm', { type: file.type });
            },
            async uploadFiles(selected) {
                if (selected.length === 0) {
                    return;
                }

                this.scanning = true;
                const checks = await Promise.all(selected.map((file) => this.isDicom(file)));
                const files = selected.filter((file, index) => checks[index]);
                this.scanning = false;

                this.skipped = selected.length - files.length;
                this.noDicomFound = files.length === 0;
                this.tooMany = files.length > this.maxFiles;
                if (this.noDicomFound || this.tooMany) {
                    return;
                }

                this.failedFiles = [];
                await this.$wire.clearResults();
                this.uploading = true;
                this.total = files.length;
                this.current = 0;
                this.progress = 0;

                if (this.directUploads) {
                    await this.uploadToStorage(files);
                } else {
                    await this.uploadToServer(files);
                }

                this.uploading = false;
            },
            async uploadToServer(files) {
                for (const [index, file] of files.entries()) {
                    this.current = index + 1;
                    await new Promise((resolve) => this.$wire.upload(
                        'files',
                        this.withExtension(file),
                        resolve,
                        () => { this.failedFiles.push(file.name); resolve(); },
                        (event) => { this.progress = Math.round((index + event.detail.progress / 100) / files.length * 100); },
                        resolve,
                    ));
                }
            },
            async uploadToStorage(files) {
                const totalBytes = files.reduce((sum, file) => sum + file.size, 0) || 1;
                const sent = files.map(() => 0);
                const showProgress = () => { this.progress = Math.round(sent.reduce((sum, bytes) => sum + bytes, 0) / totalBytes * 100); };
                let next = 0;

                const uploadNext = async () => {
                    while (next < files.length) {
                        const index = next++;
                        const file = files[index];

                        try {
                            const target = await this.$wire.prepareUpload(file.name, file.size);

                            if (target) {
                                await this.put(target, file, (bytes) => { sent[index] = bytes; showProgress(); });
                                await this.$wire.importUpload(target.path, file.name);
                            }
                        } catch (error) {
                            this.failedFiles.push(file.name);
                        }

                        sent[index] = file.size;
                        this.current++;
                        showProgress();
                    }
                };

                await Promise.all(Array.from({ length: Math.min(this.parallelUploads, files.length) }, uploadNext));
                await this.$wire.$refresh();
            },
            put(target, file, onProgress) {
                return new Promise((resolve, reject) => {
                    const request = new XMLHttpRequest();
                    request.open('PUT', target.url);
                    Object.entries(target.headers).forEach(([name, value]) => request.setRequestHeader(name, value));
                    request.upload.onprogress = (event) => onProgress(event.loaded);
                    request.onload = () => (request.status < 300 ? resolve() : reject(new Error(`Upload failed with HTTP ${request.status}`)));
                    request.onerror = () => reject(new Error('Upload failed'));
                    request.send(file);
                });
            },
        }"
        class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <div x-on:dragover.prevent="dragging = true"
            x-on:dragleave.prevent="dragging = false"
            x-on:drop.prevent="dropFiles($event)"
            class="flex flex-col items-center gap-3 rounded-lg border-2 border-dashed px-6 py-10 text-center"
            :class="[dragging ? 'border-sky-500 bg-slate-800/60' : 'border-slate-700', (uploading || scanning) ? 'cursor-wait opacity-60' : '']">
            <div class="flex flex-wrap justify-center gap-3">
                <label class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white"
                    :class="(uploading || scanning) ? 'pointer-events-none' : 'cursor-pointer hover:bg-sky-500'">
                    Choose folder
                    <input x-on:change="chooseFiles($event.target)" :disabled="uploading || scanning" type="file" webkitdirectory multiple class="sr-only">
                </label>
                <label class="rounded-md border border-slate-600 px-4 py-2 text-sm font-medium text-slate-200"
                    :class="(uploading || scanning) ? 'pointer-events-none' : 'cursor-pointer hover:border-sky-600'">
                    Choose files
                    <input x-on:change="chooseFiles($event.target)" :disabled="uploading || scanning" type="file" multiple class="sr-only">
                </label>
            </div>
            <span class="text-sm text-slate-400">or drop files or a folder here</span>
            <span class="text-xs text-slate-500">Up to {{ App\Livewire\Studies\UploadStudies::MAX_FILES }} DICOM files, 100 MB each. For a CD, choose its DICOM folder or the whole disc.</span>
        </div>

        <p x-show="scanning" x-cloak class="text-sm text-slate-300">Looking for DICOM files…</p>

        <p x-show="!scanning && skipped > 0 && !noDicomFound" x-cloak class="text-sm text-slate-400">
            <span x-text="skipped"></span> <span x-text="skipped === 1 ? 'file is' : 'files are'"></span> not DICOM images (for example DICOMDIR or viewer software) and <span x-text="skipped === 1 ? 'was' : 'were'"></span> skipped.
        </p>

        <p x-show="noDicomFound" x-cloak class="text-sm text-red-400">
            No DICOM files were found in the selection.
        </p>

        <div x-show="uploading" x-cloak class="flex flex-col gap-1.5">
            <div class="text-sm text-slate-300">
                Uploading and importing: <span x-text="current"></span> of <span x-text="total"></span> files done… <span x-text="progress + '%'"></span>
            </div>
            <div class="h-2 overflow-hidden rounded bg-slate-800">
                <div class="h-full bg-sky-500 transition-all" :style="`width: ${progress}%`"></div>
            </div>
        </div>

        <p x-show="tooMany" x-cloak class="text-sm text-red-400">
            The selection contains more than {{ App\Livewire\Studies\UploadStudies::MAX_FILES }} DICOM files. Upload at most {{ App\Livewire\Studies\UploadStudies::MAX_FILES }} at a time, for example one series folder at a time.
        </p>

        <div x-show="failedFiles.length > 0" x-cloak class="text-sm text-red-400">
            <p>These files could not be uploaded. Check your connection, and that each is 100 MB or smaller, then try them again:</p>
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
