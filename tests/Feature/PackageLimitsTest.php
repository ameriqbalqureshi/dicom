<?php

use App\Enums\Quota;
use App\Livewire\Studies\GuestLinks;
use App\Livewire\Studies\ShareStudy;
use App\Livewire\Studies\StudyReports;
use App\Livewire\Studies\UploadStudies;
use App\Models\Package;
use App\Models\Report;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\QuotaExceededException;
use App\Services\Billing\SubscriptionManager;
use App\Services\StudySharing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\DicomFileBuilder;

beforeEach(function () {
    Storage::fake('dicom');
});

/**
 * A DICOM file belonging to the given study.
 */
function dicomFileInStudy(string $studyUid, string $sopUid): UploadedFile
{
    $builder = (new DicomFileBuilder)->with(0x0020, 0x000D, 'UI', $studyUid)->with(0x0008, 0x0018, 'UI', $sopUid);

    return UploadedFile::fake()->createWithContent("{$sopUid}.dcm", $builder->build());
}

function subscriptionOf(User $user): Subscription
{
    return $user->subscriptions()->sole();
}

test('the package limits how many studies a user keeps, and more files for a kept study are free', function () {
    $technician = User::factory()->technician()->withLimits(['max_uploads' => 1])->create();

    Livewire::actingAs($technician)->test(UploadStudies::class)
        ->set('files', [dicomFileInStudy('1.2.3', '1.2.3.1'), dicomFileInStudy('1.2.3', '1.2.3.2')])
        ->assertSee('Imported.')
        ->set('files', [dicomFileInStudy('1.2.4', '1.2.4.1')])
        ->assertSee('You already have 1 study, the most your');

    expect(Study::count())->toBe(1)
        ->and(subscriptionOf($technician)->used(Quota::Uploads))->toBe(1);

    Livewire::actingAs($technician)->test(UploadStudies::class)
        ->set('files', [dicomFileInStudy('1.2.3', '1.2.3.3')])
        ->assertSee('Imported.');
});

test('deleting a study makes room for another', function () {
    $technician = User::factory()->technician()->withLimits(['max_uploads' => 1])->create();

    Livewire::actingAs($technician)->test(UploadStudies::class)->set('files', [dicomFileInStudy('1.2.3', '1.2.3.1')]);
    Study::sole()->delete();

    Livewire::actingAs($technician)->test(UploadStudies::class)
        ->set('files', [dicomFileInStudy('1.2.4', '1.2.4.1')])
        ->assertSee('Imported.');

    expect(Study::sole()->study_instance_uid)->toBe('1.2.4');
});

test('studies kept under an earlier package still count after renewing', function () {
    $physician = User::factory()->physician()->withLimits(['max_uploads' => 1])->create();
    Livewire::actingAs($physician)->test(UploadStudies::class)->set('files', [dicomFileInStudy('1.2.3', '1.2.3.1')]);

    app(SubscriptionManager::class)->assign($physician, Package::factory()->create(['max_uploads' => 1]), User::factory()->admin()->create());

    Livewire::actingAs($physician)->test(UploadStudies::class)
        ->set('files', [dicomFileInStudy('1.2.4', '1.2.4.1')])
        ->assertSee('You already have 1 study, the most your');

    expect(Study::count())->toBe(1);
});

test('files that would go over the storage limit are refused', function () {
    $technician = User::factory()->technician()->withLimits(['max_storage_mb' => 0])->create();

    Livewire::actingAs($technician)->test(UploadStudies::class)
        ->set('files', [dicomFileInStudy('1.2.3', '1.2.3.1')])
        ->assertSee('exceed the 0 MB of storage');

    expect(Study::count())->toBe(0);
    Storage::disk('dicom')->assertDirectoryEmpty('/');
});

test('users without a current package cannot upload', function (User $user) {
    Livewire::actingAs($user)->test(UploadStudies::class)
        ->assertSee('You do not have an active package')
        ->set('files', [dicomFileInStudy('1.2.3', '1.2.3.1')])
        ->assertSee('Choose one on the Package page');

    expect(Study::count())->toBe(0);
})->with([
    'no package' => fn () => User::factory()->technician()->withoutPackage()->create(),
    'package ended' => fn () => Subscription::factory()->ended()->create()->user,
]);

test('administrators are not limited by packages', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(UploadStudies::class)
        ->set('files', [dicomFileInStudy('1.2.3', '1.2.3.1')])
        ->assertSee('Imported.');
});

test('sharing counts against the sharer package', function () {
    $owner = User::factory()->technician()->withLimits(['max_shares' => 1])->create();
    $study = Study::factory()->for($owner, 'uploader')->create();
    $sharing = app(StudySharing::class);

    $sharing->share($study, $owner, User::factory()->physician()->create());

    expect(subscriptionOf($owner)->shares_used)->toBe(1)
        ->and(fn () => $sharing->share($study, $owner, User::factory()->physician()->create()))
        ->toThrow(QuotaExceededException::class, 'You have used all 1 shares');

    expect($study->shares()->count())->toBe(1);
});

test('the share panel explains when the share limit is reached', function () {
    $owner = User::factory()->technician()->withLimits(['max_shares' => 0])->create();
    $physician = User::factory()->physician()->create(['name' => 'Jane Cardiologist']);

    Livewire::actingAs($owner)->test(ShareStudy::class, ['study' => Study::factory()->for($owner, 'uploader')->create()])
        ->call('share', $physician->id)
        ->assertSee('does not include shares');

    expect(StudyShare::count())->toBe(0);
});

test('creating guest links counts against the package', function () {
    $physician = User::factory()->physician()->withLimits(['max_guest_links' => 1])->create();
    $study = Study::factory()->for($physician, 'uploader')->create();

    Livewire::actingAs($physician)->test(GuestLinks::class, ['study' => $study])
        ->call('create')
        ->assertSee('Copy it now')
        ->call('dismissIssued')
        ->call('create')
        ->assertSee('You have used all 1 guest links');

    expect($study->guestLinks()->count())->toBe(1)
        ->and(subscriptionOf($physician)->guest_links_used)->toBe(1);
});

test('signing reports counts against the package, and a report over the limit stays a draft', function () {
    $physician = User::factory()->physician()->withLimits(['max_reports' => 1])->create();
    $study = Study::factory()->for($physician, 'uploader')->create();
    [$first, $second] = Report::factory()->count(2)->for($study)->create(['author_id' => $physician->id]);

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('finalize', $first->id)
        ->assertSee('Report finalized')
        ->call('finalize', $second->id)
        ->assertSee('You have used all 1 reports signed');

    expect($first->fresh()->isFinal())->toBeTrue()
        ->and($second->fresh()->isFinal())->toBeFalse()
        ->and(subscriptionOf($physician)->reports_used)->toBe(1);
});

test('a new report over the limit is kept as a draft', function () {
    $physician = User::factory()->physician()->withLimits(['max_reports' => 0])->create();
    $study = Study::factory()->for($physician, 'uploader')->create();

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('startReport')
        ->set('findings', 'Normal')
        ->set('impression', 'Normal')
        ->call('saveAndFinalize')
        ->assertSee('Draft saved, but not finalized');

    expect(Report::sole()->isFinal())->toBeFalse();
});

test('unverified physicians cannot be found or shared with', function () {
    $owner = User::factory()->technician()->create();
    $unverified = User::factory()->unverifiedPhysician()->create(['name' => 'Jane Unverified']);
    $study = Study::factory()->for($owner, 'uploader')->create();

    Livewire::actingAs($owner)->test(ShareStudy::class, ['study' => $study])
        ->set('search', 'jane')
        ->assertDontSee('Jane Unverified');

    expect(fn () => app(StudySharing::class)->share($study, $owner, $unverified))
        ->toThrow(InvalidArgumentException::class, 'verified physicians');
});

test('unverified physicians can write drafts but not sign them', function () {
    $physician = User::factory()->unverifiedPhysician()->create();
    $study = Study::factory()->for($physician, 'uploader')->create();
    $draft = Report::factory()->for($study)->create(['author_id' => $physician->id]);

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->assertSee('waiting for verification')
        ->assertDontSee('Finalize and sign')
        ->call('finalize', $draft->id)
        ->assertForbidden();

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])
        ->call('startReport')
        ->set('findings', 'Normal')
        ->set('impression', 'Normal')
        ->call('saveAndFinalize')
        ->assertForbidden();

    expect(Report::count())->toBe(1)
        ->and(subscriptionOf($physician)->used(Quota::Reports))->toBe(0);
});

test('unverified physicians see a banner explaining what they cannot do yet', function () {
    $this->actingAs(User::factory()->unverifiedPhysician()->create())
        ->get(route('studies.index'))
        ->assertSee('waiting for verification');

    $this->actingAs(User::factory()->physician()->create())
        ->get(route('studies.index'))
        ->assertDontSee('waiting for verification');
});
