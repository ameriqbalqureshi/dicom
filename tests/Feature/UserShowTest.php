<?php

use App\Livewire\Users\UserIndex;
use App\Livewire\Users\UserShow;
use App\Models\Study;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('only administrators can open a user\'s page', function () {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->physician()->create())
        ->get(route('users.show', $user))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('users.show', $user))
        ->assertOk()
        ->assertSeeLivewire(UserShow::class);
});

test('a user\'s page lists the studies they uploaded, linked to the viewer, and not other people\'s', function () {
    $technician = User::factory()->technician()->create();
    $own = Study::factory()->for($technician, 'uploader')->create(['patient_name' => 'Ahmed Khan']);
    $expired = Study::factory()->for($technician, 'uploader')->create(['patient_name' => 'Old Patient', 'expires_at' => now()->subDay()]);
    $other = Study::factory()->create(['patient_name' => 'Someone Else']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserShow::class, ['user' => $technician])
        ->assertSee(route('studies.show', $own))
        ->assertSee($expired->patient_name)
        ->assertDontSee(route('studies.show', $expired))
        ->assertDontSee($other->patient_name);
});

test('administrators can resend the verification email to someone who has not verified', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserShow::class, ['user' => $user])
        ->call('resendVerificationEmail', $user->id)
        ->assertSet('status', "A new verification link was sent to {$user->email}.");

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('administrators can mark an email address verified, but only once', function () {
    $user = User::factory()->unverified()->create();
    $component = Livewire::actingAs(User::factory()->admin()->create())->test(UserShow::class, ['user' => $user]);

    $component->call('verifyEmail', $user->id)->assertHasNoErrors();
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();

    $component->call('verifyEmail', $user->id)->assertHasErrors('users');
});

test('deleting a user from their page goes back to the user list and says what was deleted', function () {
    $technician = User::factory()->technician()->create();
    Study::factory()->for($technician, 'uploader')->count(2)->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserShow::class, ['user' => $technician])
        ->call('deleteUser', $technician->id)
        ->assertRedirect(route('users.index'));

    $this->assertModelMissing($technician);
    expect(Study::count())->toBe(0);

    Livewire::test(UserIndex::class)->assertSee("{$technician->name} was deleted, with their 2 studies.");
});
