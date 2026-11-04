<?php

use App\Enums\Quota;
use App\Enums\Role;
use App\Livewire\Billing\PackageIndex;
use App\Livewire\PatientRecords\PatientNotes;
use App\Livewire\PatientRecords\PatientRecordForm;
use App\Livewire\PatientRecords\PatientRecordIndex;
use App\Livewire\PatientRecords\PatientRecordShow;
use App\Livewire\PatientRecords\PatientStudies;
use App\Models\Package;
use App\Models\PatientNote;
use App\Models\PatientRecord;
use App\Models\PatientRecordStudy;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use App\Services\Billing\SubscriptionManager;
use App\Services\Dicom\StudyDeleter;
use App\Services\UserDeleter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('dicom');
});

/**
 * A physician with one patient record.
 *
 * @return array{0: User, 1: PatientRecord}
 */
function physicianWithRecord(): array
{
    $physician = User::factory()->physician()->create();

    return [$physician, PatientRecord::factory()->for($physician, 'owner')->create()];
}

test('a physician can create a record with only a name', function () {
    $physician = User::factory()->physician()->create();

    Livewire::actingAs($physician)->test(PatientRecordForm::class)
        ->set('name', 'Ali Khan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('patient-records.show', PatientRecord::sole()));

    expect(PatientRecord::sole())
        ->owner_id->toBe($physician->id)
        ->name->toBe('Ali Khan')
        ->date_of_birth->toBeNull()
        ->diagnosis->toBeNull();
});

test('a physician can save clinical details and change them later', function () {
    $physician = User::factory()->physician()->create();

    Livewire::actingAs($physician)->test(PatientRecordForm::class)
        ->set('name', 'Ali Khan')
        ->set('dateOfBirth', '1960-05-01')
        ->set('sex', 'M')
        ->set('riskFactors', ['diabetes', 'smoking'])
        ->set('clinical.diagnosis', 'Triple vessel disease')
        ->call('save')
        ->assertHasNoErrors();

    $record = PatientRecord::sole();

    Livewire::actingAs($physician)->test(PatientRecordForm::class, ['patientRecord' => $record])
        ->assertSet('name', 'Ali Khan')
        ->assertSet('riskFactors', ['diabetes', 'smoking'])
        ->assertSet('clinical.diagnosis', 'Triple vessel disease')
        ->set('clinical.diagnosis', 'CABG done')
        ->set('riskFactors', ['diabetes'])
        ->call('save')
        ->assertHasNoErrors();

    expect($record->refresh())
        ->diagnosis->toBe('CABG done')
        ->date_of_birth->format('Y-m-d')->toBe('1960-05-01')
        ->and(array_map(fn ($factor) => $factor->label(), $record->riskFactors()))->toBe(['Diabetes']);
});

test('a record is validated', function (string $field, mixed $value, ?string $errorField = null) {
    Livewire::actingAs(User::factory()->physician()->create())->test(PatientRecordForm::class)
        ->set('name', 'Ali Khan')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($errorField ?? $field);

    expect(PatientRecord::count())->toBe(0);
})->with([
    'missing name' => ['name', ''],
    'date of birth in the future' => ['dateOfBirth', '2999-01-01'],
    'unknown sex' => ['sex', 'X'],
    'unknown risk factor' => ['riskFactors', ['astrology'], 'riskFactors.0'],
    'clinical text too long' => ['clinical.diagnosis', str_repeat('a', 10001)],
]);

test('fields outside the clinical sections cannot be set through the clinical details', function () {
    $physician = User::factory()->physician()->create();
    $otherPhysician = User::factory()->physician()->create();

    Livewire::actingAs($physician)->test(PatientRecordForm::class)
        ->set('name', 'Ali Khan')
        ->set('clinical.owner_id', (string) $otherPhysician->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(PatientRecord::sole()->owner_id)->toBe($physician->id);
});

test('clinical details and notes are encrypted in the database', function () {
    [, $record] = physicianWithRecord();
    $record->update(['diagnosis' => 'Secret diagnosis', 'risk_factors' => ['diabetes']]);
    $note = PatientNote::factory()->for($record)->create(['body' => 'Secret note']);

    $recordRow = DB::table('patient_records')->find($record->id);

    expect($recordRow->diagnosis)->not->toContain('Secret diagnosis')
        ->and($recordRow->risk_factors)->not->toContain('diabetes')
        ->and(DB::table('patient_notes')->find($note->id)->body)->not->toContain('Secret note')
        ->and($record->refresh()->diagnosis)->toBe('Secret diagnosis');
});

test('only physicians can open the patient pages', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get(route('patient-records.index'))->assertForbidden();
    $this->get(route('patient-records.create'))->assertForbidden();
})->with(['admin', 'technician', 'patient']);

test('physicians see the patient pages and the navigation link', function () {
    $this->actingAs(User::factory()->physician()->create());

    $this->get(route('patient-records.index'))->assertOk()->assertSee('New patient');
    $this->get(route('patient-records.create'))->assertOk();
});

test('nobody but the physician who owns a record can open it', function (User $intruder) {
    [, $record] = physicianWithRecord();

    $this->actingAs($intruder);

    $this->get(route('patient-records.show', $record))->assertForbidden();
    $this->get(route('patient-records.edit', $record))->assertForbidden();
    $this->get(route('patient-records.pdf', $record))->assertForbidden();
})->with([
    'another physician' => fn () => User::factory()->physician()->create(),
    'an administrator' => fn () => User::factory()->admin()->create(),
    'a technician' => fn () => User::factory()->technician()->create(),
    'a patient' => fn () => User::factory()->patient()->create(),
]);

test('the owner can open the edit page with the record filled in', function () {
    [$physician, $record] = physicianWithRecord();

    $this->actingAs($physician)->get(route('patient-records.edit', $record))
        ->assertOk()
        ->assertSee($record->name)
        ->assertSee('Edit patient');
});

test('guests are sent to sign in', function () {
    [, $record] = physicianWithRecord();

    $this->get(route('patient-records.show', $record))->assertRedirect(route('login'));
    $this->get(route('patient-records.pdf', $record))->assertRedirect(route('login'));
});

test('another physician cannot change a record through its components', function () {
    [$owner, $record] = physicianWithRecord();
    $note = PatientNote::factory()->for($record)->create();
    $otherPhysician = User::factory()->physician()->create();

    Livewire::actingAs($owner);
    $notes = Livewire::test(PatientNotes::class, ['patientRecord' => $record]);
    $show = Livewire::test(PatientRecordShow::class, ['patientRecord' => $record]);

    Livewire::actingAs($otherPhysician);

    $notes->call('deleteNote', $note->id)->assertForbidden();
    $show->call('delete')->assertForbidden();

    expect(PatientNote::whereKey($note->id)->exists())->toBeTrue()
        ->and($record->fresh())->not->toBeNull();
});

test('physicians only list their own records, and can search them', function () {
    $physician = User::factory()->physician()->create();
    PatientRecord::factory()->for($physician, 'owner')->create(['name' => 'Ali Khan', 'medical_record_number' => 'MRN-111']);
    PatientRecord::factory()->for($physician, 'owner')->create(['name' => 'Sara Ahmed', 'medical_record_number' => 'MRN-222']);
    PatientRecord::factory()->create(['name' => 'Someone Elses']);

    Livewire::actingAs($physician)->test(PatientRecordIndex::class)
        ->assertSee('Ali Khan')
        ->assertSee('Sara Ahmed')
        ->assertDontSee('Someone Elses')
        ->set('search', 'MRN-222')
        ->assertSee('Sara Ahmed')
        ->assertDontSee('Ali Khan');
});

test('the package limit stops new records but not access to existing ones', function () {
    $physician = User::factory()->physician()->withLimits(['max_patient_records' => 1])->create();
    $record = PatientRecord::factory()->for($physician, 'owner')->create();

    Livewire::actingAs($physician)->test(PatientRecordIndex::class)
        ->assertDontSee('New patient')
        ->assertSee('You already have 1 patient record');

    Livewire::actingAs($physician)->test(PatientRecordForm::class)
        ->set('name', 'One Too Many')
        ->call('save')
        ->assertHasErrors('limit');

    expect(PatientRecord::count())->toBe(1);

    Livewire::actingAs($physician)->test(PatientRecordForm::class, ['patientRecord' => $record])
        ->set('name', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($record->refresh()->name)->toBe('Renamed');
});

test('deleting a record frees room in the package', function () {
    $physician = User::factory()->physician()->withLimits(['max_patient_records' => 1])->create();
    $record = PatientRecord::factory()->for($physician, 'owner')->create();

    Livewire::actingAs($physician)->test(PatientRecordShow::class, ['patientRecord' => $record])
        ->call('delete')
        ->assertRedirect(route('patient-records.index'));

    Livewire::actingAs($physician)->test(PatientRecordForm::class)
        ->set('name', 'Next Patient')
        ->call('save')
        ->assertHasNoErrors();

    expect(PatientRecord::sole()->name)->toBe('Next Patient');
});

test('a physician whose package ended can still review records but not add new ones', function () {
    $physician = User::factory()->physician()->withoutPackage()->create();
    $record = PatientRecord::factory()->for($physician, 'owner')->create(['diagnosis' => 'Stable angina']);

    $this->actingAs($physician)->get(route('patient-records.show', $record))
        ->assertOk()
        ->assertSee('Stable angina');

    Livewire::actingAs($physician)->test(PatientRecordForm::class)
        ->set('name', 'New Patient')
        ->call('save')
        ->assertHasErrors('limit');
});

test('a physician can add, edit and delete dated notes', function () {
    [$physician, $record] = physicianWithRecord();

    $component = Livewire::actingAs($physician)->test(PatientNotes::class, ['patientRecord' => $record])
        ->set('notedOn', '2026-10-01')
        ->set('body', 'Chest pain on exertion')
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertSee('Chest pain on exertion');

    $note = PatientNote::sole();
    expect($note->noted_on->toDateString())->toBe('2026-10-01');

    $component->call('startEditing', $note->id)
        ->assertSet('editingBody', 'Chest pain on exertion')
        ->set('editingBody', 'Pain resolved')
        ->set('editingNotedOn', '2026-10-02')
        ->call('saveEdit')
        ->assertHasNoErrors()
        ->assertSee('Pain resolved');

    expect($note->refresh())
        ->body->toBe('Pain resolved')
        ->noted_on->toDateString()->toBe('2026-10-02');

    $component->call('deleteNote', $note->id);

    expect(PatientNote::count())->toBe(0);
});

test('a note needs a date and text', function () {
    [$physician, $record] = physicianWithRecord();

    Livewire::actingAs($physician)->test(PatientNotes::class, ['patientRecord' => $record])
        ->set('notedOn', '')
        ->set('body', '')
        ->call('addNote')
        ->assertHasErrors(['notedOn', 'body']);
});

test('a note on another record cannot be reached from this one', function () {
    [$physician, $record] = physicianWithRecord();
    $otherNote = PatientNote::factory()->create();

    Livewire::actingAs($physician)->test(PatientNotes::class, ['patientRecord' => $record])
        ->call('deleteNote', $otherNote->id)
        ->assertNotFound();

    expect(PatientNote::whereKey($otherNote->id)->exists())->toBeTrue();
});

test('adding a note marks the record as changed', function () {
    [$physician, $record] = physicianWithRecord();
    $record->timestamps = false;
    $record->forceFill(['updated_at' => now()->subWeek()])->save();

    Livewire::actingAs($physician)->test(PatientNotes::class, ['patientRecord' => $record->fresh()])
        ->set('body', 'Follow-up')
        ->call('addNote');

    expect($record->fresh()->updated_at->isToday())->toBeTrue();
});

test('a record can have no studies or several, from those the physician can open', function () {
    [$physician, $record] = physicianWithRecord();
    $ownStudy = Study::factory()->for($physician, 'uploader')->create(['study_description' => 'First angiogram']);
    $sharedStudy = Study::factory()->create(['study_description' => 'Second angiogram']);
    StudyShare::factory()->for($sharedStudy)->create(['user_id' => $physician->id]);

    $component = Livewire::actingAs($physician)->test(PatientStudies::class, ['patientRecord' => $record])
        ->assertSee('No studies attached.')
        ->call('startLinking')
        ->assertSee('First angiogram')
        ->assertSee('Second angiogram')
        ->call('link', $ownStudy->id)
        ->call('link', $sharedStudy->id);

    expect($record->linkedStudies()->pluck('study_id')->sort()->values()->all())
        ->toBe([$ownStudy->id, $sharedStudy->id]);

    $component->assertDontSee('No studies attached.');
});

test('a study cannot be attached twice', function () {
    [$physician, $record] = physicianWithRecord();
    $study = Study::factory()->for($physician, 'uploader')->create();

    Livewire::actingAs($physician)->test(PatientStudies::class, ['patientRecord' => $record])
        ->call('link', $study->id)
        ->call('link', $study->id);

    expect($record->linkedStudies()->count())->toBe(1);
});

test('a study the physician cannot open cannot be offered or attached', function () {
    [$physician, $record] = physicianWithRecord();
    $otherStudy = Study::factory()->create(['study_description' => 'Someone elses angiogram']);

    Livewire::actingAs($physician)->test(PatientStudies::class, ['patientRecord' => $record])
        ->call('startLinking')
        ->assertDontSee('Someone elses angiogram')
        ->call('link', $otherStudy->id)
        ->assertNotFound();

    expect($record->linkedStudies()->count())->toBe(0);
});

test('removing a study from a record does not delete the study', function () {
    [$physician, $record] = physicianWithRecord();
    $study = Study::factory()->for($physician, 'uploader')->create();
    $link = PatientRecordStudy::factory()->for($record)->create(['study_id' => $study->id]);

    Livewire::actingAs($physician)->test(PatientStudies::class, ['patientRecord' => $record])
        ->call('unlink', $link->id);

    expect($record->linkedStudies()->count())->toBe(0)
        ->and($study->fresh())->not->toBeNull();
});

test('a link from another record cannot be removed from this one', function () {
    [$physician, $record] = physicianWithRecord();
    $otherLink = PatientRecordStudy::factory()->create();

    Livewire::actingAs($physician)->test(PatientStudies::class, ['patientRecord' => $record])
        ->call('unlink', $otherLink->id)
        ->assertNotFound();

    expect(PatientRecordStudy::whereKey($otherLink->id)->exists())->toBeTrue();
});

test('a deleted study stays on the record as no longer available', function () {
    [$physician, $record] = physicianWithRecord();
    $study = Study::factory()->for($physician, 'uploader')->create(['study_description' => 'Coronary angiogram', 'study_date' => '2026-10-12']);

    Livewire::actingAs($physician)->test(PatientStudies::class, ['patientRecord' => $record])
        ->call('link', $study->id);

    app(StudyDeleter::class)->delete($study);

    Livewire::actingAs($physician)->test(PatientStudies::class, ['patientRecord' => $record])
        ->assertSee('Coronary angiogram')
        ->assertSee('2026-10-12')
        ->assertSee('No longer available');
});

test('a study whose share was withdrawn cannot be opened from the record', function () {
    [$physician, $record] = physicianWithRecord();
    $study = Study::factory()->create(['study_description' => 'Shared angiogram']);
    $share = StudyShare::factory()->for($study)->create(['user_id' => $physician->id]);

    Livewire::actingAs($physician)->test(PatientStudies::class, ['patientRecord' => $record])
        ->call('link', $study->id)
        ->assertSeeHtml(route('studies.show', $study));

    $share->delete();

    Livewire::actingAs($physician)->test(PatientStudies::class, ['patientRecord' => $record])
        ->assertSee('Shared angiogram')
        ->assertSee('No longer available')
        ->assertDontSeeHtml(route('studies.show', $study));
});

test('deleting a record keeps its studies and removes its notes', function () {
    [$physician, $record] = physicianWithRecord();
    $study = Study::factory()->for($physician, 'uploader')->create();
    PatientRecordStudy::factory()->for($record)->create(['study_id' => $study->id]);
    PatientNote::factory()->for($record)->create();

    Livewire::actingAs($physician)->test(PatientRecordShow::class, ['patientRecord' => $record])->call('delete');

    expect(PatientRecord::count())->toBe(0)
        ->and(PatientNote::count())->toBe(0)
        ->and(PatientRecordStudy::count())->toBe(0)
        ->and($study->fresh())->not->toBeNull();
});

test('deleting a physician deletes their records', function () {
    [$physician, $record] = physicianWithRecord();
    PatientNote::factory()->for($record)->create();

    app(UserDeleter::class)->delete($physician);

    expect(PatientRecord::count())->toBe(0)
        ->and(PatientNote::count())->toBe(0);
});

test('the record page shows the clinical details and escapes what was typed', function () {
    [$physician, $record] = physicianWithRecord();
    $record->update(['name' => '<script>alert(1)</script>', 'diagnosis' => '<img src=x onerror=alert(2)>']);

    $this->actingAs($physician)->get(route('patient-records.show', $record))
        ->assertOk()
        ->assertSee('Diagnosis')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x onerror=alert(2)>', false);
});

test('administrators see how many records a physician keeps, never their contents', function () {
    [$physician, $record] = physicianWithRecord();
    $record->update(['name' => 'Private Patient Name', 'diagnosis' => 'Private diagnosis']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('users.show', $physician))
        ->assertOk()
        ->assertSee('Patient records')
        ->assertDontSee('Private Patient Name')
        ->assertDontSee('Private diagnosis');
});

test('the PDF has the record, its studies and notes, and can leave out the notes', function () {
    [$physician, $record] = physicianWithRecord();
    $record->update(['diagnosis' => 'Triple vessel disease']);
    PatientRecordStudy::factory()->for($record)->create(['study_description' => 'Left heart cath']);
    PatientNote::factory()->for($record)->create(['body' => 'Private thoughts on the case']);

    $wrapper = app('dompdf.wrapper');
    $renderedPages = [];

    Pdf::shouldReceive('loadView')
        ->twice()
        ->andReturnUsing(function (string $view, array $data) use ($wrapper, &$renderedPages) {
            $renderedPages[] = view($view, $data)->render();

            return $wrapper->loadHTML('<p>PDF</p>');
        });

    $this->actingAs($physician);
    $this->get(route('patient-records.pdf', $record))->assertOk();
    $this->get(route('patient-records.pdf', ['patientRecord' => $record, 'notes' => 0]))->assertOk();

    expect($renderedPages[0])->toContain('Triple vessel disease', 'Left heart cath', 'Private thoughts on the case')
        ->and($renderedPages[1])->toContain('Triple vessel disease', 'Left heart cath')
        ->and($renderedPages[1])->not->toContain('Private thoughts on the case');
});

test('the owner gets a private PDF', function () {
    [$physician, $record] = physicianWithRecord();
    PatientNote::factory()->for($record)->create();

    $response = $this->actingAs($physician)->get(route('patient-records.pdf', $record));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
});

test('physician credentials only print once the physician is verified', function () {
    $physician = User::factory()->unverifiedPhysician()->create(['registration_number' => 'PMC-777']);
    $record = PatientRecord::factory()->for($physician, 'owner')->create();

    $html = view('patient-records.pdf', ['record' => $record, 'owner' => $physician, 'linkedStudies' => collect(), 'notes' => collect()])->render();
    expect($html)->not->toContain('PMC-777');

    $physician->markPhysicianVerified();

    $html = view('patient-records.pdf', ['record' => $record, 'owner' => $physician, 'linkedStudies' => collect(), 'notes' => collect()])->render();
    expect($html)->toContain('PMC-777');
});

test('administrators can set the patient record limit, and a package that starts keeps a copy of it', function () {
    $package = Package::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(PackageIndex::class)
        ->call('edit', $package->id)
        ->set('maxPatientRecords', '25')
        ->call('save')
        ->assertHasNoErrors();

    expect($package->refresh()->max_patient_records)->toBe(25);

    $physician = User::factory()->physician()->withoutPackage()->create();
    $subscription = app(SubscriptionManager::class)->assign($physician, $package, User::factory()->admin()->create());
    $package->update(['max_patient_records' => 1]);

    expect($subscription->refresh()->limit(Quota::PatientRecords))->toBe(25);
});

test('only physicians are shown the patient record limit', function () {
    $physicianPackage = Package::factory()->create();
    $technicianPackage = Package::factory()->forRole(Role::Technician)->create();

    expect(view('billing.partials.limits', ['limits' => $physicianPackage])->render())->toContain('Patient records')
        ->and(view('billing.partials.limits', ['limits' => $technicianPackage])->render())->not->toContain('Patient records');
});
