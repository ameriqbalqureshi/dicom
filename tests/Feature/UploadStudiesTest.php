<?php

use App\Enums\Role;
use App\Livewire\Studies\UploadStudies;
use App\Models\DicomInstance;
use App\Models\Series;
use App\Models\Study;
use App\Models\User;
use App\Services\Dicom\DicomImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Livewire;
use Tests\Support\DicomFileBuilder;

beforeEach(function () {
    Storage::fake('dicom');
});

function dicomUpload(string $name, ?DicomFileBuilder $builder = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, ($builder ?? new DicomFileBuilder)->build());
}

test('every role that works with studies can open the upload page', function (Role $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get(route('studies.upload'))
        ->assertOk()
        ->assertSeeLivewire(UploadStudies::class);
})->with([Role::Admin, Role::Technician, Role::Physician, Role::Patient]);

test('uploading a DICOM file immediately creates its study, series and instance', function () {
    $technician = User::factory()->technician()->create();

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->set('files', [dicomUpload('slice.dcm')])
        ->assertHasNoErrors()
        ->assertSet('files', [])
        ->assertSee('Imported.');

    $study = Study::sole();
    expect($study)
        ->study_instance_uid->toBe('1.2.826.0.1.3680043.2.1')
        ->patient_name->toBe('DOE JANE')
        ->patient_id->toBe('PID001')
        ->study_date->toDateString()->toBe('2024-03-15')
        ->uploaded_by->toBe($technician->id);

    $series = Series::sole();
    expect($series)
        ->study_id->toBe($study->id)
        ->modality->toBe('CT')
        ->series_number->toBe(3);

    $instance = DicomInstance::sole();
    expect($instance)
        ->series_id->toBe($series->id)
        ->sop_instance_uid->toBe('1.2.826.0.1.3680043.2.1.1')
        ->instance_number->toBe(7)
        ->rows->toBe(2)
        ->uploaded_by->toBe($technician->id);

    expect($instance->file_path)->toBe("users/{$technician->id}/2024/03/15/1.2.826.0.1.3680043.2.1/1.2.826.0.1.3680043.2.1.1.1/1.2.826.0.1.3680043.2.1.1.dcm");
    Storage::disk('dicom')->assertExists($instance->file_path);
});

test('a study uploaded by two users becomes an independent copy for each', function () {
    $first = User::factory()->technician()->create();
    $second = User::factory()->physician()->create();

    Livewire::actingAs($first)->test(UploadStudies::class)->set('files', [dicomUpload('slice.dcm')]);
    Livewire::actingAs($second)->test(UploadStudies::class)
        ->set('files', [dicomUpload('slice.dcm')])
        ->assertSee('Imported.');

    expect(Study::pluck('uploaded_by')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and(Series::count())->toBe(2)
        ->and(DicomInstance::count())->toBe(2)
        ->and(Storage::disk('dicom')->allFiles())->toHaveCount(2);
});

test('instances of the same series are grouped together', function () {
    Livewire::actingAs(User::factory()->technician()->create())
        ->test(UploadStudies::class)
        ->set('files', [
            dicomUpload('one.dcm'),
            dicomUpload('two.dcm', (new DicomFileBuilder)->with(0x0008, 0x0018, 'UI', '1.2.826.0.1.3680043.2.1.2')),
        ]);

    expect(Study::count())->toBe(1)
        ->and(Series::count())->toBe(1)
        ->and(DicomInstance::count())->toBe(2);
});

test('an instance that was already uploaded is skipped', function () {
    $component = Livewire::actingAs(User::factory()->technician()->create())->test(UploadStudies::class);

    $component->set('files', [dicomUpload('slice.dcm')]);
    $component->set('files', [dicomUpload('again.dcm')])->assertSee('Already uploaded.');

    expect(DicomInstance::count())->toBe(1);
    expect(Storage::disk('dicom')->allFiles())->toHaveCount(1);
});

test('results of files uploaded one request at a time accumulate until cleared', function () {
    $component = Livewire::actingAs(User::factory()->technician()->create())->test(UploadStudies::class);

    $component->set('files', [dicomUpload('one.dcm')]);
    $component->set('files', [dicomUpload('two.dcm', (new DicomFileBuilder)->with(0x0008, 0x0018, 'UI', '1.2.826.0.1.3680043.2.1.2'))])
        ->assertSee('one.dcm')
        ->assertSee('two.dcm');

    expect($component->get('results'))->toHaveCount(2)
        ->and(DicomInstance::count())->toBe(2);

    $component->call('clearResults')->assertSet('results', []);
});

test('a large upload counts every file but lists only some, and failures stay visible', function () {
    $sliceCount = UploadStudies::MAX_LISTED_RESULTS + 5;
    $slices = collect(range(1, $sliceCount))->map(fn (int $slice) => dicomUpload(
        "slice-{$slice}.dcm",
        (new DicomFileBuilder)->with(0x0008, 0x0018, 'UI', "1.2.826.0.1.3680043.2.1.{$slice}"),
    ))->all();

    $component = Livewire::actingAs(User::factory()->technician()->create())->test(UploadStudies::class);

    $component->set('files', $slices);
    $component->set('files', [UploadedFile::fake()->createWithContent('notes.txt', 'hello')]);

    $study = Study::sole();

    $component
        ->assertSet('resultCounts', ['imported' => $sliceCount, 'skipped' => 0, 'failed' => 1])
        ->assertSet('importedStudyIds', [$study->id])
        ->assertSee("{$sliceCount} imported")
        ->assertSee('notes.txt')
        ->assertDontSee("slice-{$sliceCount}.dcm");

    expect($component->get('results'))->toHaveCount(UploadStudies::MAX_LISTED_RESULTS + 1)
        ->and($study->series()->sole()->instances()->count())->toBe($sliceCount);
});

test('clearing results also clears the counts and study links', function () {
    Livewire::actingAs(User::factory()->technician()->create())
        ->test(UploadStudies::class)
        ->set('files', [dicomUpload('slice.dcm')])
        ->call('clearResults')
        ->assertSet('resultCounts', ['imported' => 0, 'skipped' => 0, 'failed' => 0])
        ->assertSet('importedStudyIds', []);
});

test('a storage failure on one file is reported and does not stop the others', function () {
    Exceptions::fake();

    $failure = UnableToCheckFileExistence::forLocation('incoming/slice.dcm');
    $technician = User::factory()->technician()->create();
    $importer = app(DicomImporter::class);

    $this->mock(DicomImporter::class)
        ->shouldReceive('import')
        ->twice()
        ->andReturnUsing(
            fn () => throw $failure,
            fn (string $path, User $uploader) => $importer->import($path, $uploader),
        );

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->set('files', [dicomUpload('stalled.dcm'), dicomUpload('fine.dcm')])
        ->assertSet('resultCounts', ['imported' => 1, 'skipped' => 0, 'failed' => 1])
        ->assertSee('Storage did not respond.');

    Exceptions::assertReported(UnableToCheckFileExistence::class);
    expect(DicomInstance::count())->toBe(1);
});

test('a file that is not DICOM is reported and not stored', function () {
    Livewire::actingAs(User::factory()->technician()->create())
        ->test(UploadStudies::class)
        ->set('files', [UploadedFile::fake()->createWithContent('notes.txt', 'hello')])
        ->assertSee('The file is not a DICOM Part 10 file.');

    expect(Study::count())->toBe(0);
    expect(Storage::disk('dicom')->allFiles())->toBeEmpty();
});

test('at least one file is required', function () {
    Livewire::actingAs(User::factory()->technician()->create())
        ->test(UploadStudies::class)
        ->call('save')
        ->assertHasErrors(['files' => 'Choose at least one DICOM file.']);
});

test('physicians can import files', function () {
    $physician = User::factory()->physician()->create();

    Livewire::actingAs($physician)
        ->test(UploadStudies::class)
        ->set('files', [dicomUpload('slice.dcm')])
        ->assertSee('Imported.');

    expect(Study::sole()->uploaded_by)->toBe($physician->id);
});
