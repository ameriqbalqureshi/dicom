<?php

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Livewire\Billing\MyPackage;
use App\Models\Package;
use App\Models\PaymentAccount;
use App\Models\Study;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    $account = PaymentAccount::factory()->mobileWallet()->create(['name' => 'JazzCash']);
    $hiddenAccount = PaymentAccount::factory()->inactive()->create();

    Livewire::actingAs($physician)->test(MyPackage::class)
        ->call('choose', $package->id)
        ->call('purchase')
        ->assertHasErrors(['paymentAccountId' => 'required', 'paymentReference' => 'required_without', 'receipt' => 'required_without'])
        ->set('paymentAccountId', (string) $hiddenAccount->id)
        ->call('purchase')
        ->assertHasErrors(['paymentAccountId' => 'exists'])
        ->set('paymentAccountId', (string) $account->id)
        ->set('paymentReference', ' TXN-42 ')
        ->call('purchase')
        ->assertHasNoErrors()
        ->assertSee('will start as soon as we confirm your payment')
        ->assertSee('awaiting payment confirmation');

    $purchase = $physician->subscriptions()->pending()->sole();

    expect($purchase->package_id)->toBe($package->id)
        ->and($purchase->amount)->toBe(7500)
        ->and($purchase->payment_method)->toBe('JazzCash')
        ->and($purchase->payment_reference)->toBe('TXN-42')
        ->and($purchase->receipt_path)->toBeNull()
        ->and($physician->subscriptions()->current()->count())->toBe(1);
});

test('buyers can attach a receipt instead of entering a reference', function () {
    Storage::fake(Subscription::RECEIPT_DISK);
    $physician = User::factory()->physician()->create();

    Livewire::actingAs($physician)->test(MyPackage::class)
        ->call('choose', Package::factory()->create()->id)
        ->set('paymentAccountId', (string) PaymentAccount::factory()->create()->id)
        ->set('receipt', UploadedFile::fake()->image('receipt.jpg'))
        ->call('purchase')
        ->assertHasNoErrors()
        ->assertSee('receipt attached');

    $purchase = $physician->subscriptions()->pending()->sole();

    expect($purchase->payment_reference)->toBeNull();
    Storage::disk(Subscription::RECEIPT_DISK)->assertExists($purchase->receipt_path);
});

test('receipts must be a photo or PDF of up to 5 MB', function (UploadedFile $file) {
    Storage::fake(Subscription::RECEIPT_DISK);

    Livewire::actingAs(User::factory()->physician()->create())->test(MyPackage::class)
        ->call('choose', Package::factory()->create()->id)
        ->set('paymentAccountId', (string) PaymentAccount::factory()->create()->id)
        ->set('receipt', $file)
        ->call('purchase')
        ->assertHasErrors('receipt');
})->with([
    'not a photo or PDF' => fn () => UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>'),
    'too large' => fn () => UploadedFile::fake()->create('receipt.pdf', 5121, 'application/pdf'),
]);

test('cancelling a purchase deletes its receipt', function () {
    $disk = Storage::fake(Subscription::RECEIPT_DISK);
    $disk->put('receipts/receipt.jpg', 'image');
    $pending = Subscription::factory()->pending()->for(User::factory()->physician(), 'user')->create(['receipt_path' => 'receipts/receipt.jpg']);

    Livewire::actingAs($pending->user)->test(MyPackage::class)->call('cancelPending', $pending->id);

    $disk->assertMissing('receipts/receipt.jpg');
    expect($pending->fresh()->receipt_path)->toBeNull();
});

test('the package page shows the user ID to quote when sending a receipt', function () {
    $physician = User::factory()->physician()->create();

    $this->actingAs($physician)->get(route('package.show'))->assertSee("Your user ID: <span class=\"select-all font-mono text-slate-200\">{$physician->id}</span>", false);
});

test('only one purchase can wait for confirmation at a time, and it can be cancelled', function () {
    $pending = Subscription::factory()->pending()->for(User::factory()->physician(), 'user')->create();
    $package = Package::factory()->create();

    $component = Livewire::actingAs($pending->user)->test(MyPackage::class)
        ->call('choose', $package->id)
        ->set('paymentAccountId', (string) PaymentAccount::factory()->create()->id)
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
