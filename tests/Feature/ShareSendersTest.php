<?php

use App\Enums\SenderStatus;
use App\Enums\ShareOutcome;
use App\Livewire\Studies\PendingShares;
use App\Livewire\Studies\ShareSenders;
use App\Livewire\Studies\ShareStudy;
use App\Models\SenderPermission;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use App\Notifications\StudySharedWithYou;
use App\Services\StudySharing;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
});

test('shares from a blocked or suspended sender are not delivered, cost nothing and are not announced', function (string $state) {
    $sender = User::factory()->technician()->withLimits(['max_shares' => 5])->create();
    $physician = User::factory()->physician()->create();
    SenderPermission::factory()->{$state}()->create(['recipient_id' => $physician->id, 'sender_id' => $sender->id]);
    $study = Study::factory()->for($sender, 'uploader')->create();

    expect(app(StudySharing::class)->share($study, $sender, $physician))->toBe(ShareOutcome::NotDelivered)
        ->and(StudyShare::count())->toBe(0)
        ->and($sender->subscriptions()->sole()->shares_used)->toBe(0);

    Notification::assertNothingSent();
})->with(['blocked', 'suspended']);

test('the sender only sees that the share was not delivered', function () {
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    $physician = User::factory()->physician()->create(['name' => 'Jane Cardiologist']);
    SenderPermission::factory()->blocked()->create(['recipient_id' => $physician->id, 'sender_id' => $study->uploaded_by]);

    Livewire::actingAs($study->uploader)
        ->test(ShareStudy::class, ['study' => $study])
        ->call('share', $physician->id)
        ->assertHasErrors('search')
        ->assertSee('Your share to Jane Cardiologist was not delivered.')
        ->assertDontSee('blocked');
});

test('blocking a sender removes the studies they shared, but colleagues the physician forwarded them to keep access', function () {
    $sender = User::factory()->technician()->create();
    [$physician, $colleague] = User::factory()->physician()->count(2)->create();
    $accepted = StudyShare::factory()->for(Study::factory()->for($sender, 'uploader'))->create(['user_id' => $physician->id, 'shared_by' => $sender->id]);
    $pending = StudyShare::factory()->pending()->for(Study::factory()->for($sender, 'uploader'))->create(['user_id' => $physician->id, 'shared_by' => $sender->id]);
    $forwarded = StudyShare::factory()->for($accepted->study)->create(['user_id' => $colleague->id, 'shared_by' => $physician->id]);
    $fromSomeoneElse = StudyShare::factory()->create(['user_id' => $physician->id]);

    app(StudySharing::class)->block($physician, $sender);

    $this->assertModelMissing($accepted);
    $this->assertModelMissing($pending);
    $this->assertModelExists($forwarded);
    $this->assertModelExists($fromSomeoneElse);
    expect(SenderPermission::statusOf($physician, $sender))->toBe(SenderStatus::Blocked)
        ->and($colleague->can('view', $accepted->study))->toBeTrue();
});

test('suspending a sender keeps the studies they already shared', function () {
    $sender = User::factory()->technician()->create();
    $physician = User::factory()->physician()->create();
    $share = StudyShare::factory()->for(Study::factory()->for($sender, 'uploader'))->create(['user_id' => $physician->id, 'shared_by' => $sender->id]);
    SenderPermission::factory()->create(['recipient_id' => $physician->id, 'sender_id' => $sender->id]);

    Livewire::actingAs($physician)->test(ShareSenders::class)
        ->call('suspend', $sender->id)
        ->assertSee('Suspended');

    $this->assertModelExists($share);
    expect(SenderPermission::statusOf($physician, $sender))->toBe(SenderStatus::Suspended);
});

test('resuming a suspended sender delivers their shares straight away again', function () {
    $sender = User::factory()->technician()->create();
    $physician = User::factory()->physician()->create();
    SenderPermission::factory()->suspended()->create(['recipient_id' => $physician->id, 'sender_id' => $sender->id]);

    Livewire::actingAs($physician)->test(ShareSenders::class)->call('resume', $sender->id);

    expect(app(StudySharing::class)->share(Study::factory()->for($sender, 'uploader')->create(), $sender, $physician))->toBe(ShareOutcome::Shared);
});

test('unblocking a sender makes them new again, so their next share needs acceptance', function () {
    $sender = User::factory()->technician()->create();
    $physician = User::factory()->physician()->create();
    SenderPermission::factory()->blocked()->create(['recipient_id' => $physician->id, 'sender_id' => $sender->id]);

    Livewire::actingAs($physician)->test(ShareSenders::class)->call('unblock', $sender->id);

    expect(SenderPermission::statusOf($physician, $sender))->toBeNull()
        ->and(app(StudySharing::class)->share(Study::factory()->for($sender, 'uploader')->create(), $sender, $physician))->toBe(ShareOutcome::AwaitingAcceptance);
});

test('resume and unblock only apply to senders in that state', function (string $action, string $state) {
    $sender = User::factory()->technician()->create();
    $physician = User::factory()->physician()->create();
    SenderPermission::factory()->{$state}()->create(['recipient_id' => $physician->id, 'sender_id' => $sender->id]);

    Livewire::actingAs($physician)->test(ShareSenders::class)->call($action, $sender->id)->assertNotFound();
})->with([
    'resume a blocked sender' => ['resume', 'blocked'],
    'unblock a suspended sender' => ['unblock', 'suspended'],
]);

test('physicians can only act on people who have shared with them', function () {
    $physician = User::factory()->physician()->create();
    $stranger = User::factory()->technician()->create();
    SenderPermission::factory()->create(['sender_id' => $stranger->id]);

    Livewire::actingAs($physician)->test(ShareSenders::class)->call('block', $stranger->id)->assertNotFound();

    expect(SenderPermission::statusOf($physician, $stranger))->toBeNull();
});

test('the senders page lists everyone who shared with the physician and their status', function () {
    $physician = User::factory()->physician()->create();
    $newSender = User::factory()->patient()->create(['name' => 'Nina New']);
    StudyShare::factory()->pending()->for(Study::factory()->for($newSender, 'uploader'))->create(['user_id' => $physician->id, 'shared_by' => $newSender->id]);
    SenderPermission::factory()->blocked()->create(['recipient_id' => $physician->id, 'sender_id' => User::factory()->create(['name' => 'Bob Blocked'])->id]);
    SenderPermission::factory()->suspended()->create(['sender_id' => User::factory()->create(['name' => 'Someone Elses Sender'])->id]);

    $this->actingAs($physician)->get(route('shares.senders'))
        ->assertOk()
        ->assertSeeInOrder(['Bob Blocked', 'Blocked', 'Unblock'])
        ->assertSeeInOrder(['Nina New', '1 waiting', 'New · needs acceptance'])
        ->assertDontSee('Someone Elses Sender');
});

test('only physicians can open the senders page', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())->get(route('shares.senders'))->assertForbidden();
})->with(['technician', 'patient', 'admin']);

test('a sender can be blocked from the requests page', function () {
    $physician = User::factory()->physician()->create();
    $sender = User::factory()->patient()->create(['name' => 'Pat Patient']);
    $share = StudyShare::factory()->pending()->for(Study::factory()->for($sender, 'uploader'))->create(['user_id' => $physician->id, 'shared_by' => $sender->id]);

    Livewire::actingAs($physician)->test(PendingShares::class)
        ->call('blockSender', $share->id)
        ->assertSee('Blocked Pat Patient.');

    $this->assertModelMissing($share);
    expect(SenderPermission::statusOf($physician, $sender))->toBe(SenderStatus::Blocked);
});

test('a share cannot be used to block its sender by anyone but its recipient', function () {
    $share = StudyShare::factory()->pending()->create();

    Livewire::actingAs(User::factory()->physician()->create())->test(PendingShares::class)
        ->call('blockSender', $share->id)
        ->assertForbidden();

    expect(SenderPermission::count())->toBe(0);
});

test('existing shares are left alone when the sender is blocked by another physician', function () {
    $sender = User::factory()->technician()->create();
    $physician = User::factory()->physician()->create();
    $share = StudyShare::factory()->for(Study::factory()->for($sender, 'uploader'))->create(['user_id' => $physician->id, 'shared_by' => $sender->id]);

    app(StudySharing::class)->block(User::factory()->physician()->create(), $sender);

    $this->assertModelExists($share);
    Notification::assertNotSentTo($physician, StudySharedWithYou::class);
});
