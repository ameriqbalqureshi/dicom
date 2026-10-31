<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the sign-in page', function () {
    $this->get(route('studies.index'))->assertRedirect(route('login'));
});

test('the sign-in page renders', function () {
    $this->get(route('login'))->assertOk()->assertSeeLivewire(Login::class);
});

test('users can sign in with valid credentials', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('studies.index'));

    $this->assertAuthenticatedAs($user);
});

test('users cannot sign in with an invalid password', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('sign-in is throttled after five failed attempts', function () {
    $user = User::factory()->create();
    $component = Livewire::test(Login::class)->set('email', $user->email)->set('password', 'wrong-password');

    foreach (range(1, 5) as $attempt) {
        $component->call('login');
    }

    $component->set('password', 'password')->call('login');

    expect($component->errors()->first('email'))->toStartWith('Too many login attempts.');
    $this->assertGuest();
});

test('users can sign out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
