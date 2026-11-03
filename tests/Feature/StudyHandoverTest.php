<?php

use App\Livewire\Studies\PendingShares;
use App\Livewire\Studies\StudyShow;
use App\Models\DicomInstance;
use App\Models\GuestLink;
use App\Models\Series;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use App\Notifications\StudyReleased;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\QuotaExceededException;
use App\Services\StudyHandover;
use App\Services\StudySharing;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('dicom');
    Notification::fake();
});

/**
 * A study with one image, owned by the given user and shared with the given physicians.
 *
 * @param  list<User>  $physicians
 */
function studySharedWith(User $owner, array $physicians, array $studyAttributes = []): Study
{
    $study = Study::factory()->for($owner, 'uploader')->create($studyAttributes);
    DicomInstance::factory()->for(Series::factory()->for($study))->create(['uploaded_by' => $owner->id, 'file_size' => 1024]);

    foreach ($physicians as $physician) {
        StudyShare::factory()->for($study)->create(['user_id' => $physician->id, 'shared_by' => $owner->id]);
    }

    return $study;
}

function handover(): StudyHandover
{
    return app(StudyHandover::class);
}

test('an owner deleting a shared study hands it to the physicians who can open it, who are asked whether to keep it', function () {
    $this->freezeSecond();
    $technician = User::factory()->technician()->create();
    [$cardiologist, $surgeon] = User::factory()->physician()->count(2)->create();
    $study = studySharedWith($technician, [$cardiologist, $surgeon]);
    $pending = StudyShare::factory()->pending()->for($study)->create(['shared_by' => $technician->id]);
    GuestLink::factory()->for($study)->create(['created_by' => $technician->id]);

    Livewire::actingAs($technician)->test(StudyShow::class, ['study' => $study])
        ->call('delete')
        ->assertRedirect(route('studies.index'));

    $study->refresh();
    expect($study->uploaded_by)->toBeNull()
        ->and($study->released_by)->toBe($technician->id)
        ->and($study->handover_ends_at->equalTo(now()->addDays(config('studies.handover_days'))))->toBeTrue()
        ->and(DicomInstance::sole()->uploaded_by)->toBeNull()
        ->and(app(PackageLimits::class)->storageUsed($technician))->toBe(0)
        ->and($technician->can('view', $study))->toBeFalse()
        ->and($cardiologist->can('view', $study))->toBeTrue()
        ->and($cardiologist->can('keep', $study))->toBeTrue()
        ->and($cardiologist->can('share', $study))->toBeFalse();
    $this->assertModelMissing($pending);
    expect(GuestLink::count())->toBe(0)
        ->and($cardiologist->openRequestCount())->toBe(1);

    Notification::assertSentTo([$cardiologist, $surgeon], StudyReleased::class);
    Notification::assertNotSentTo($pending->recipient, StudyReleased::class);
});

test('an owner deleting a study nobody else can open deletes it at once', function () {
    $study = studySharedWith(User::factory()->technician()->create(), []);

    expect(handover()->release($study))->toBeFalse();

    $this->assertModelMissing($study);
    Notification::assertNothingSent();
});

test('an administrator deleting someone else\'s shared study deletes it for everyone', function () {
    $study = studySharedWith(User::factory()->technician()->create(), [User::factory()->physician()->create()]);

    Livewire::actingAs(User::factory()->admin()->create())->test(StudyShow::class, ['study' => $study])->call('delete');

    $this->assertModelMissing($study);
    expect(StudyShare::count())->toBe(0);
});

test('the first physician to keep the study becomes its owner, and the others keep their access through them', function () {
    $technician = User::factory()->technician()->create();
    [$cardiologist, $surgeon, $anaesthetist] = User::factory()->physician()->count(3)->create();
    $study = studySharedWith($technician, [$cardiologist, $surgeon]);
    allowSender($anaesthetist, $surgeon);
    app(StudySharing::class)->share($study, $surgeon, $anaesthetist);
    handover()->release($study);

    Livewire::actingAs($cardiologist)->test(StudyShow::class, ['study' => $study])
        ->assertSee('Keep study')
        ->call('keep')
        ->assertRedirect(route('studies.show', $study));

    $study->refresh();
    expect($study->uploaded_by)->toBe($cardiologist->id)
        ->and($study->isAwaitingHandover())->toBeFalse()
        ->and(DicomInstance::sole()->uploaded_by)->toBe($cardiologist->id)
        ->and($cardiologist->can('delete', $study))->toBeTrue()
        ->and($study->shares()->pluck('shared_by', 'user_id')->all())->toEqual([
            $surgeon->id => $cardiologist->id,
            $anaesthetist->id => $surgeon->id,
        ])
        ->and($surgeon->can('view', $study))->toBeTrue()
        ->and($surgeon->can('keep', $study))->toBeFalse();

    expect(fn () => handover()->keep($study, $surgeon))->toThrow(InvalidArgumentException::class);
});

test('a physician without room in their package cannot keep the study, and it stays open to the others', function () {
    $physician = User::factory()->physician()->withLimits(['max_uploads' => 0])->create();
    $study = studySharedWith(User::factory()->technician()->create(), [$physician]);
    handover()->release($study);

    Livewire::actingAs($physician)->test(PendingShares::class)
        ->call('keep', $study->id)
        ->assertHasErrors("handover.{$study->id}");

    expect($study->fresh()->isAwaitingHandover())->toBeTrue()
        ->and(fn () => handover()->keep($study, $physician))->toThrow(QuotaExceededException::class);
});

test('a physician who already has their own copy of the study cannot keep this one', function () {
    $physician = User::factory()->physician()->create();
    $study = studySharedWith(User::factory()->technician()->create(), [$physician]);
    Study::factory()->for($physician, 'uploader')->create(['study_instance_uid' => $study->study_instance_uid]);
    handover()->release($study);

    expect(fn () => handover()->keep($study, $physician))->toThrow(InvalidArgumentException::class, 'own copy');
});

test('physicians can keep or remove studies awaiting handover from their requests', function () {
    [$cardiologist, $surgeon] = User::factory()->physician()->count(2)->create();
    $study = studySharedWith(User::factory()->patient()->create(), [$cardiologist, $surgeon], ['study_description' => 'CORONARY ANGIO']);
    handover()->release($study);

    Livewire::actingAs($surgeon)->test(PendingShares::class)
        ->assertSee('CORONARY ANGIO')
        ->call('remove', $study->id)
        ->assertSet('status', 'Removed the study from your list.')
        ->assertDontSee('CORONARY ANGIO');

    Livewire::actingAs($cardiologist)->test(PendingShares::class)
        ->call('keep', $study->id)
        ->assertHasNoErrors();

    expect($surgeon->can('view', $study->fresh()))->toBeFalse()
        ->and($study->fresh()->uploaded_by)->toBe($cardiologist->id);
});

test('an expired patient study is offered to the physicians who can open it, and the patient can upload another', function () {
    $patient = User::factory()->patient()->create();
    $physician = User::factory()->physician()->create();
    $study = studySharedWith($patient, [$physician], ['expires_at' => now()->subMinute()]);

    $this->artisan('studies:delete-expired')
        ->expectsOutput('Offered 1 expired study to physicians to keep.')
        ->assertSuccessful();

    $study->refresh();
    expect($study->isAwaitingHandover())->toBeTrue()
        ->and($study->expires_at)->toBeNull()
        ->and($patient->uploadedStudies()->count())->toBe(0)
        ->and($physician->can('view', $study))->toBeTrue();
    Notification::assertSentTo($physician, StudyReleased::class, fn (StudyReleased $notification) => $notification->expired);

    handover()->keep($study, $physician);

    expect($study->fresh()->expires_at)->toBeNull();
});

test('studies nobody keeps in time, or that nobody can open any more, are deleted with their files', function () {
    $physician = User::factory()->physician()->create();
    $unclaimed = studySharedWith(User::factory()->technician()->create(), [$physician]);
    $removedByEveryone = studySharedWith(User::factory()->technician()->create(), [$physician]);
    $stillOpen = studySharedWith(User::factory()->technician()->create(), [$physician]);
    Storage::disk('dicom')->put($unclaimed->instances()->sole()->file_path, 'bytes');

    foreach ([$unclaimed, $removedByEveryone, $stillOpen] as $study) {
        handover()->release($study);
    }

    app(StudySharing::class)->removeFromList($removedByEveryone, $physician);
    $this->travel(config('studies.handover_days'))->days();
    $stillOpen->update(['handover_ends_at' => now()->addDay()]);

    $this->artisan('studies:delete-expired')->expectsOutput('Deleted 2 studies.')->assertSuccessful();

    $this->assertModelMissing($unclaimed);
    $this->assertModelMissing($removedByEveryone);
    $this->assertModelExists($stillOpen);
    expect(Storage::disk('dicom')->allFiles())->toBe([]);
});

test('owners are told that physicians may keep a copy of a study they share', function () {
    $study = Study::factory()->create();

    Livewire::actingAs($study->uploader)->test(StudyShow::class, ['study' => $study])
        ->assertSee('Physicians you share this study with may keep their own copy if you delete it later.');
});
