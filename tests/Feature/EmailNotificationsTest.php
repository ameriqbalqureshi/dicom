<?php

use App\Enums\Role;
use App\Livewire\Billing\MyPackage;
use App\Livewire\Billing\PaymentIndex;
use App\Livewire\Studies\StudyReports;
use App\Livewire\Users\UserIndex;
use App\Models\Package;
use App\Models\PaymentAccount;
use App\Models\Report;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AccountCreated;
use App\Notifications\PackageEndingSoon;
use App\Notifications\PackageStarted;
use App\Notifications\PaymentReceiptSubmitted;
use App\Notifications\PaymentRejected;
use App\Notifications\PaymentSubmitted;
use App\Notifications\PhysicianSignedUp;
use App\Notifications\PhysicianVerified;
use App\Notifications\ReportFinalized;
use App\Notifications\ShareAccepted;
use App\Notifications\StudySharedWithYou;
use App\Services\StudySharing;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
});

test('physicians are emailed when a study is shared with them', function () {
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    $physician = User::factory()->physician()->create();
    allowSender($physician, $study->uploader);

    app(StudySharing::class)->share($study, $study->uploader, $physician);

    Notification::assertSentTo($physician, StudySharedWithYou::class, function (StudySharedWithYou $notification) use ($physician) {
        $mail = $notification->toMail($physician);

        return ! $notification->awaitingAcceptance && $mail->actionUrl === route('studies.show', $notification->study);
    });
});

test('physicians are asked to accept studies patients share with them, and the patient hears when they do', function () {
    $study = Study::factory()->for(User::factory()->patient(), 'uploader')->create();
    $physician = User::factory()->physician()->create();
    $sharing = app(StudySharing::class);

    $sharing->share($study, $study->uploader, $physician);

    Notification::assertSentTo($physician, StudySharedWithYou::class, function (StudySharedWithYou $notification) use ($physician) {
        return $notification->awaitingAcceptance && $notification->toMail($physician)->actionUrl === route('shares.pending');
    });
    Notification::assertNotSentTo($study->uploader, ShareAccepted::class);

    $sharing->accept(StudyShare::sole());

    Notification::assertSentTo($study->uploader, ShareAccepted::class, fn (ShareAccepted $notification) => $notification->physician->is($physician));
});

test('the study owner is emailed when a report is signed, but not when they sign it themselves', function () {
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    $physician = User::factory()->physician()->create();
    StudyShare::factory()->for($study)->create(['user_id' => $physician->id]);
    $report = Report::factory()->for($study)->create(['author_id' => $physician->id]);

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $study])->call('finalize', $report->id);

    Notification::assertSentTo($study->uploader, ReportFinalized::class, fn (ReportFinalized $notification) => $notification->report->is($report));

    $ownStudy = Study::factory()->for($physician, 'uploader')->create();
    $ownReport = Report::factory()->for($ownStudy)->create(['author_id' => $physician->id]);

    Livewire::actingAs($physician)->test(StudyReports::class, ['study' => $ownStudy])->call('finalize', $ownReport->id);

    Notification::assertNotSentTo($physician, ReportFinalized::class);
});

test('administrators are emailed about payments to confirm', function () {
    $admins = User::factory()->admin()->count(2)->create();
    $physician = User::factory()->physician()->create();
    $package = Package::factory()->create();

    Livewire::actingAs($physician)->test(MyPackage::class)
        ->call('choose', $package->id)
        ->set('paymentAccountId', (string) PaymentAccount::factory()->create()->id)
        ->set('paymentReference', 'TXN-9')
        ->call('purchase');

    Notification::assertSentTo($admins, PaymentSubmitted::class);
    Notification::assertNotSentTo($physician, PaymentSubmitted::class);
});

test('free packages do not ask administrators to confirm anything', function () {
    User::factory()->admin()->create();
    $package = Package::factory()->create(['price' => 0]);

    Livewire::actingAs(User::factory()->physician()->create())->test(MyPackage::class)
        ->call('choose', $package->id)
        ->call('purchase');

    Notification::assertNothingSent();
});

test('users are emailed when their payment is confirmed or rejected', function () {
    $admin = User::factory()->admin()->create();
    $confirmed = Subscription::factory()->pending()->create();
    $rejected = Subscription::factory()->pending()->create();

    Livewire::actingAs($admin)->test(PaymentIndex::class)
        ->call('approve', $confirmed->id)
        ->call('startRejecting', $rejected->id)
        ->set('rejectionReason', 'No payment found')
        ->call('reject');

    Notification::assertSentTo($confirmed->user, PackageStarted::class);
    Notification::assertSentTo($rejected->user, PaymentRejected::class, function (PaymentRejected $notification) use ($rejected) {
        return str_contains(implode(' ', $notification->toMail($rejected->user)->introLines), 'No payment found');
    });
});

test('users are emailed when an administrator gives them a package', function () {
    $user = User::factory()->technician()->create();
    $package = Package::factory()->forRole(Role::Technician)->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(UserIndex::class)
        ->call('assignPackage', $user->id, (string) $package->id);

    Notification::assertSentTo($user, PackageStarted::class);
});

test('physicians are emailed when they are verified', function () {
    $physician = User::factory()->unverifiedPhysician()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(UserIndex::class)->call('verifyPhysician', $physician->id);

    Notification::assertSentTo($physician, PhysicianVerified::class);
});

test('people get a welcome email when an administrator creates their account, which is already verified', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(UserIndex::class)
        ->set('name', 'Sam Tech')
        ->set('email', 'sam@clinic.test')
        ->set('password', 'secret-password')
        ->set('role', Role::Technician->value)
        ->call('createUser');

    $user = User::where('email', 'sam@clinic.test')->sole();

    expect($user->hasVerifiedEmail())->toBeTrue();
    Notification::assertSentTo($user, AccountCreated::class, function (AccountCreated $notification) use ($user) {
        $mail = $notification->toMail($user);

        return $mail->actionUrl === route('login') && ! str_contains(serialize($mail), 'secret-password');
    });
});

test('users are reminded once when their package is about to end', function () {
    $endingSoon = Subscription::factory()->create(['ends_at' => now()->addDays(2)]);
    $endingLater = Subscription::factory()->create(['ends_at' => now()->addDays(10)]);
    $alreadyEnded = Subscription::factory()->ended()->create();
    $neverEnds = Subscription::factory()->create(['ends_at' => null]);

    $this->artisan('packages:send-expiry-reminders')->assertSuccessful();
    $this->artisan('packages:send-expiry-reminders')->assertSuccessful();

    Notification::assertSentToTimes($endingSoon->user, PackageEndingSoon::class, 1);
    Notification::assertNotSentTo([$endingLater->user, $alreadyEnded->user, $neverEnds->user], PackageEndingSoon::class);
    expect($endingSoon->fresh()->expiry_reminder_sent_at)->not->toBeNull();
});

test('every email renders', function () {
    $admin = User::factory()->admin()->create();
    $physician = User::factory()->unverifiedPhysician()->create(['registration_number' => 'PMDC-1']);
    $study = Study::factory()->for($physician, 'uploader')->create();
    $report = Report::factory()->final()->for($study)->create(['author_id' => $physician->id]);
    $pending = Subscription::factory()->pending()->create(['rejection_reason' => 'No payment found']);
    $active = Subscription::factory()->create();

    $notifications = [
        new StudySharedWithYou($study, $admin, awaitingAcceptance: true),
        new StudySharedWithYou($study, $admin, awaitingAcceptance: false),
        new ShareAccepted($study, $physician),
        new ReportFinalized($report),
        new PaymentSubmitted($pending),
        new PackageStarted($active),
        new PaymentRejected($pending),
        new PaymentReceiptSubmitted,
        new PhysicianSignedUp($physician),
        new PhysicianVerified,
        new PackageEndingSoon($active),
        new AccountCreated($admin),
    ];

    foreach ($notifications as $notification) {
        expect((string) $notification->toMail($physician)->render())->toContain(config('app.name'));
    }
});
