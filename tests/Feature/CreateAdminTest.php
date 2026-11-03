<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates an administrator with a verified email address and the given password', function () {
    $this->artisan('users:create-admin', ['email' => 'owner@clinic.test', '--name' => 'Clinic Owner', '--password' => 'a-long-secret-password'])
        ->expectsOutput('Administrator owner@clinic.test created.')
        ->assertSuccessful();

    $admin = User::sole();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->name)->toBe('Clinic Owner')
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('a-long-secret-password', $admin->password))->toBeTrue();
});

test('it generates and shows a password when none is given without prompting', function () {
    $this->artisan('users:create-admin', ['email' => 'owner@clinic.test', '--no-interaction' => true])
        ->expectsOutputToContain('Password: ')
        ->assertSuccessful();

    expect(User::sole()->role)->toBe(Role::Admin);
});

test('it refuses an email address that is already taken', function () {
    User::factory()->create(['email' => 'owner@clinic.test']);

    $this->artisan('users:create-admin', ['email' => 'owner@clinic.test', '--name' => 'Clinic Owner', '--password' => 'a-long-secret-password'])
        ->expectsOutput('The email has already been taken.')
        ->assertFailed();

    expect(User::count())->toBe(1);
});
