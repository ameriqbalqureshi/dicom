<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Upload DICOM</h1>
        <p class="mt-1 text-sm text-slate-400">
            Choose DICOM files, a .zip of them, or a whole folder such as the DICOM folder on a patient CD. Files are recognised by their content, so names without .dcm are fine, and anything else is skipped.
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
            maxFileSize: {{ App\Services\Dicom\DicomImporter::MAX_FILE_SIZE }},
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
            dicomFound: 0,
            encryptedZips: [],
            unreadableZips: [],
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
            {{-- Each upload is read only when its turn comes, so a zip is extracted one image at a time. Zip entries are only known to be DICOM once extracted. --}}
            async uploadsIn(selected) {
                const uploads = [];

                for (const file of selected) {
                    if (await this.isDicom(file)) {
                        uploads.push({ name: file.name, size: file.size, read: async () => file });
                    } else if (await window.dicomZip.isZip(file)) {
                        uploads.push(...await this.uploadsInZip(file));
                    } else {
                        this.skipped++;
                    }
                }

                return uploads;
            },
            async uploadsInZip(file) {
                try {
                    const zip = await window.dicomZip.readZip(file);

                    if (zip.isEncrypted) {
                        this.encryptedZips.push(file.name);

                        return [];
                    }

                    this.skipped += zip.ignored;

                    return zip.uploads;
                } catch (error) {
                    this.unreadableZips.push(file.name);

                    return [];
                }
            },
            {{-- The file to send for an upload, or null when it turns out not to be DICOM. --}}
            async readUpload(upload) {
                if (upload.size > this.maxFileSize) {
                    throw new Error('The file is too large.');
                }

                const file = await upload.read();

                if (await this.isDicom(file)) {
                    this.dicomFound++;

                    return file;
                }

                this.skipped++;

                return null;
            },
            async uploadFiles(selected) {
                if (selected.length === 0) {
                    return;
                }

                this.skipped = 0;
                this.dicomFound = 0;
                this.encryptedZips = [];
                this.unreadableZips = [];
                this.failedFiles = [];
                this.noDicomFound = false;
                this.scanning = true;
                const uploads = await this.uploadsIn(selected);
                this.scanning = false;

                this.tooMany = uploads.length > this.maxFiles;
                if (uploads.length === 0 || this.tooMany) {
                    this.noDicomFound = uploads.length === 0 && this.encryptedZips.length === 0 && this.unreadableZips.length === 0;

                    return;
                }

                await this.$wire.clearResults();
                this.uploading = true;
                this.total = uploads.length;
                this.current = 0;
                this.progress = 0;

                if (this.directUploads) {
                    await this.uploadToStorage(uploads);
                } else {
                    await this.uploadToServer(uploads);
                }

                this.uploading = false;
                this.noDicomFound = this.dicomFound === 0 && this.failedFiles.length === 0;
            },
            async uploadToServer(uploads) {
                for (const [index, upload] of uploads.entries()) {
                    this.current = index + 1;
                    let file = null;

                    try {
                        file = await this.readUpload(upload);
                    } catch (error) {
                        this.failedFiles.push(upload.name);
                    }

                    if (! file) {
                        this.progress = Math.round((index + 1) / uploads.length * 100);
                        continue;
                    }

                    await new Promise((resolve) => this.$wire.upload(
                        'files',
                        this.withExtension(file),
                        resolve,
                        () => { this.failedFiles.push(upload.name); resolve(); },
                        (event) => { this.progress = Math.round((index + event.detail.progress / 100) / uploads.length * 100); },
                        resolve,
                    ));
                }
            },
            async uploadToStorage(uploads) {
                const totalBytes = uploads.reduce((sum, upload) => sum + upload.size, 0) || 1;
                const sent = uploads.map(() => 0);
                const showProgress = () => { this.progress = Math.round(sent.reduce((sum, bytes) => sum + bytes, 0) / totalBytes * 100); };
                let next = 0;

                const uploadNext = async () => {
                    while (next < uploads.length) {
                        const index = next++;
                        const upload = uploads[index];

                        try {
                            const file = await this.readUpload(upload);
                            const target = file ? await this.$wire.prepareUpload(file.name, file.size) : null;

                            if (target) {
                                await this.put(target, file, (bytes) => { sent[index] = bytes; showProgress(); });
                                await this.$wire.importUpload(target.path, file.name);
                            }
                        } catch (error) {
                            this.failedFiles.push(upload.name);
                        }

                        sent[index] = upload.size;
                        this.current++;
                        showProgress();
                    }
                };

                await Promise.all(Array.from({ length: Math.min(this.parallelUploads, uploads.length) }, uploadNext));
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
                    Choose files or .zip
                    <input x-on:change="chooseFiles($event.target)" :disabled="uploading || scanning" type="file" multiple class="sr-only">
                </label>
            </div>
            <span class="text-sm text-slate-400">or drop files, a .zip or a folder here</span>
            <span class="text-xs text-slate-500">Up to {{ number_format(App\Livewire\Studies\UploadStudies::MAX_FILES) }} files, 100 MB each. For a CD, choose its DICOM folder or the whole disc.</span>
        </div>

        <p x-show="scanning" x-cloak class="text-sm text-slate-300">Looking for DICOM files…</p>

        <p x-show="!scanning && skipped > 0 && !noDicomFound" x-cloak class="text-sm text-slate-400">
            <span x-text="skipped"></span> <span x-text="skipped === 1 ? 'file is' : 'files are'"></span> not DICOM images (for example DICOMDIR or viewer software) and <span x-text="skipped === 1 ? 'was' : 'were'"></span> skipped.
        </p>

        <p x-show="noDicomFound" x-cloak class="text-sm text-red-400">
            No DICOM files were found in the selection.
        </p>

        <p x-show="encryptedZips.length > 0" x-cloak class="text-sm text-red-400">
            Password-protected zips cannot be read. Zip the images again without a password: <span class="text-red-300" x-text="encryptedZips.join(', ')"></span>
        </p>

        <p x-show="unreadableZips.length > 0" x-cloak class="text-sm text-red-400">
            These zips could not be opened and may be damaged or incomplete: <span class="text-red-300" x-text="unreadableZips.join(', ')"></span>
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
            The selection contains more than {{ number_format(App\Livewire\Studies\UploadStudies::MAX_FILES) }} files. Upload at most {{ number_format(App\Livewire\Studies\UploadStudies::MAX_FILES) }} at a time, for example one series folder at a time.
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
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <span class="text-emerald-400">{{ number_format($resultCounts['imported']) }} imported</span>
            @if ($resultCounts['skipped'] > 0)
                <span class="text-amber-400">{{ number_format($resultCounts['skipped']) }} already uploaded</span>
            @endif
            @if ($resultCounts['failed'] > 0)
                <span class="text-red-400">{{ number_format($resultCounts['failed']) }} failed</span>
            @endif
            @if (count($importedStudyIds) === 1)
                <a href="{{ route('studies.show', $importedStudyIds[0]) }}" class="font-medium text-sky-400 hover:text-sky-300">Open study</a>
            @elseif (count($importedStudyIds) > 1)
                <a href="{{ route('studies.index') }}" class="font-medium text-sky-400 hover:text-sky-300">View {{ count($importedStudyIds) }} studies</a>
            @endif
            @if (count($results) < array_sum($resultCounts))
                <span class="text-slate-500">Showing {{ count($results) }} of {{ number_format(array_sum($resultCounts)) }} files.</span>
            @endif
        </div>

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
                    @foreach (collect($results)->sortBy(fn (array $result) => $result['status'] === 'failed' ? 0 : 1) as $index => $result)
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
