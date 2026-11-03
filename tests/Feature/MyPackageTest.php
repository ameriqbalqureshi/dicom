<?php

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Livewire\Billing\MyPackage;
use App\Models\Package;
use App\Models\Study;
use App\Models\Subscription;
use App\Models\User;
use Livewire\Livewire;

test('users see their current package and what they have used', function () {
    $subscription = Subscription::factory()
        ->for(Package::factory()->create(['name' => 'Gold Plan']))
        ->withLimits(['max_uploads' => 10, 'max_reports' => 0])
        ->create();
    Study::factory()->count(3)->create(['uploaded_by' => $subscription->user_id]);

    $this->actingAs($subscription->user)->get(route('package.show'))
        ->assertOk()
        ->assertSee('Gold Plan')
        ->assertSee('3 of 10')
        ->assertSee('Not included');
});

test('users without a package are told what they cannot do', function () {
    Livewire::actingAs(User::factory()->withoutPackage()->create())->test(MyPackage::class)
        ->assertSee('You do not have an active package');
});

test('administrators have no package page', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('package.show'))->assertForbidden();
});

test('users only see active, paid packages for their role', function () {
    $physician = User::factory()->physician()->create();
    Package::factory()->create(['name' => 'Physician Plus']);
    Package::factory()->forRole(Role::Patient)->create(['name' => 'Patient Plus']);
    Package::factory()->inactive()->create(['name' => 'Old Physician Plan']);

    Livewire::actingAs($physician)->test(MyPackage::class)
        ->assertSee('Physician Plus')
        ->assertDontSee('Patient Plus')
        ->assertDontSee('Old Physician Plan')
        ->assertDontSee('Free trial');
});

test('packages for another role, withdrawn packages and trials cannot be bought', function (Package $package) {
    Livewire::actingAs(User::factory()->physician()->create())->test(MyPackage::class)
        ->call('choose', $package->id)
        ->assertNotFound();
})->with([
    'other role' => fn () => Package::factory()->forRole(Role::Patient)->create(),
    'withdrawn' => fn () => Package::factory()->inactive()->create(),
    'trial' => fn () => Package::factory()->trial()->create(),
]);

test('buying a paid package records the payment for confirmation', function () {
    $physician = User::factory()->physician()->create();
    $package = Package::factory()->create(['name' => 'Physician Plus', 'price' => 7500]);

    Livewire::actingAs($physician)->test(MyPackage::class)
        ->call('choose', $package->id)
        ->call('purchase')
        ->assertHasErrors(['paymentMethod' => 'required', 'paymentReference' => 'required'])
        ->set('paymentMethod', 'not-a-method')
        ->call('purchase')
        ->assertHasErrors(['paymentMethod' => 'in'])
        ->set('paymentMethod', 'jazzcash')
        ->set('paymentReference', ' TXN-42 ')
        ->call('purchase')
        ->assertHasNoErrors()
        ->assertSee('will start as soon as we confirm your payment')
        ->assertSee('awaiting payment confirmation');

    $purchase = $physician->subscriptions()->pending()->sole();

    expect($purchase->package_id)->toBe($package->id)
        ->and($purchase->amount)->toBe(7500)
        ->and($purchase->payment_method)->toBe('jazzcash')
        ->and($purchase->payment_reference)->toBe('TXN-42')
        ->and($physician->subscriptions()->current()->count())->toBe(1);
});

test('only one purchase can wait for confirmation at a time, and it can be cancelled', function () {
    $pending = Subscription::factory()->pending()->for(User::factory()->physician(), 'user')->create();
    $package = Package::factory()->create();

    $component = Livewire::actingAs($pending->user)->test(MyPackage::class)
        ->call('choose', $package->id)
        ->set('paymentMethod', 'bank_transfer')
        ->set('paymentReference', 'TXN-2')
        ->call('purchase')
        ->assertHasErrors('purchase');

    $component->call('cancelPending', $pending->id)->assertSee('Purchase cancelled.');

    expect($pending->fresh()->status)->toBe(SubscriptionStatus::Cancelled);
});

test('users cannot cancel other people purchases', function () {
    $pending = Subscription::factory()->pending()->create();

    Livewire::actingAs(User::factory()->physician()->create())->test(MyPackage::class)
        ->call('cancelPending', $pending->id)
        ->assertNotFound();
});

test('a free package starts straight away and replaces the current one', function () {
    $physician = User::factory()->physician()->create();
    $previous = $physician->subscriptions()->sole();
    $package = Package::factory()->create(['name' => 'Community', 'price' => 0, 'max_uploads' => 2]);

    Livewire::actingAs($physician)->test(MyPackage::class)
        ->call('choose', $package->id)
        ->call('purchase')
        ->assertHasNoErrors()
        ->assertSee('Your Community package has started.');

    $current = $physician->subscriptions()->current()->sole();

    expect($current->package_id)->toBe($package->id)
        ->and($current->max_uploads)->toBe(2)
        ->and($previous->fresh()->status)->toBe(SubscriptionStatus::Replaced);
});

test('rejected purchases show the reason', function () {
    $rejected = Subscription::factory()->pending()->for(User::factory()->physician(), 'user')->create();
    $rejected->forceFill(['status' => SubscriptionStatus::Rejected, 'rejection_reason' => 'No payment found'])->save();

    Livewire::actingAs($rejected->user)->test(MyPackage::class)
        ->assertSee('No payment found')
        ->assertSee('Rejected');
});
