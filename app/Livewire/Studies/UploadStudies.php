<?php

namespace App\Livewire\Studies;

use App\Enums\Role;
use App\Models\Study;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\QuotaExceededException;
use App\Services\Dicom\DicomImporter;
use App\Services\Dicom\DuplicateDicomInstanceException;
use App\Services\Dicom\InvalidDicomFileException;
use App\Services\Dicom\StudyLimitReachedException;
use Illuminate\Contracts\View\View;
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
     * @return array{name: string, status: string, message: string, studyId: int|null}
     */
    private function importFile(DicomImporter $importer, TemporaryUploadedFile $file): array
    {
        $name = $file->getClientOriginalName();

        try {
            $instance = $importer->import($file->getRealPath(), auth()->user());

            return ['name' => $name, 'status' => 'imported', 'message' => 'Imported.', 'studyId' => $instance->series->study_id];
        } catch (DuplicateDicomInstanceException) {
            return ['name' => $name, 'status' => 'skipped', 'message' => 'Already uploaded.', 'studyId' => null];
        } catch (InvalidDicomFileException|StudyLimitReachedException|QuotaExceededException $exception) {
            return ['name' => $name, 'status' => 'failed', 'message' => $exception->getMessage(), 'studyId' => null];
        } finally {
            $file->delete();
        }
    }

    public function render(PackageLimits $limits): View
    {
        $user = auth()->user();

        return view('livewire.studies.upload-studies', [
            'isPatient' => $user->role === Role::Patient,
            'currentStudy' => $user->role === Role::Patient ? $user->uploadedStudies()->unexpired()->first() : null,
            'subscription' => $user->isAdmin() ? null : $limits->current($user),
            'hasPackage' => $user->isAdmin() || $limits->current($user) !== null,
            'storageUsedMb' => round($limits->storageUsed($user) / 1024 / 1024, 1),
        ]);
    }
}
