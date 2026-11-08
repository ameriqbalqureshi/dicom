<?php

use App\Enums\Role;
use App\Models\Study;
use App\Models\User;

test('every role that works with studies can browse the study list and upload studies', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->can('viewAny', Study::class))->toBeTrue()
        ->and($user->can('create', Study::class))->toBeTrue();
})->with([Role::Admin, Role::Technician, Role::Physician, Role::Patient]);

test('marketing officers cannot browse the study list or upload studies', function () {
    $user = User::factory()->marketingOfficer()->create();

    expect($user->can('viewAny', Study::class))->toBeFalse()
        ->and($user->can('create', Study::class))->toBeFalse();
});

test('uploaders can view and delete their own studies', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);
    $study = Study::factory()->for($user, 'uploader')->create();

    expect($user->can('view', $study))->toBeTrue()
        ->and($user->can('delete', $study))->toBeTrue();
})->with(Role::cases());

test('users cannot view or delete studies uploaded by someone else', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);
    $study = Study::factory()->create();

    expect($user->can('view', $study))->toBeFalse()
        ->and($user->can('delete', $study))->toBeFalse();
})->with([Role::Technician, Role::Physician, Role::Patient]);

test('administrators can view and delete every study', function () {
    $admin = User::factory()->admin()->create();
    $study = Study::factory()->create();

    expect($admin->can('view', $study))->toBeTrue()
        ->and($admin->can('delete', $study))->toBeTrue();
});

test('only administrators can manage users', function (Role $role, bool $allowed) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->can('manage-users'))->toBe($allowed);
})->with([
    'admin' => [Role::Admin, true],
    'technician' => [Role::Technician, false],
    'physician' => [Role::Physician, false],
]);
