<?php

use App\Enums\Role;
use App\Livewire\Profile\EditProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(User::SIGNATURE_DISK);
});

test('physicians can open their profile from the navigation', function () {
    $this->actingAs(User::factory()->physician()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Registration number');
});

test('only physicians have a profile page', function (Role $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('profile.edit'))->assertForbidden();
})->with([Role::Admin, Role::Technician, Role::Patient]);

test('physicians can save their credentials and signature', function () {
    $physician = User::factory()->physician()->create();

    Livewire::actingAs($physician)->test(EditProfile::class)
        ->set('qualifications', ' MBBS, FCPS (Cardiology) ')
        ->set('registrationNumber', 'PMDC-12345')
        ->set('signature', UploadedFile::fake()->image('signature.png', 300, 100))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Profile saved.');

    $physician->refresh();

    expect($physician->qualifications)->toBe('MBBS, FCPS (Cardiology)')
        ->and($physician->registration_number)->toBe('PMDC-12345');
    Storage::disk(User::SIGNATURE_DISK)->assertExists($physician->signature_path);
});

test('clearing a credential stores nothing rather than an empty string', function () {
    $physician = User::factory()->physician()->create(['registration_number' => 'PMDC-1']);

    Livewire::actingAs($physician)->test(EditProfile::class)
        ->set('registrationNumber', '')
        ->call('save');

    expect($physician->fresh()->registration_number)->toBeNull();
});

test('uploading a new signature replaces the old file', function () {
    $physician = User::factory()->physician()->create();
    $oldPath = UploadedFile::fake()->image('old.png')->store("signatures/{$physician->id}", User::SIGNATURE_DISK);
    $physician->update(['signature_path' => $oldPath]);

    Livewire::actingAs($physician)->test(EditProfile::class)
        ->set('signature', UploadedFile::fake()->image('new.png'))
        ->call('save');

    Storage::disk(User::SIGNATURE_DISK)->assertMissing($oldPath);
    Storage::disk(User::SIGNATURE_DISK)->assertExists($physician->fresh()->signature_path);
});

test('physicians can remove their signature', function () {
    $physician = User::factory()->physician()->create();
    $path = UploadedFile::fake()->image('signature.png')->store("signatures/{$physician->id}", User::SIGNATURE_DISK);
    $physician->update(['signature_path' => $path]);

    Livewire::actingAs($physician)->test(EditProfile::class)->call('removeSignature');

    expect($physician->fresh()->signature_path)->toBeNull();
    Storage::disk(User::SIGNATURE_DISK)->assertMissing($path);
});

test('the signature must be a small PNG or JPEG image', function (UploadedFile $file) {
    Livewire::actingAs(User::factory()->physician()->create())->test(EditProfile::class)
        ->set('signature', $file)
        ->call('save')
        ->assertHasErrors('signature');
})->with([
    'not an image' => fn () => UploadedFile::fake()->create('signature.pdf', 10, 'application/pdf'),
    'too large' => fn () => UploadedFile::fake()->image('signature.png')->size(2048),
]);
