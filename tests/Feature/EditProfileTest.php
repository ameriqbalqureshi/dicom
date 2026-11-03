<?php

use App\Enums\Role;
use App\Livewire\Profile\EditProfile;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

test('everyone has a profile page, but only physicians have report details', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee(route('profile.edit'))
        ->assertSee('Change password')
        ->assertDontSee('Registration number');

    Livewire::actingAs($user)->test(EditProfile::class)->call('save')->assertForbidden();
})->with([Role::Admin, Role::Technician, Role::Patient]);

test('users can change their name without their password', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(EditProfile::class)
        ->set('name', ' New Name ')
        ->call('saveAccount')
        ->assertHasNoErrors()
        ->assertSee('Account details saved.');

    expect($admin->fresh()->name)->toBe('New Name')
        ->and($admin->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('changing the email address needs the current password and a confirmation of the new address', function () {
    Notification::fake();
    $user = User::factory()->admin()->create(['email' => 'old@clinic.test']);

    Livewire::actingAs($user)->test(EditProfile::class)
        ->set('email', 'new@clinic.test')
        ->call('saveAccount')
        ->assertHasErrors(['emailPassword' => 'required'])
        ->set('emailPassword', 'wrong-password')
        ->call('saveAccount')
        ->assertHasErrors(['emailPassword' => 'current_password']);

    expect($user->fresh()->email)->toBe('old@clinic.test');

    Livewire::actingAs($user)->test(EditProfile::class)
        ->set('email', 'new@clinic.test')
        ->set('emailPassword', 'password')
        ->call('saveAccount')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user->refresh();
    expect($user->email)->toBe('new@clinic.test')
        ->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('the new email address must not belong to someone else', function () {
    User::factory()->create(['email' => 'taken@clinic.test']);

    Livewire::actingAs(User::factory()->create())->test(EditProfile::class)
        ->set('email', 'taken@clinic.test')
        ->set('emailPassword', 'password')
        ->call('saveAccount')
        ->assertHasErrors(['email' => 'unique']);
});

test('users waiting to confirm their email can still open their profile to correct it', function () {
    $this->actingAs(User::factory()->unverified()->create())->get(route('profile.edit'))->assertOk();
});

test('changing the password needs the current password and a confirmed new one', function () {
    $user = User::factory()->technician()->create();

    Livewire::actingAs($user)->test(EditProfile::class)
        ->set('currentPassword', 'wrong-password')
        ->set('password', 'a-new-long-password')
        ->set('password_confirmation', 'something-else')
        ->call('updatePassword')
        ->assertHasErrors(['currentPassword' => 'current_password', 'password' => 'confirmed']);

    Livewire::actingAs($user)->test(EditProfile::class)
        ->set('currentPassword', 'password')
        ->set('password', 'a-new-long-password')
        ->set('password_confirmation', 'a-new-long-password')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('currentPassword', '')
        ->assertSee('Password changed.');

    expect(Hash::check('a-new-long-password', $user->fresh()->password))->toBeTrue();
});

test('physicians can save their credentials and signature', function () {
    $physician = User::factory()->physician()->create();

    Livewire::actingAs($physician)->test(EditProfile::class)
        ->set('qualifications', ' MBBS, FCPS (Cardiology) ')
        ->set('registrationNumber', 'PMDC-12345')
        ->set('signature', UploadedFile::fake()->image('signature.png', 300, 100))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Report details saved.');

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
