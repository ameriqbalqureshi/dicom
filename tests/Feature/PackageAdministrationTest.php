<?php

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Livewire\Billing\PackageIndex;
use App\Livewire\Billing\PaymentIndex;
use App\Livewire\Users\UserIndex;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Livewire\Livewire;

test('only administrators can manage packages and payments', function (string $routeName) {
    $this->actingAs(User::factory()->admin()->create())->get(route($routeName))->assertOk();

    foreach ([Role::Physician, Role::Technician, Role::Patient] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route($routeName))->assertForbidden();
    }
})->with(['packages.index', 'payments.index']);

test('administrators create packages where blank limits are unlimited and zero is not included', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(PackageIndex::class)
        ->call('create')
        ->set('name', 'Clinic Yearly')
        ->set('role', Role::Technician->value)
        ->set('price', '40000')
        ->set('durationDays', '365')
        ->set('maxUploads', '1000')
        ->set('maxReports', '0')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Package created.');

    $package = Package::where('name', 'Clinic Yearly')->sole();

    expect($package->role)->toBe(Role::Technician)
        ->and($package->price)->toBe(40000)
        ->and($package->duration_days)->toBe(365)
        ->and($package->max_uploads)->toBe(1000)
        ->and($package->max_reports)->toBe(0)
        ->and($package->max_shares)->toBeNull()
        ->and($package->max_storage_mb)->toBeNull();
});

test('packages cannot be made for administrators', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(PackageIndex::class)
        ->call('create')
        ->set('name', 'Admin plan')
        ->set('role', Role::Admin->value)
        ->call('save')
        ->assertHasErrors(['role' => 'in']);
});

test('making a package the trial makes it free and replaces the role previous trial', function () {
    $oldTrial = Package::trialFor(Role::Patient);

    Livewire::actingAs(User::factory()->admin()->create())->test(PackageIndex::class)
        ->call('create')
        ->set('name', 'New patient trial')
        ->set('role', Role::Patient->value)
        ->set('price', '999')
        ->set('isTrial', true)
        ->call('save')
        ->assertHasNoErrors();

    $newTrial = Package::trialFor(Role::Patient);

    expect($newTrial->name)->toBe('New patient trial')
        ->and($newTrial->price)->toBe(0)
        ->and($oldTrial->fresh()->is_trial)->toBeFalse();
});

test('editing a package does not change the limits of current subscribers', function () {
    $subscription = Subscription::factory()->for(Package::factory()->create(['max_uploads' => 10]))->withLimits(['max_uploads' => 10])->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(PackageIndex::class)
        ->call('edit', $subscription->package_id)
        ->assertSet('maxUploads', '10')
        ->set('maxUploads', '2')
        ->call('save')
        ->assertHasNoErrors();

    expect($subscription->package->fresh()->max_uploads)->toBe(2)
        ->and($subscription->fresh()->max_uploads)->toBe(10);
});

test('packages that were bought can be withdrawn but not deleted', function () {
    $admin = User::factory()->admin()->create();
    $bought = Subscription::factory()->create()->package;
    $unused = Package::factory()->create();

    Livewire::actingAs($admin)->test(PackageIndex::class)
        ->call('delete', $bought->id)
        ->assertSee('cannot be deleted')
        ->call('toggleActive', $bought->id)
        ->call('delete', $unused->id);

    expect($bought->fresh()->is_active)->toBeFalse()
        ->and($unused->fresh())->toBeNull();
});

test('confirming a payment starts the package with fresh usage and replaces the current one', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->physician()->create();
    $previous = $user->subscriptions()->sole();
    $previous->update(['uploads_used' => 4]);
    $package = Package::factory()->create(['duration_days' => 30, 'max_uploads' => 50]);
    $purchase = Subscription::factory()->pending()->for($user)->for($package)->create();

    Livewire::actingAs($admin)->test(PaymentIndex::class)
        ->assertSee($purchase->payment_reference)
        ->call('approve', $purchase->id)
        ->assertSee('Payment confirmed.');

    $purchase->refresh();

    expect($purchase->status)->toBe(SubscriptionStatus::Active)
        ->and($purchase->isCurrent())->toBeTrue()
        ->and($purchase->max_uploads)->toBe(50)
        ->and($purchase->uploads_used)->toBe(0)
        ->and($purchase->ends_at->diffInDays(now()->addDays(30), true))->toBeLessThan(1)
        ->and($purchase->reviewed_by)->toBe($admin->id)
        ->and($previous->fresh()->status)->toBe(SubscriptionStatus::Replaced);
});

test('rejecting a payment needs a reason', function () {
    $purchase = Subscription::factory()->pending()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(PaymentIndex::class)
        ->call('startRejecting', $purchase->id)
        ->call('reject')
        ->assertHasErrors(['rejectionReason' => 'required'])
        ->set('rejectionReason', 'No payment found with this reference')
        ->call('reject')
        ->assertHasNoErrors();

    expect($purchase->fresh())
        ->status->toBe(SubscriptionStatus::Rejected)
        ->rejection_reason->toBe('No payment found with this reference')
        ->starts_at->toBeNull();
});

test('reviewed payments cannot be reviewed again', function () {
    $purchase = Subscription::factory()->pending()->create();
    $purchase->forceFill(['status' => SubscriptionStatus::Rejected])->save();

    Livewire::actingAs(User::factory()->admin()->create())->test(PaymentIndex::class)
        ->call('approve', $purchase->id)
        ->assertNotFound();
});

test('administrators verify physicians who signed up', function () {
    $physician = User::factory()->unverifiedPhysician()->create(['registration_number' => 'PMDC-77']);

    Livewire::actingAs(User::factory()->admin()->create())->test(UserIndex::class)
        ->assertSee('Not verified')
        ->assertSee('PMDC-77')
        ->call('verifyPhysician', $physician->id)
        ->assertSee('is verified');

    expect($physician->fresh()->isVerifiedPhysician())->toBeTrue();
});

test('administrators can give a user a package without payment', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->technician()->create();
    $package = Package::factory()->forRole(Role::Technician)->create(['name' => 'Courtesy']);

    Livewire::actingAs($admin)->test(UserIndex::class)
        ->call('assignPackage', $user->id, (string) $package->id)
        ->assertSee("{$user->name} now has the Courtesy package.");

    $current = $user->subscriptions()->current()->sole();

    expect($current->package_id)->toBe($package->id)
        ->and($current->amount)->toBe(0)
        ->and($current->reviewed_by)->toBe($admin->id);
});

test('packages for another role cannot be assigned', function () {
    $user = User::factory()->technician()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(UserIndex::class)
        ->call('assignPackage', $user->id, (string) Package::factory()->forRole(Role::Patient)->create()->id)
        ->assertNotFound();
});

test('users added by an administrator start on their trial, and physicians are verified', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(UserIndex::class)
        ->set('name', 'Dr. Added')
        ->set('email', 'added@clinic.test')
        ->set('password', 'secret-password')
        ->set('role', Role::Physician->value)
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::where('email', 'added@clinic.test')->sole();

    expect($user->isVerifiedPhysician())->toBeTrue()
        ->and($user->subscriptions()->sole()->package->is(Package::trialFor(Role::Physician)))->toBeTrue();
});

test('making someone a physician verifies them', function () {
    $technician = User::factory()->technician()->create(['physician_verified_at' => null]);

    Livewire::actingAs(User::factory()->admin()->create())->test(UserIndex::class)
        ->call('updateRole', $technician->id, Role::Physician->value);

    expect($technician->fresh()->isVerifiedPhysician())->toBeTrue();
});
