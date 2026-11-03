<?php

use App\Enums\Role;
use App\Enums\SenderStatus;
use App\Enums\ShareOutcome;
use App\Models\SenderPermission;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use App\Services\StudySharing;

function sharing(): StudySharing
{
    return app(StudySharing::class);
}

/**
 * Share a study along a chain of physicians, each forwarding it to the next.
 *
 * @param  list<User>  $chain
 */
function shareAlong(Study $study, array $chain): void
{
    $sharer = $study->uploader;

    foreach ($chain as $recipient) {
        allowSender($recipient, $sharer);
        sharing()->share($study, $sharer, $recipient);
        $sharer = $recipient;
    }
}

test('sharing with a physician who allowed the sender grants access immediately', function (Role $ownerRole) {
    $study = Study::factory()->for(User::factory()->state(['role' => $ownerRole]), 'uploader')->create();
    $physician = User::factory()->physician()->create();
    allowSender($physician, $study->uploader);

    expect(sharing()->share($study, $study->uploader, $physician))->toBe(ShareOutcome::Shared)
        ->and($physician->can('view', $study))->toBeTrue()
        ->and(Study::visibleTo($physician)->pluck('id')->all())->toBe([$study->id]);
})->with([Role::Technician, Role::Physician, Role::Admin]);

test('a first share from any sender waits until the physician accepts it', function (Role $ownerRole) {
    $study = Study::factory()->for(User::factory()->state(['role' => $ownerRole]), 'uploader')->create();
    $physician = User::factory()->physician()->create();

    expect(sharing()->share($study, $study->uploader, $physician))->toBe(ShareOutcome::AwaitingAcceptance)
        ->and($physician->can('view', $study))->toBeFalse()
        ->and($physician->can('share', $study))->toBeFalse()
        ->and(Study::visibleTo($physician)->count())->toBe(0);

    sharing()->accept(StudyShare::sole());

    $nextStudy = Study::factory()->for($study->uploader, 'uploader')->create();

    expect($physician->can('view', $study))->toBeTrue()
        ->and(SenderPermission::statusOf($physician, $study->uploader))->toBe(SenderStatus::Allowed)
        ->and(sharing()->share($nextStudy, $study->uploader, $physician))->toBe(ShareOutcome::Shared);
})->with([Role::Patient, Role::Technician, Role::Physician]);

test('declining a first share does not allow the sender', function () {
    $study = Study::factory()->create();
    $physician = User::factory()->physician()->create();
    sharing()->share($study, $study->uploader, $physician);

    sharing()->revoke(StudyShare::sole());

    expect(SenderPermission::statusOf($physician, $study->uploader))->toBeNull()
        ->and(sharing()->share($study, $study->uploader, $physician))->toBe(ShareOutcome::AwaitingAcceptance);
});

test('studies can only be shared with physicians', function (Role $role) {
    $study = Study::factory()->create();

    sharing()->share($study, $study->uploader, User::factory()->create(['role' => $role]));
})->with([Role::Technician, Role::Patient, Role::Admin])->throws(InvalidArgumentException::class, 'only be shared with verified physicians');

test('a study cannot be shared with its owner or someone who already has access', function () {
    $study = Study::factory()->create();
    $physician = User::factory()->physician()->create();
    sharing()->share($study, $study->uploader, $physician);

    expect(fn () => sharing()->share($study, $physician, $study->uploader))->toThrow(InvalidArgumentException::class, 'already has access')
        ->and(fn () => sharing()->share($study, $study->uploader, $physician))->toThrow(InvalidArgumentException::class, 'already has access');
});

test('physicians can forward studies shared with them, and recipients can view but not delete them', function () {
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    [$cardiologist, $surgeon] = User::factory()->physician()->count(2)->create();

    expect($cardiologist->can('share', $study))->toBeFalse();

    shareAlong($study, [$cardiologist, $surgeon]);

    expect($cardiologist->can('share', $study))->toBeTrue()
        ->and($surgeon->can('view', $study))->toBeTrue()
        ->and($surgeon->can('delete', $study))->toBeFalse()
        ->and(StudyShare::whereBelongsTo($surgeon, 'recipient')->sole()->shared_by)->toBe($cardiologist->id);
});

test('a physician who has lost the physician role cannot forward', function () {
    $study = Study::factory()->create();
    $user = User::factory()->physician()->create();
    shareAlong($study, [$user]);

    $user->update(['role' => Role::Technician]);

    expect($user->can('share', $study))->toBeFalse();
});

test('administrators can view and delete studies but cannot share studies they do not own', function () {
    $admin = User::factory()->admin()->create();
    $study = Study::factory()->create();

    expect($admin->can('view', $study))->toBeTrue()
        ->and($admin->can('delete', $study))->toBeTrue()
        ->and($admin->can('share', $study))->toBeFalse();
});

test('revoking a share also revokes everything forwarded from it, leaving other shares alone', function () {
    $study = Study::factory()->create();
    [$cardiologist, $surgeon, $anaesthetist, $colleague] = User::factory()->physician()->count(4)->create();
    shareAlong($study, [$cardiologist, $surgeon, $anaesthetist]);
    sharing()->share($study, $study->uploader, $colleague);

    sharing()->revoke(StudyShare::whereBelongsTo($cardiologist, 'recipient')->sole());

    expect($study->shares()->pluck('user_id')->all())->toBe([$colleague->id])
        ->and($surgeon->can('view', $study))->toBeFalse()
        ->and($anaesthetist->can('view', $study))->toBeFalse();
});

test('revoking the shares a user received removes what they forwarded', function () {
    $study = Study::factory()->create();
    [$cardiologist, $surgeon] = User::factory()->physician()->count(2)->create();
    shareAlong($study, [$cardiologist, $surgeon]);

    sharing()->revokeReceivedBy($cardiologist);

    expect(StudyShare::count())->toBe(0);
});

test('revoking everything involving a user removes the shares they made and received', function () {
    [$cardiologist, $surgeon, $other] = User::factory()->physician()->count(3)->create();
    $received = Study::factory()->create();
    $own = Study::factory()->for($cardiologist, 'uploader')->create();
    shareAlong($received, [$cardiologist, $surgeon]);
    shareAlong($own, [$other, $surgeon]);

    sharing()->revokeInvolving($cardiologist);

    expect(StudyShare::count())->toBe(0);
});

test('only the recipient can respond to a pending share, and only its sharer or the owner can revoke it', function () {
    $study = Study::factory()->create();
    [$cardiologist, $surgeon, $outsider] = User::factory()->physician()->count(3)->create();
    shareAlong($study, [$cardiologist, $surgeon]);
    $forwarded = StudyShare::whereBelongsTo($surgeon, 'recipient')->sole();
    $pending = StudyShare::factory()->pending()->create();

    expect($pending->recipient->can('respond', $pending))->toBeTrue()
        ->and($pending->sharer->can('respond', $pending))->toBeFalse()
        ->and($surgeon->can('respond', $forwarded))->toBeFalse()
        ->and($cardiologist->can('delete', $forwarded))->toBeTrue()
        ->and($study->uploader->can('delete', $forwarded))->toBeTrue()
        ->and($surgeon->can('delete', $forwarded))->toBeFalse()
        ->and($outsider->can('delete', $forwarded))->toBeFalse();
});

test('a physician can remove a study shared with them from their list, and whoever they forwarded it to keeps it', function () {
    $study = Study::factory()->create();
    [$cardiologist, $surgeon] = User::factory()->physician()->count(2)->create();
    shareAlong($study, [$cardiologist, $surgeon]);

    expect($cardiologist->can('removeFromList', $study))->toBeTrue()
        ->and($study->uploader->can('removeFromList', $study))->toBeFalse();

    sharing()->removeFromList($study, $cardiologist);

    expect($study->shares()->pluck('user_id')->all())->toBe([$surgeon->id])
        ->and($cardiologist->can('view', $study))->toBeFalse()
        ->and($cardiologist->can('removeFromList', $study))->toBeFalse()
        ->and($surgeon->can('view', $study))->toBeTrue();
});

test('a pending share cannot be removed from the list, only declined', function () {
    $share = StudyShare::factory()->pending()->create();

    expect($share->recipient->can('removeFromList', $share->study))->toBeFalse();
});
