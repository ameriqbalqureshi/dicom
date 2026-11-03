<?php

use App\Livewire\Studies\StudyIndex;
use App\Livewire\Studies\UploadStudies;
use App\Models\DicomInstance;
use App\Models\GuestLink;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\DicomFileBuilder;

beforeEach(function () {
    Storage::fake('dicom');
});

/**
 * A DICOM upload of the given study and instance.
 */
function patientUpload(string $studyUid, string $sopInstanceUid): UploadedFile
{
    $builder = (new DicomFileBuilder)
        ->with(0x0020, 0x000D, 'UI', $studyUid)
        ->with(0x0008, 0x0018, 'UI', $sopInstanceUid)
        ->with(0x0002, 0x0003, 'UI', $sopInstanceUid);

    return UploadedFile::fake()->createWithContent("{$sopInstanceUid}.dcm", $builder->build());
}

test('a patient study expires seven days after upload, other studies do not expire', function () {
    $this->freezeSecond();
    $patient = User::factory()->patient()->create();
    $physician = User::factory()->physician()->create();

    Livewire::actingAs($patient)->test(UploadStudies::class)->set('files', [patientUpload('1.2.3', '1.2.3.1')]);
    Livewire::actingAs($physician)->test(UploadStudies::class)->set('files', [patientUpload('1.2.3', '1.2.3.1')]);

    expect(Study::whereBelongsTo($patient, 'uploader')->sole()->expires_at->equalTo(now()->addDays(Study::PATIENT_STUDY_LIFETIME_DAYS)))->toBeTrue()
        ->and(Study::whereBelongsTo($physician, 'uploader')->sole()->expires_at)->toBeNull();
});

test('a patient can add images to their current study but cannot upload a second one', function () {
    $patient = User::factory()->patient()->create();

    Livewire::actingAs($patient)
        ->test(UploadStudies::class)
        ->set('files', [patientUpload('1.2.3', '1.2.3.1'), patientUpload('1.2.3', '1.2.3.2'), patientUpload('1.2.4', '1.2.4.1')])
        ->assertSee('This file belongs to another study.');

    expect(Study::sole()->study_instance_uid)->toBe('1.2.3')
        ->and(DicomInstance::count())->toBe(2)
        ->and(Storage::disk('dicom')->allFiles())->toHaveCount(2);
});

test('the upload page tells patients about the limit and their current study', function () {
    $patient = User::factory()->patient()->create();

    $this->actingAs($patient)->get(route('studies.upload'))->assertSee('You can upload one study.');

    $study = Study::factory()->for($patient, 'uploader')->create(['study_description' => 'CORONARY ANGIO', 'expires_at' => now()->addDays(3)]);

    $this->actingAs($patient)->get(route('studies.upload'))
        ->assertSee('CORONARY ANGIO')
        ->assertSee($study->expires_at->toDayDateTimeString());
});

test('once the study has expired the patient can upload a new one, and the old one is removed', function () {
    $patient = User::factory()->patient()->create();
    Livewire::actingAs($patient)->test(UploadStudies::class)->set('files', [patientUpload('1.2.3', '1.2.3.1')]);
    Study::sole()->update(['expires_at' => now()->subMinute()]);

    Livewire::actingAs($patient)->test(UploadStudies::class)->set('files', [patientUpload('1.2.4', '1.2.4.1')])->assertSee('Imported.');

    expect(Study::sole()->study_instance_uid)->toBe('1.2.4')
        ->and(Storage::disk('dicom')->allFiles())->toHaveCount(1);
});

test('expired studies cannot be opened, shared or linked, even by physicians who accepted them', function () {
    $study = Study::factory()->for(User::factory()->patient(), 'uploader')->create(['expires_at' => now()->subMinute()]);
    $share = StudyShare::factory()->for($study)->create();
    $admin = User::factory()->admin()->create();

    foreach ([$study->uploader, $share->recipient, $admin] as $user) {
        expect($user->can('view', $study))->toBeFalse()
            ->and($user->can('share', $study))->toBeFalse()
            ->and($user->can('createGuestLink', $study))->toBeFalse()
            ->and(Study::visibleTo($user)->count())->toBe(0);
    }

    Livewire::actingAs($admin)->test(StudyIndex::class)->assertDontSee($study->patient_name);
});

test('guest links stop working when the study expires', function () {
    $study = Study::factory()->for(User::factory()->patient(), 'uploader')->create(['expires_at' => now()->addHour()]);
    $link = GuestLink::factory()->for($study)->create(['created_by' => $study->uploaded_by]);

    expect($link->isUsable())->toBeTrue();

    $this->travel(2)->hours();

    expect($link->fresh()->isUsable())->toBeFalse();
});

test('the scheduled clean-up deletes expired studies no physician can open with their files, shares and guest links', function () {
    Storage::disk('dicom')->put('expired.dcm', 'bytes');
    Storage::disk('dicom')->put('current.dcm', 'bytes');
    $expired = DicomInstance::factory()->create(['file_path' => 'expired.dcm'])->series->study;
    $expired->update(['expires_at' => now()->subMinute()]);
    StudyShare::factory()->pending()->for($expired)->create();
    GuestLink::factory()->for($expired)->create();
    $current = DicomInstance::factory()->create(['file_path' => 'current.dcm'])->series->study;
    $current->update(['expires_at' => now()->addDay()]);

    $this->artisan('studies:delete-expired')->expectsOutput('Deleted 1 study.')->assertSuccessful();

    $this->assertModelMissing($expired);
    $this->assertModelExists($current);
    expect(StudyShare::count())->toBe(0)
        ->and(GuestLink::count())->toBe(0)
        ->and(Storage::disk('dicom')->allFiles())->toBe(['current.dcm']);
});

test('the study page and list show when a patient study will be deleted', function () {
    $study = Study::factory()->for(User::factory()->patient(), 'uploader')->create(['expires_at' => now()->addDays(3)]);

    $this->actingAs($study->uploader)->get(route('studies.show', $study))->assertSee('Deleted on:');
    $this->actingAs($study->uploader)->get(route('studies.index'))->assertSee('Deleted 2 days from now');
});
