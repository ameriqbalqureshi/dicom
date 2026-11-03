<?php

use App\Livewire\Studies\ShareStudy;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use App\Services\StudySharing;
use Livewire\Livewire;

test('owners and physicians the study was shared with see the share panel', function () {
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    $physician = User::factory()->physician()->create();
    allowSender($physician, $study->uploader);
    app(StudySharing::class)->share($study, $study->uploader, $physician);

    $this->actingAs($study->uploader)->get(route('studies.show', $study))->assertSeeLivewire(ShareStudy::class)->assertSee('Share this study');

    $this->actingAs($physician)->get(route('studies.show', $study))
        ->assertOk()
        ->assertSeeLivewire(ShareStudy::class)
        ->assertSee('Forward this study')
        ->assertDontSee('Delete study')
        ->assertSee($study->uploader->name);
});

test('only physicians can be found, by name or email, excluding the current user', function (string $term) {
    $owner = User::factory()->physician()->create(['name' => 'Owner Jane', 'email' => 'owner@clinic.test']);
    User::factory()->physician()->create(['name' => 'Jane Cardiologist', 'email' => 'jane@clinic.test']);
    User::factory()->technician()->create(['name' => 'Jane Technician', 'email' => 'tech@clinic.test']);
    User::factory()->patient()->create(['name' => 'Jane Patient', 'email' => 'patient@clinic.test']);
    User::factory()->physician()->create(['name' => 'Bob Other', 'email' => 'bob@elsewhere.test']);

    Livewire::actingAs($owner)
        ->test(ShareStudy::class, ['study' => Study::factory()->for($owner, 'uploader')->create()])
        ->set('search', $term)
        ->assertSee('Jane Cardiologist')
        ->assertDontSee('Jane Technician')
        ->assertDontSee('Jane Patient')
        ->assertDontSee('Bob Other')
        ->assertDontSee('owner@clinic.test');
})->with(['name' => 'jane', 'email' => 'clinic.test']);

test('short search terms do not list anyone', function () {
    $owner = User::factory()->create();
    User::factory()->physician()->create(['name' => 'Al Short']);

    Livewire::actingAs($owner)
        ->test(ShareStudy::class, ['study' => Study::factory()->for($owner, 'uploader')->create()])
        ->set('search', 'al')
        ->assertDontSee('Al Short')
        ->assertSee('Type at least 3 characters.');
});

test('physicians who already have access are marked as such', function () {
    $study = Study::factory()->create();
    StudyShare::factory()->for($study)->create(['user_id' => User::factory()->physician()->create(['name' => 'Jane Cardiologist'])->id]);

    Livewire::actingAs($study->uploader)
        ->test(ShareStudy::class, ['study' => $study])
        ->set('search', 'jane')
        ->assertSee('Already has access');
});

test('owners can share a study with a physician who allowed them, and remove access', function () {
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    $recipient = User::factory()->physician()->create(['name' => 'Jane Cardiologist']);
    allowSender($recipient, $study->uploader);

    $component = Livewire::actingAs($study->uploader)
        ->test(ShareStudy::class, ['study' => $study])
        ->call('share', $recipient->id)
        ->assertSee('Shared with Jane Cardiologist.')
        ->assertDontSee('Waiting for them to accept');

    $component->call('revoke', StudyShare::sole()->id)->assertSee('Jane Cardiologist can no longer open this study.');

    expect($study->shares()->count())->toBe(0);
});

test('a first share waits for the physician to accept', function () {
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    $recipient = User::factory()->physician()->create(['name' => 'Jane Cardiologist']);

    Livewire::actingAs($study->uploader)
        ->test(ShareStudy::class, ['study' => $study])
        ->call('share', $recipient->id)
        ->assertSee('Sent to Jane Cardiologist. They can open the study once they accept it.')
        ->assertSee('Waiting for them to accept');

    expect(StudyShare::sole()->isAccepted())->toBeFalse();
});

test('sharing with someone who is not a physician shows an error', function () {
    $study = Study::factory()->create();

    Livewire::actingAs($study->uploader)
        ->test(ShareStudy::class, ['study' => $study])
        ->call('share', User::factory()->technician()->create()->id)
        ->assertHasErrors('search')
        ->assertSee('Studies can only be shared with verified physicians.');

    expect(StudyShare::count())->toBe(0);
});

test('forwarding physicians see only their own shares while the owner sees all of them', function () {
    $study = Study::factory()->create();
    $cardiologist = User::factory()->physician()->create(['name' => 'Carl Cardiologist']);
    $surgeon = User::factory()->physician()->create(['name' => 'Sam Surgeon']);
    allowSender($cardiologist, $study->uploader);
    allowSender($surgeon, $cardiologist);
    app(StudySharing::class)->share($study, $study->uploader, $cardiologist);

    Livewire::actingAs($cardiologist)
        ->test(ShareStudy::class, ['study' => $study])
        ->call('share', $surgeon->id)
        ->assertSee('Shared with Sam Surgeon.')
        ->assertSee('You forwarded it to')
        ->assertDontSee('Carl Cardiologist');

    Livewire::actingAs($study->uploader)
        ->test(ShareStudy::class, ['study' => $study])
        ->assertSee('Carl Cardiologist')
        ->assertSee('Sam Surgeon')
        ->assertSee('forwarded by Carl Cardiologist');
});

test('a forwarding physician cannot revoke shares made by others', function () {
    $study = Study::factory()->create();
    [$cardiologist, $colleague] = User::factory()->physician()->count(2)->create();
    allowSender($cardiologist, $study->uploader);
    allowSender($colleague, $study->uploader);
    app(StudySharing::class)->share($study, $study->uploader, $cardiologist);
    app(StudySharing::class)->share($study, $study->uploader, $colleague);

    Livewire::actingAs($cardiologist)
        ->test(ShareStudy::class, ['study' => $study])
        ->call('revoke', StudyShare::whereBelongsTo($colleague, 'recipient')->sole()->id)
        ->assertForbidden();
});

test('people without sharing rights cannot use the share panel', function (string $who) {
    $study = Study::factory()->create();
    $user = match ($who) {
        'admin' => User::factory()->admin()->create(),
        'pending physician' => StudyShare::factory()->pending()->for($study)->create()->recipient,
        default => User::factory()->physician()->create(),
    };

    Livewire::actingAs($user)
        ->test(ShareStudy::class, ['study' => $study])
        ->assertForbidden();
})->with(['another physician' => 'other', 'an administrator' => 'admin', 'a physician yet to accept' => 'pending physician']);
