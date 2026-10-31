<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

test('the sign-in page links to the forgot password page', function () {
    $this->get(route('login'))->assertSee(route('password.request'));
    $this->get(route('password.request'))->assertOk()->assertSeeLivewire(ForgotPassword::class);
});

test('a reset link is emailed to the account', function () {
    Notification::fake();
    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendLink')
        ->assertHasNoErrors()
        ->assertSee('If an account uses that address');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('unknown addresses get the same answer, so accounts cannot be discovered', function () {
    Notification::fake();

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nobody@example.test')
        ->call('sendLink')
        ->assertHasNoErrors()
        ->assertSee('If an account uses that address');

    Notification::assertNothingSent();
});

test('the emailed token sets a new password and confirms the email address', function () {
    $user = User::factory()->unverified()->create();
    $token = Password::createToken($user);

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee($user->email);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'brand-new-password')
        ->set('password_confirmation', 'brand-new-password')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();

    expect(Hash::check('brand-new-password', $user->password))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('an invalid token does not change the password', function () {
    $user = User::factory()->create();

    Livewire::test(ResetPassword::class, ['token' => 'not-a-real-token'])
        ->set('email', $user->email)
        ->set('password', 'brand-new-password')
        ->set('password_confirmation', 'brand-new-password')
        ->call('resetPassword')
        ->assertHasErrors('email');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
