<?php

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Livewire\Auth\Register;
use App\Models\Package;
use App\Models\User;
use Livewire\Livewire;

test('the sign-in page links to the sign-up page', function () {
    $this->get(route('login'))->assertOk()->assertSee(route('register'));
    $this->get(route('register'))->assertOk()->assertSeeLivewire(Register::class);
});

test('physicians sign up unverified, with their registration number and the physician trial', function () {
    Livewire::test(Register::class)
        ->set('role', Role::Physician->value)
        ->set('name', 'Dr. Jane Heart')
        ->set('email', 'jane@clinic.test')
        ->set('registrationNumber', ' PMDC-123 ')
        ->set('qualifications', 'MBBS, FCPS')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user = User::sole();
    $subscription = $user->subscriptions()->sole();
    $trial = Package::trialFor(Role::Physician);

    expect($user->role)->toBe(Role::Physician)
        ->and($user->isVerifiedPhysician())->toBeFalse()
        ->and($user->registration_number)->toBe('PMDC-123')
        ->and($user->qualifications)->toBe('MBBS, FCPS')
        ->and($subscription->package_id)->toBe($trial->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->max_uploads)->toBe($trial->max_uploads)
        ->and($subscription->ends_at->isFuture())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('patients and technicians sign up without a registration number', function (Role $role) {
    Livewire::test(Register::class)
        ->set('role', $role->value)
        ->set('name', 'Sam Person')
        ->set('email', 'sam@example.test')
        ->set('registrationNumber', 'ignored')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::sole();

    expect($user->role)->toBe($role)
        ->and($user->registration_number)->toBeNull()
        ->and($user->subscriptions()->sole()->package->is(Package::trialFor($role)))->toBeTrue();
})->with([Role::Patient, Role::Technician]);

test('physicians must give a registration number', function () {
    Livewire::test(Register::class)
        ->set('role', Role::Physician->value)
        ->set('name', 'Dr. No Number')
        ->set('email', 'no@clinic.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('register')
        ->assertHasErrors(['registrationNumber' => 'required']);

    expect(User::count())->toBe(0);
});

test('nobody can sign up as an administrator', function () {
    Livewire::test(Register::class)
        ->set('role', Role::Admin->value)
        ->set('name', 'Sneaky')
        ->set('email', 'sneaky@example.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('register')
        ->assertHasErrors(['role' => 'in']);

    expect(User::count())->toBe(0);
});

test('the email must be unused and the password confirmed', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    Livewire::test(Register::class)
        ->set('role', Role::Patient->value)
        ->set('name', 'Sam')
        ->set('email', 'taken@example.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'different-password')
        ->call('register')
        ->assertHasErrors(['email' => 'unique', 'password' => 'confirmed']);
});

test('without an active trial package, new users start with no package', function () {
    Package::query()->where('role', Role::Patient)->update(['is_active' => false]);

    Livewire::test(Register::class)
        ->set('role', Role::Patient->value)
        ->set('name', 'Sam')
        ->set('email', 'sam@example.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('register')
        ->assertHasNoErrors();

    expect(User::sole()->subscriptions()->count())->toBe(0);
});
