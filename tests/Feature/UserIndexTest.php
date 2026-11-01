<?php

use App\Enums\Role;
use App\Livewire\Users\UserIndex;
use App\Models\DicomInstance;
use App\Models\Series;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use App\Services\StudySharing;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
    $user = User::factory()->physician()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->call('updateRole', $user->id, 'superuser')
        ->assertHasErrors(['role']);

    expect($user->fresh()->role)->toBe(Role::Physician);
});

test('administrators can change another user\'s role', function () {
    $user = User::factory()->physician()->create();

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
        ->call('updateRole', $admin->id, Role::Physician->value)
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

test('a physician who changes role loses the studies shared with them and everything they forwarded', function () {
    $study = Study::factory()->create();
    [$physician, $surgeon] = User::factory()->physician()->count(2)->create();
    $sharing = app(StudySharing::class);
    $sharing->share($study, $study->uploader, $physician);
    $sharing->share($study, $physician, $surgeon);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->call('updateRole', $physician->id, Role::Technician->value);

    expect(StudyShare::count())->toBe(0);
});

test('deleting a user removes every share they made or received', function () {
    $study = Study::factory()->create();
    [$physician, $surgeon] = User::factory()->physician()->count(2)->create();
    $sharing = app(StudySharing::class);
    $sharing->share($study, $study->uploader, $physician);
    $sharing->share($study, $physician, $surgeon);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->call('deleteUser', $physician->id);

    expect(StudyShare::count())->toBe(0);
});

test('deleting a user also deletes the studies they uploaded and their files, but not other people\'s', function () {
    Storage::fake('dicom');
    Storage::disk('dicom')->put('a.dcm', 'dicom');
    $technician = User::factory()->technician()->create();
    $instance = DicomInstance::factory()
        ->for(Series::factory()->for(Study::factory()->for($technician, 'uploader')))
        ->create(['file_path' => 'a.dcm', 'uploaded_by' => $technician->id]);
    $study = $instance->series->study;
    $otherStudy = Study::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->call('deleteUser', $technician->id)
        ->assertSet('status', "{$technician->name} was deleted, with their study.");

    $this->assertModelMissing($technician);
    $this->assertModelMissing($study);
    $this->assertModelMissing($instance);
    $this->assertModelExists($otherStudy);
    Storage::disk('dicom')->assertMissing('a.dcm');
});

test('administrators can verify an email address for someone who cannot', function () {
    $user = User::factory()->unverified()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserIndex::class)
        ->call('verifyEmail', $user->id)
        ->assertHasNoErrors();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

test('administrators can find users by name or email, role and status', function () {
    $grey = User::factory()->physician()->create(['name' => 'Dr Grey', 'email' => 'grey@clinic.test']);
    $unverifiedPhysician = User::factory()->unverifiedPhysician()->create(['name' => 'Dr New']);
    $unconfirmed = User::factory()->patient()->unverified()->create(['name' => 'Pending Patient']);
    $technician = User::factory()->technician()->create(['name' => 'Lab Tech', 'email' => 'tech@lab.test']);

    $component = Livewire::actingAs(User::factory()->admin()->create(['name' => 'Admin']))->test(UserIndex::class);

    $component->set('search', 'grey@clinic')->assertSee($grey->name)->assertDontSee($technician->name);
    $component->set('search', '')->set('roleFilter', Role::Technician->value)->assertSee($technician->name)->assertDontSee($grey->name);
    $component->set('roleFilter', '')->set('show', 'physician-unverified')->assertSee($unverifiedPhysician->name)->assertDontSee($grey->name);
    $component->set('show', 'email-unverified')->assertSee($unconfirmed->name)->assertDontSee($grey->name);
});

test('the inactive filter lists users not seen for the chosen period, including old accounts never seen', function () {
    $recent = User::factory()->create(['name' => 'Recent User', 'last_seen_at' => now()->subDays(10)]);
    $away = User::factory()->create(['name' => 'Away User', 'last_seen_at' => now()->subDays(100)]);
    $neverSeen = User::factory()->create(['name' => 'Never Seen', 'last_seen_at' => null, 'created_at' => now()->subDays(200)]);
    $newcomer = User::factory()->create(['name' => 'New Comer', 'last_seen_at' => null, 'created_at' => now()->subDay()]);

    Livewire::actingAs(User::factory()->admin()->create(['last_seen_at' => now()]))
        ->test(UserIndex::class)
        ->set('show', 'inactive')
        ->set('inactiveDays', 90)
        ->assertSee([$away->name, $neverSeen->name])
        ->assertDontSee($recent->name)
        ->assertDontSee($newcomer->name)
        ->set('inactiveDays', 180)
        ->assertSee($neverSeen->name)
        ->assertDontSee($away->name);
});

test('administrators can export the listed users as CSV', function () {
    User::factory()->create(['name' => 'Away User', 'email' => 'away@clinic.test', 'last_seen_at' => now()->subDays(100)]);
    User::factory()->create(['name' => 'Recent User', 'last_seen_at' => now()]);

    Livewire::actingAs(User::factory()->admin()->create(['last_seen_at' => now()]))
        ->test(UserIndex::class)
        ->set('show', 'inactive')
        ->call('export')
        ->assertFileDownloaded('users-'.now()->format('Y-m-d').'.csv');
});
