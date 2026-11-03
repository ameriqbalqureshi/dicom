<?php

namespace App\Livewire\Studies;

use App\Enums\Role;
use App\Models\DicomInstance;
use App\Models\Study;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\QuotaExceededException;
use App\Services\Dicom\DicomImporter;
use App\Services\Dicom\DicomStorage;
use App\Services\Dicom\DuplicateDicomInstanceException;
use App\Services\Dicom\InvalidDicomFileException;
use App\Services\Dicom\StudyLimitReachedException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Upload DICOM')]
class UploadStudies extends Component
{
    use WithFileUploads;

    public const MAX_FILES = 200;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /** @var list<array{name: string, status: string, message: string, studyId: int|null}> */
    public array $results = [];

    public function mount(): void
    {
        $this->authorize('create', Study::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'files' => ['required', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'max:102400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'files.required' => 'Choose at least one DICOM file.',
            'files.max' => 'Upload at most '.self::MAX_FILES.' files at a time.',
            'files.*.max' => 'Each file must be 100 MB or smaller.',
        ];
    }

    /**
     * Import the files as soon as the browser has finished uploading them.
     */
    public function updatedFiles(): void
    {
        $this->save();
    }

    public function save(): void
    {
        $this->authorize('create', Study::class);
        $this->validate();

        $importer = app(DicomImporter::class);

        foreach ($this->files as $file) {
            $this->results[] = $this->importFile($importer, $file);
        }

        $this->reset('files');
    }

    /**
     * Start a new selection. The browser uploads its files one request at a
     * time, so results accumulate across requests until this is called.
     */
    public function clearResults(): void
    {
        $this->reset('results');
        $this->resetErrorBag();
    }

    /**
     * Hand the browser a signed link to upload one file straight to storage (S3), skipping the application. Returns
     * null, and records why, when the file cannot be accepted.
     *
     * @return array{path: string, url: string, headers: array<string, string>}|null
     */
    #[Renderless]
    public function prepareUpload(string $name, int $size, DicomStorage $storage, PackageLimits $limits): ?array
    {
        $user = auth()->user();
        $this->authorize('create', Study::class);

        if (! $storage->supportsDirectUploads()) {
            abort(404);
        }

        if ($size < 1 || $size > DicomImporter::MAX_FILE_SIZE) {
            $this->addFailure($name, 'Each file must be 100 MB or smaller.');

            return null;
        }

        try {
            $limits->ensureStorageFor($user, $size);
        } catch (QuotaExceededException $exception) {
            $this->addFailure($name, $exception->getMessage());

            return null;
        }

        $path = $storage->incomingPathFor($user->id);

        return ['path' => $path, ...$storage->uploadTarget($path)];
    }

    /**
     * Import a file the browser has uploaded with a link from prepareUpload().
     */
    public function importUpload(string $path, string $name, DicomStorage $storage, DicomImporter $importer): void
    {
        $user = auth()->user();
        $this->authorize('create', Study::class);

        if (! $storage->isIncomingPathOf($path, $user->id)) {
            abort(403);
        }

        $this->results[] = $this->importWith(Str::limit($name, 255), fn () => $importer->importUploaded($path, $user));
    }

    /**
     * Record a file that was refused before it was uploaded. The render this needs happens on the next request.
     */
    private function addFailure(string $name, string $message): void
    {
        $this->results[] = ['name' => Str::limit($name, 255), 'status' => 'failed', 'message' => $message, 'studyId' => null];
    }

    /**
     * @return array{name: string, status: string, message: string, studyId: int|null}
     */
    private function importFile(DicomImporter $importer, TemporaryUploadedFile $file): array
    {
        try {
            return $this->importWith($file->getClientOriginalName(), fn () => $importer->import($file->getRealPath(), auth()->user()));
        } finally {
            $file->delete();
        }
    }

    /**
     * Run an import and describe its outcome for the results table.
     *
     * @param  callable(): DicomInstance  $import
     * @return array{name: string, status: string, message: string, studyId: int|null}
     */
    private function importWith(string $name, callable $import): array
    {
        try {
            $instance = $import();

            return ['name' => $name, 'status' => 'imported', 'message' => 'Imported.', 'studyId' => $instance->series->study_id];
        } catch (DuplicateDicomInstanceException) {
            return ['name' => $name, 'status' => 'skipped', 'message' => 'Already uploaded.', 'studyId' => null];
        } catch (InvalidDicomFileException|StudyLimitReachedException|QuotaExceededException $exception) {
            return ['name' => $name, 'status' => 'failed', 'message' => $exception->getMessage(), 'studyId' => null];
        }
    }

    public function render(PackageLimits $limits, DicomStorage $storage): View
    {
        $user = auth()->user();

        return view('livewire.studies.upload-studies', [
            'directUploads' => $storage->supportsDirectUploads(),
            'isPatient' => $user->role === Role::Patient,
            'currentStudy' => $user->role === Role::Patient ? $user->uploadedStudies()->unexpired()->first() : null,
            'subscription' => $user->isAdmin() ? null : $limits->current($user),
            'hasPackage' => $user->isAdmin() || $limits->current($user) !== null,
            'storageUsedMb' => round($limits->storageUsed($user) / 1024 / 1024, 1),
        ]);
    }
}
