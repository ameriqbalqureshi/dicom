<?php

use App\Livewire\Studies\PendingShares;
use App\Livewire\Studies\StudyIndex;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use Livewire\Livewire;

function pendingShareFromPatient(User $physician, string $patientName = 'Pat Patient'): StudyShare
{
    $study = Study::factory()->for(User::factory()->patient()->state(['name' => $patientName]), 'uploader')->create();

    return StudyShare::factory()->pending()->for($study)->create(['user_id' => $physician->id]);
}

test('physicians see the studies patients have shared with them that await acceptance', function () {
    $physician = User::factory()->physician()->create();
    pendingShareFromPatient($physician, 'Pat Patient');
    pendingShareFromPatient(User::factory()->physician()->create(), 'Someone Else');

    $this->actingAs($physician)
        ->get(route('shares.pending'))
        ->assertOk()
        ->assertSee('Pat Patient')
        ->assertDontSee('Someone Else');
});

test('the study list and navigation point physicians to pending studies', function () {
    $physician = User::factory()->physician()->create();
    pendingShareFromPatient($physician);

    $this->actingAs($physician)
        ->get(route('studies.index'))
        ->assertSee(route('shares.pending'))
        ->assertSee('1 study is waiting for you to accept.');
});

test('accepting a study makes it visible', function () {
    $physician = User::factory()->physician()->create();
    $share = pendingShareFromPatient($physician);

    Livewire::actingAs($physician)
        ->test(PendingShares::class)
        ->call('accept', $share->id)
        ->assertSee('Accepted the study from Pat Patient.');

    expect($physician->can('view', $share->study))->toBeTrue();
});

test('declining a study discards the share', function () {
    $physician = User::factory()->physician()->create();
    $share = pendingShareFromPatient($physician);

    Livewire::actingAs($physician)
        ->test(PendingShares::class)
        ->call('decline', $share->id)
        ->assertSee('Declined the study from Pat Patient.');

    $this->assertModelMissing($share);
});

test('physicians cannot respond to shares meant for someone else', function () {
    $share = pendingShareFromPatient(User::factory()->physician()->create());

    Livewire::actingAs(User::factory()->physician()->create())->test(PendingShares::class)->call('accept', $share->id)->assertForbidden();
    Livewire::actingAs($share->sharer)->test(PendingShares::class)->call('accept', $share->id)->assertForbidden();

    expect($share->fresh()->isAccepted())->toBeFalse();
});

test('the study list shows who has shared or forwarded studies and lists them per person', function () {
    $user = User::factory()->physician()->create();
    $sharer = User::factory()->physician()->create(['name' => 'Sharing Sam']);
    $forwarded = Study::factory()->create(['patient_name' => 'FORWARDED PATIENT']);
    StudyShare::factory()->for($forwarded)->create(['user_id' => $user->id, 'shared_by' => $sharer->id]);
    Study::factory()->for($sharer, 'uploader')->create(['patient_name' => 'PRIVATE PATIENT']);
    Study::factory()->for($user, 'uploader')->create(['patient_name' => 'OWN PATIENT']);
    pendingShareFromPatient($user, 'Pending Paula');

    Livewire::actingAs($user)
        ->test(StudyIndex::class)
        ->assertSee('Sharing Sam')
        ->assertDontSee('Pending Paula')
        ->assertSee('OWN PATIENT')
        ->assertDontSee('FORWARDED PATIENT')
        ->set('from', $sharer->id)
        ->assertSee('FORWARDED PATIENT')
        ->assertDontSee('PRIVATE PATIENT')
        ->assertDontSee('OWN PATIENT');
});
