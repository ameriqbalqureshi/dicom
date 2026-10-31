<?php

use App\Enums\Role;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\VerifyEmail;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Notifications\PhysicianSignedUp;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

function verificationUrl(User $user): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
}

test('signing up emails a verification link and asks admins to verify physicians', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    Livewire::test(Register::class)
        ->set('role', Role::Physician->value)
        ->set('name', 'Dr. Jane Heart')
        ->set('email', 'jane@clinic.test')
        ->set('registrationNumber', 'PMDC-1')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('register');

    $user = User::where('email', 'jane@clinic.test')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
    Notification::assertSentTo($admin, PhysicianSignedUp::class, fn (PhysicianSignedUp $notification) => $notification->physician->is($user));
});

test('admins are not asked to verify patients or technicians', function () {
    Notification::fake();
    User::factory()->admin()->create();

    Livewire::test(Register::class)
        ->set('role', Role::Patient->value)
        ->set('name', 'Sam')
        ->set('email', 'sam@example.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('register');

    Notification::assertNotSentTo(User::admins()->get(), PhysicianSignedUp::class);
});

test('users must confirm their email before using the app', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('studies.index'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee($user->email);
});

test('the link in the email confirms the address', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(verificationUrl($user))->assertRedirect(route('studies.index'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a tampered or someone else link does not confirm the address', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();
    $this->actingAs($user)->get(verificationUrl(User::factory()->unverified()->create()))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('the verification email can be sent again, a few times an hour', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $component = Livewire::actingAs($user)->test(VerifyEmail::class);

    foreach (range(1, 3) as $attempt) {
        $component->call('resend')->assertHasNoErrors()->assertSee('We have sent a new link');
    }

    $component->call('resend')->assertHasErrors('resend');

    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 3);
});

test('verified users are sent on from the verification page', function () {
    Livewire::actingAs(User::factory()->create())->test(VerifyEmail::class)->assertRedirect(route('studies.index'));
});
