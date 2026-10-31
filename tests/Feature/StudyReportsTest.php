<?php

use App\Enums\Role;
use App\Livewire\Guest\GuestStudy;
use App\Livewire\Studies\StudyReports;
use App\Models\GuestLink;
use App\Models\Report;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('dicom');
});

/**
 * A study uploaded by a technician and shared with a physician, who can report on it.
 *
 * @return array{0: Study, 1: User}
 */
function sharedStudyWithPhysician(): array
{
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    $physician = User::factory()->physician()->create();
    StudyShare::factory()->for($study)->create(['user_id' => $physician->id]);

    return [$study, $physician];
}

test('physicians who can open a study can write reports on it', function () {
    [$study, $physician] = sharedStudyWithPhysician();

    expect($physician->can('create', [Report::class, $study]))->toBeTrue()
        ->and($physician->can('create', [Report::class, Study::factory()->for($physician, 'uploader')->create()]))->toBeTrue()
        ->and($physician->can('create', [Report::class, Study::factory()->create()]))->toBeFalse()
        ->and($physician->can('create', [Report::class, Study::factory()->for($physician, 'uploader')->create(['expires_at' => now()->subMinute()])]))->toBeFalse();
});

test('only physicians can write reports, even on their own studies', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);
    $study = Study::factory()->for($user, 'uploader')->create();

    expect($user->can('create', [Report::class, $study]))->toBeFalse();

    Livewire::actingAs($user)->test(StudyReports::class, ['study' => $study])
        ->assertDontSee('Write a report')
        ->call('startReport')
        ->assertForbidden();
})->with([Role::Technician, Role::Patient, Role::Admin]);

test('saving a draft requires findings and an impression', function () {
    [$study, $physician] = sharedStudyWithPhysician();

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('startReport')
        ->call('saveDraft')
        ->assertHasErrors(['findings' => 'required', 'impression' => 'required'])
        ->assertHasNoErrors('indication');

    expect(Report::count())->toBe(0);
});

test('a draft is saved for its author, who can edit it, and is hidden from everyone else', function () {
    [$study, $physician] = sharedStudyWithPhysician();
    $colleague = User::factory()->physician()->create();
    StudyShare::factory()->for($study)->create(['user_id' => $colleague->id]);

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('startReport')
        ->set('indication', 'Chest pain')
        ->set('findings', 'Mild LAD narrowing')
        ->set('impression', 'Single vessel disease')
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertSee('Mild LAD narrowing')
        ->assertSee('only you can see this');

    $report = Report::sole();
    expect($report->author_id)->toBe($physician->id)
        ->and($report->isFinal())->toBeFalse();

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('editDraft', $report->id)
        ->assertSet('findings', 'Mild LAD narrowing')
        ->set('findings', 'Moderate LAD narrowing')
        ->call('saveDraft');

    expect($report->fresh()->findings)->toBe('Moderate LAD narrowing')
        ->and(Report::count())->toBe(1);

    foreach ([$colleague, $study->uploader] as $other) {
        Livewire::actingAs($other)->test(StudyReports::class, ['study' => $study])
            ->assertDontSee('Moderate LAD narrowing')
            ->call('editDraft', $report->id)
            ->assertNotFound();
    }
});

test('finalizing locks the report, records the author credentials and shows it to everyone with access', function () {
    [$study, $physician] = sharedStudyWithPhysician();
    $physician->update(['qualifications' => 'MBBS, FCPS', 'registration_number' => 'PMDC-123']);
    $report = Report::factory()->for($study)->create(['author_id' => $physician->id, 'impression' => 'Normal coronaries']);

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('finalize', $report->id)
        ->assertSee('Report finalized');

    $physician->update(['name' => 'Renamed Doctor', 'registration_number' => 'PMDC-999']);
    $report->refresh();

    expect($report->isFinal())->toBeTrue()
        ->and($report->author_qualifications)->toBe('MBBS, FCPS')
        ->and($report->author_registration_number)->toBe('PMDC-123')
        ->and($report->authorName())->not->toBe('Renamed Doctor');

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('editDraft', $report->id)
        ->assertForbidden();

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('discardDraft', $report->id)
        ->assertForbidden();

    Livewire::actingAs($study->uploader)->test(StudyReports::class, ['study' => $study])
        ->assertSee('Normal coronaries')
        ->assertDontSee('Add addendum');
});

test('a new report can be saved and finalized in one step', function () {
    [$study, $physician] = sharedStudyWithPhysician();

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('startReport')
        ->set('findings', 'Patent grafts')
        ->set('impression', 'No significant disease')
        ->call('saveAndFinalize')
        ->assertHasNoErrors();

    expect(Report::sole()->isFinal())->toBeTrue();
});

test('drafts can be discarded by their author', function () {
    [$study, $physician] = sharedStudyWithPhysician();
    $report = Report::factory()->for($study)->create(['author_id' => $physician->id]);

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('discardDraft', $report->id)
        ->assertSee('Draft discarded.');

    expect(Report::count())->toBe(0);
});

test('authors who lose access to the study can no longer edit their drafts', function () {
    [$study, $physician] = sharedStudyWithPhysician();
    $report = Report::factory()->for($study)->create(['author_id' => $physician->id]);

    $study->shares()->delete();

    expect($physician->can('update', $report))->toBeFalse()
        ->and($physician->can('view', $report))->toBeFalse();
});

test('only the author can add addenda to a final report, which everyone with access sees', function () {
    [$study, $physician] = sharedStudyWithPhysician();
    $report = Report::factory()->final()->for($study)->create(['author_id' => $physician->id]);
    $colleague = User::factory()->physician()->create();
    StudyShare::factory()->for($study)->create(['user_id' => $colleague->id]);

    Livewire::actingAs($colleague)->test(StudyReports::class, ['study' => $study])
        ->assertDontSee('Add addendum')
        ->call('startAddendum', $report->id)
        ->assertForbidden();

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('startAddendum', $report->id)
        ->call('saveAddendum')
        ->assertHasErrors(['addendum' => 'required'])
        ->set('addendum', 'Correction: RCA is dominant.')
        ->call('saveAddendum')
        ->assertHasNoErrors()
        ->assertSee('Addendum added.');

    expect($report->addenda()->sole()->body)->toBe('Correction: RCA is dominant.');

    Livewire::actingAs($study->uploader)->test(StudyReports::class, ['study' => $study])
        ->assertSee('Correction: RCA is dominant.');
});

test('drafts cannot receive addenda', function () {
    [$study, $physician] = sharedStudyWithPhysician();
    $report = Report::factory()->for($study)->create(['author_id' => $physician->id]);

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('startAddendum', $report->id)
        ->assertForbidden();
});

test('the study page shows the reports panel', function () {
    [$study, $physician] = sharedStudyWithPhysician();

    $this->actingAs($physician)->get(route('studies.show', $study))
        ->assertOk()
        ->assertSeeLivewire(StudyReports::class)
        ->assertSee('Write a report');
});

test('everyone who can open the study can download a final report as a PDF', function () {
    [$study, $physician] = sharedStudyWithPhysician();
    $report = Report::factory()->final()->for($study)->create(['author_id' => $physician->id]);

    foreach ([$physician, $study->uploader, User::factory()->admin()->create()] as $user) {
        $this->actingAs($user)->get(route('reports.pdf', $report))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    $this->actingAs(User::factory()->physician()->create())->get(route('reports.pdf', $report))->assertForbidden();
});

test('a draft PDF can only be previewed by its author', function () {
    [$study, $physician] = sharedStudyWithPhysician();
    $report = Report::factory()->for($study)->create(['author_id' => $physician->id]);

    $this->actingAs($physician)->get(route('reports.pdf', $report))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($study->uploader)->get(route('reports.pdf', $report))->assertForbidden();
});

test('the PDF includes the signature image of the author', function () {
    Storage::fake(User::SIGNATURE_DISK);
    [$study, $physician] = sharedStudyWithPhysician();
    $path = UploadedFile::fake()->image('signature.png', 200, 80)->store('signatures', User::SIGNATURE_DISK);
    $physician->update(['signature_path' => $path]);

    expect($physician->signatureDataUri())->toStartWith('data:image/png;base64,');

    $report = Report::factory()->final()->for($study)->create(['author_id' => $physician->id]);

    $this->actingAs($physician)->get(route('reports.pdf', $report))->assertOk();
});

test('guests see final reports, but not drafts, and can download them as PDFs', function () {
    [$study, $physician] = sharedStudyWithPhysician();
    $final = Report::factory()->final()->for($study)->create(['author_id' => $physician->id, 'impression' => 'Final impression text']);
    $draft = Report::factory()->for($study)->create(['author_id' => $physician->id, 'impression' => 'Draft impression text']);
    $otherStudyReport = Report::factory()->final()->create();
    $token = Str::random(48);
    GuestLink::factory()->for($study)->withToken($token)->create(['created_by' => $physician->id]);

    $this->get(route('guest.show', $token))
        ->assertOk()
        ->assertSee('Final impression text')
        ->assertDontSee('Draft impression text');

    $this->get(route('guest.reports.pdf', [$token, $final]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('guest.reports.pdf', [$token, $draft]))->assertNotFound();
    $this->get(route('guest.reports.pdf', [$token, $otherStudyReport]))->assertNotFound();
    $this->get(route('guest.reports.pdf', [Str::random(48), $final]))->assertNotFound();
});

test('guests must enter the PIN before reading reports', function () {
    [$study, $physician] = sharedStudyWithPhysician();
    $report = Report::factory()->final()->for($study)->create(['author_id' => $physician->id, 'impression' => 'Final impression text']);
    $token = Str::random(48);
    GuestLink::factory()->for($study)->withToken($token)->withPin('123456')->create(['created_by' => $physician->id]);

    Livewire::test(GuestStudy::class, ['token' => $token])->assertDontSee('Final impression text');
    $this->get(route('guest.reports.pdf', [$token, $report]))->assertNotFound();
});
