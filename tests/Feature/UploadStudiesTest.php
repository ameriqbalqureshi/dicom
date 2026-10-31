<?php

use App\Livewire\Studies\UploadStudies;
use App\Models\DicomInstance;
use App\Models\Series;
use App\Models\Study;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\DicomFileBuilder;

beforeEach(function () {
    Storage::fake('dicom');
});

function dicomUpload(string $name, ?DicomFileBuilder $builder = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, ($builder ?? new DicomFileBuilder)->build());
}

test('radiologists cannot open the upload page', function () {
    $this->actingAs(User::factory()->radiologist()->create())
        ->get(route('studies.upload'))
        ->assertForbidden();
});

test('technicians can open the upload page', function () {
    $this->actingAs(User::factory()->technician()->create())
        ->get(route('studies.upload'))
        ->assertOk()
        ->assertSeeLivewire(UploadStudies::class);
});

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

    Storage::disk('dicom')->assertExists($instance->file_path);
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

test('radiologists cannot import files through the component', function () {
    Livewire::actingAs(User::factory()->radiologist()->create())
        ->test(UploadStudies::class)
        ->assertForbidden();
});
