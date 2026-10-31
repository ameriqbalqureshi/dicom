<?php

use App\Enums\Role;
use App\Models\Study;
use App\Models\User;

test('every role can browse and view studies', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);
    $study = Study::factory()->create();

    expect($user->can('viewAny', Study::class))->toBeTrue()
        ->and($user->can('view', $study))->toBeTrue();
})->with(Role::cases());

test('only administrators and technicians can upload studies', function (Role $role, bool $allowed) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->can('create', Study::class))->toBe($allowed);
})->with([
    'admin' => [Role::Admin, true],
    'technician' => [Role::Technician, true],
    'radiologist' => [Role::Radiologist, false],
]);

test('only administrators can delete studies', function (Role $role, bool $allowed) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->can('delete', Study::factory()->create()))->toBe($allowed);
})->with([
    'admin' => [Role::Admin, true],
    'technician' => [Role::Technician, false],
    'radiologist' => [Role::Radiologist, false],
]);

test('only administrators can manage users', function (Role $role, bool $allowed) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->can('manage-users'))->toBe($allowed);
})->with([
    'admin' => [Role::Admin, true],
    'technician' => [Role::Technician, false],
    'radiologist' => [Role::Radiologist, false],
]);
