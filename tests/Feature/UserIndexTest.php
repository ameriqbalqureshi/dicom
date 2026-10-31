<?php

use App\Enums\Role;
use App\Livewire\Users\UserIndex;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('non-administrators cannot open user management', function () {
    $this->actingAs(User::factory()->technician()->create())
        ->get(route('users.index'))
        ->assertForbidden();
});

test('administrators see every user', function () {
    $other = User::factory()->create(['name' => 'Dr Grey']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee($other->name);
});

test('administrators can create a user with a role', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->set('name', 'Dr Grey')
        ->set('email', 'grey@example.com')
        ->set('password', 'secret-password')
        ->set('role', Role::Technician->value)
        ->call('createUser')
        ->assertHasNoErrors()
        ->assertSet('email', '')
        ->assertSee('User created.');

    $user = User::where('email', 'grey@example.com')->sole();
    expect($user->role)->toBe(Role::Technician)
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

test('creating a user requires every field', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->set('role', '')
        ->call('createUser')
        ->assertHasErrors(['name' => 'required', 'email' => 'required', 'password' => 'required', 'role' => 'required']);
});

test('email addresses must be unique', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->set('name', 'Dr Grey')
        ->set('email', 'taken@example.com')
        ->set('password', 'secret-password')
        ->call('createUser')
        ->assertHasErrors(['email' => 'unique']);
});

test('an unknown role is rejected', function () {
    $user = User::factory()->radiologist()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->call('updateRole', $user->id, 'superuser')
        ->assertHasErrors(['role']);

    expect($user->fresh()->role)->toBe(Role::Radiologist);
});

test('administrators can change another user\'s role', function () {
    $user = User::factory()->radiologist()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->call('updateRole', $user->id, Role::Admin->value)
        ->assertHasNoErrors();

    expect($user->fresh()->role)->toBe(Role::Admin);
});

test('administrators cannot change their own role', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserIndex::class)
        ->call('updateRole', $admin->id, Role::Radiologist->value)
        ->assertHasErrors(['users' => 'You cannot change your own role.']);

    expect($admin->fresh()->role)->toBe(Role::Admin);
});

test('administrators can delete another user', function () {
    $user = User::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->call('deleteUser', $user->id);

    $this->assertModelMissing($user);
});

test('administrators cannot delete themselves', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserIndex::class)
        ->call('deleteUser', $admin->id)
        ->assertHasErrors(['users' => 'You cannot delete your own account.']);

    $this->assertModelExists($admin);
});

test('non-administrators cannot call user management actions', function () {
    $user = User::factory()->create();

    Livewire::actingAs(User::factory()->technician()->create())
        ->test(UserIndex::class)
        ->assertForbidden();

    $this->assertModelExists($user);
});
