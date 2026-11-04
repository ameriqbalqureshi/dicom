<?php

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Livewire\Billing\PaymentIndex;
use App\Livewire\Site\PaymentReceiptForm;
use App\Models\Package;
use App\Models\PaymentAccount;
use App\Models\PaymentSubmission;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PackageStarted;
use App\Notifications\PaymentReceiptSubmitted;
use App\Notifications\PaymentSubmitted;
use App\Services\UserDeleter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->receipts = Storage::fake(Subscription::RECEIPT_DISK);
});

/**
 * The public form filled in with valid details.
 */
function filledReceiptForm(?Package $package = null): Testable
{
    return Livewire::test(PaymentReceiptForm::class)
        ->set('name', 'Dr. Buyer')
        ->set('accountIdentifier', 'buyer@clinic.test')
        ->set('packageId', (string) ($package ?? Package::factory()->create())->id)
        ->set('paymentAccountId', (string) PaymentAccount::factory()->create()->id)
        ->set('receipt', UploadedFile::fake()->image('receipt.jpg'));
}

test('anyone can send a receipt through the public form, and administrators are told', function () {
    $admin = User::factory()->admin()->create();
    $package = Package::factory()->create();

    $this->get(route('payment-receipt'))->assertOk()->assertSee('Send your payment receipt');

    filledReceiptForm($package)
        ->set('paymentAccountId', (string) PaymentAccount::factory()->create(['name' => 'Meezan Bank'])->id)
        ->set('phone', '03001234567')
        ->set('paymentReference', ' TXN-7 ')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    $submission = PaymentSubmission::sole();

    expect($submission->account_identifier)->toBe('buyer@clinic.test')
        ->and($submission->package_id)->toBe($package->id)
        ->and($submission->payment_method)->toBe('Meezan Bank')
        ->and($submission->payment_reference)->toBe('TXN-7');
    $this->receipts->assertExists($submission->receipt_path);
    Notification::assertSentTo($admin, PaymentReceiptSubmitted::class);
});

test('the public form does not reveal whether an account exists or touch it', function () {
    $user = User::factory()->physician()->create(['email' => 'buyer@clinic.test']);
    $package = Package::factory()->create();

    $known = filledReceiptForm($package)->call('submit')->assertSet('sent', true)->html();
    $unknown = filledReceiptForm($package)->set('accountIdentifier', 'nobody@clinic.test')->call('submit')->assertSet('sent', true)->html();

    expect($known)->toContain('we have your receipt')
        ->and($unknown)->toContain('we have your receipt')
        ->and($user->subscriptions()->pending()->exists())->toBeFalse()
        ->and(PaymentSubmission::count())->toBe(2);
});

test('the public form needs a receipt, an account on offer and a package on sale', function () {
    $form = Livewire::test(PaymentReceiptForm::class)
        ->set('packageId', (string) Package::factory()->trial()->create()->id)
        ->set('paymentAccountId', (string) PaymentAccount::factory()->inactive()->create()->id)
        ->call('submit')
        ->assertHasErrors(['name' => 'required', 'accountIdentifier' => 'required', 'receipt' => 'required', 'packageId' => 'in', 'paymentAccountId' => 'exists']);

    $form->set('receipt', UploadedFile::fake()->createWithContent('page.svg', '<svg onload="alert(1)"></svg>'))
        ->call('submit')
        ->assertHasErrors(['receipt' => 'mimes']);

    expect(PaymentSubmission::count())->toBe(0);
});

test('bots filling in the hidden field are ignored', function () {
    filledReceiptForm()->set('website', 'spam.example')->call('submit')->assertSet('sent', true);

    expect(PaymentSubmission::count())->toBe(0);
    Notification::assertNothingSent();
});

test('one visitor can only send so many receipts', function () {
    $form = filledReceiptForm();

    foreach (range(1, PaymentReceiptForm::MAX_ATTEMPTS) as $attempt) {
        RateLimiter::hit('payment-receipt-form:'.request()->ip(), 3600);
    }

    $form->call('submit')->assertHasErrors('receipt');

    expect(PaymentSubmission::count())->toBe(0);
});

test('receipts can only be seen by administrators', function () {
    $this->receipts->put('receipts/form.jpg', 'form receipt');
    $this->receipts->put('receipts/purchase.pdf', 'purchase receipt');
    $submission = PaymentSubmission::factory()->create(['receipt_path' => 'receipts/form.jpg']);
    $purchase = Subscription::factory()->pending()->create(['receipt_path' => 'receipts/purchase.pdf']);
    $routes = [route('payment-submissions.receipt', $submission), route('payments.receipt-file', $purchase)];

    foreach ($routes as $route) {
        $this->get($route)->assertRedirect(route('login'));
    }

    foreach ($routes as $route) {
        $this->actingAs($purchase->user)->get($route)->assertForbidden();
    }

    foreach ($routes as $route) {
        $this->actingAs(User::factory()->admin()->create())->get($route)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
});

test('a purchase without a receipt has none to show', function () {
    $purchase = Subscription::factory()->pending()->create();

    $this->actingAs(User::factory()->admin()->create())->get(route('payments.receipt-file', $purchase))->assertNotFound();
});

test('administrators see which account a receipt matches, by email or user ID', function (bool $byUserId) {
    $user = User::factory()->physician()->create(['name' => 'Dr. Matched', 'email' => 'Matched@Clinic.test']);
    PaymentSubmission::factory()->create(['account_identifier' => $byUserId ? (string) $user->id : 'matched@clinic.test']);

    Livewire::actingAs(User::factory()->admin()->create())->test(PaymentIndex::class)
        ->assertSee('Matches Dr. Matched')
        ->assertDontSee('No account has this user ID or email.');
})->with([
    'email in another case' => false,
    'user ID' => true,
]);

test('receipts are not matched to administrators or unknown accounts', function () {
    $admin = User::factory()->admin()->create();
    PaymentSubmission::factory()->create(['account_identifier' => $admin->email]);

    Livewire::actingAs($admin)->test(PaymentIndex::class)
        ->assertSee('No account has this user ID or email.');
});

test('linking a receipt makes it a purchase awaiting confirmation, without starting the package', function () {
    $user = User::factory()->physician()->create();
    $package = Package::factory()->create(['price' => 7500]);
    $submission = PaymentSubmission::factory()->for($package)->create([
        'account_identifier' => $user->email,
        'payment_method' => 'JazzCash',
        'payment_reference' => 'TXN-5',
    ]);

    Livewire::actingAs(User::factory()->admin()->create())->test(PaymentIndex::class)
        ->call('startLinking', $submission->id)
        ->assertSet('linkPackageId', (string) $package->id)
        ->call('linkSubmission')
        ->assertHasNoErrors()
        ->assertSee("Receipt linked to {$user->name}.");

    $purchase = $user->subscriptions()->pending()->sole();

    expect($purchase->package_id)->toBe($package->id)
        ->and($purchase->amount)->toBe(7500)
        ->and($purchase->payment_method)->toBe('JazzCash')
        ->and($purchase->payment_reference)->toBe('TXN-5')
        ->and($purchase->receipt_path)->toBe($submission->receipt_path)
        ->and($submission->fresh())->toBeNull();
    Notification::assertNotSentTo($user, PackageStarted::class);
    Notification::assertNothingSentTo(User::admins()->get());
});

test('a receipt cannot be linked to an unknown account, the wrong role package, or someone with a purchase waiting', function () {
    $admin = User::factory()->admin()->create();
    $physician = User::factory()->physician()->create();
    $submission = PaymentSubmission::factory()->for(Package::factory()->forRole(Role::Patient))->create(['account_identifier' => 'nobody@clinic.test']);

    $component = Livewire::actingAs($admin)->test(PaymentIndex::class)
        ->call('startLinking', $submission->id)
        ->call('linkSubmission')
        ->assertHasErrors('linkIdentifier')
        ->set('linkIdentifier', $physician->email)
        ->call('linkSubmission')
        ->assertHasErrors(['linkPackageId' => 'in']);

    Subscription::factory()->pending()->for($physician)->create();

    $component->set('linkPackageId', (string) Package::factory()->create()->id)
        ->call('linkSubmission')
        ->assertHasErrors('linkIdentifier');

    expect($submission->fresh())->not->toBeNull();
});

test('deleting a receipt removes its file', function () {
    $this->receipts->put('receipts/spam.jpg', 'spam');
    $submission = PaymentSubmission::factory()->create(['receipt_path' => 'receipts/spam.jpg']);

    Livewire::actingAs(User::factory()->admin()->create())->test(PaymentIndex::class)
        ->call('discardSubmission', $submission->id)
        ->assertSee('Receipt deleted.');

    $this->receipts->assertMissing('receipts/spam.jpg');
    expect($submission->fresh())->toBeNull();
});

test('only administrators can act on receipts', function (string $action) {
    $submission = PaymentSubmission::factory()->create();

    $component = Livewire::actingAs(User::factory()->admin()->create())->test(PaymentIndex::class);

    $this->actingAs(User::factory()->physician()->create());

    $component->call($action, $submission->id)->assertForbidden();

    expect($submission->fresh())->not->toBeNull();
})->with(['startLinking', 'discardSubmission']);

test('administrators record an emailed payment, which starts the package at once', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->technician()->create();
    $previous = $user->subscriptions()->sole();
    $package = Package::factory()->forRole(Role::Technician)->create(['price' => 4000, 'duration_days' => 30]);
    $account = PaymentAccount::factory()->create(['name' => 'Meezan Bank']);

    Livewire::actingAs($admin)->test(PaymentIndex::class)
        ->call('startRecording')
        ->set('recordIdentifier', (string) $user->id)
        ->set('recordPackageId', (string) $package->id)
        ->assertSet('recordAmount', '4000')
        ->set('recordAmount', '3500')
        ->set('recordAccountId', (string) $account->id)
        ->set('recordReference', 'EMAIL-1')
        ->set('recordReceipt', UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'))
        ->call('recordPayment')
        ->assertHasNoErrors()
        ->assertSee('Payment recorded.');

    $current = $user->subscriptions()->current()->sole();

    expect($current->package_id)->toBe($package->id)
        ->and($current->amount)->toBe(3500)
        ->and($current->payment_method)->toBe('Meezan Bank')
        ->and($current->payment_reference)->toBe('EMAIL-1')
        ->and($current->reviewed_by)->toBe($admin->id)
        ->and($previous->fresh()->status)->toBe(SubscriptionStatus::Replaced);
    $this->receipts->assertExists($current->receipt_path);
    Notification::assertSentTo($user, PackageStarted::class);
    Notification::assertNotSentTo($admin, PaymentSubmitted::class);
});

test('recording a payment needs an account, a package for their role and a positive amount', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->technician()->create();

    $component = Livewire::actingAs($admin)->test(PaymentIndex::class)
        ->call('startRecording')
        ->set('recordIdentifier', 'nobody@clinic.test')
        ->call('recordPayment')
        ->assertHasErrors('recordIdentifier')
        ->set('recordIdentifier', $user->email)
        ->set('recordPackageId', (string) Package::factory()->forRole(Role::Patient)->create()->id)
        ->set('recordAmount', '0')
        ->call('recordPayment')
        ->assertHasErrors(['recordPackageId' => 'in', 'recordAccountId' => 'required', 'recordAmount' => 'min']);

    expect($user->subscriptions()->count())->toBe(1);

    Subscription::factory()->pending()->for($user)->create();

    $component->set('recordPackageId', (string) Package::factory()->forRole(Role::Technician)->create()->id)
        ->set('recordAccountId', (string) PaymentAccount::factory()->create()->id)
        ->set('recordAmount', '100')
        ->call('recordPayment')
        ->assertHasErrors('recordIdentifier');

    expect($user->subscriptions()->current()->sole()->amount)->toBe(0);
});

test('a confirmed payment is emailed with a numbered PDF receipt', function () {
    $purchase = Subscription::factory()->pending()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(PaymentIndex::class)
        ->call('approve', $purchase->id);

    Notification::assertSentTo($purchase->user, PackageStarted::class, function (PackageStarted $notification) use ($purchase) {
        $attachment = $notification->toMail($purchase->user)->rawAttachments[0];

        return $attachment['name'] === 'payment-receipt-'.sprintf('R-%06d', $purchase->id).'.pdf'
            && str_starts_with($attachment['data'], '%PDF');
    });
});

test('packages given without payment come with no receipt', function () {
    $subscription = Subscription::factory()->create(['amount' => 0, 'reviewed_at' => now()]);

    expect((new PackageStarted($subscription))->toMail($subscription->user)->rawAttachments)->toBe([]);
});

test('buyers and administrators can open the receipt for a confirmed payment, and nobody else', function () {
    $purchase = Subscription::factory()->create(['amount' => 5000, 'reviewed_at' => now()]);
    $route = route('package.receipt', $purchase);

    $this->actingAs($purchase->user)->get($route)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs(User::factory()->admin()->create())->get($route)->assertOk();
    $this->actingAs(User::factory()->physician()->create())->get($route)->assertForbidden();

    $purchase->forceFill(['status' => SubscriptionStatus::Replaced])->save();
    $this->actingAs($purchase->user)->get($route)->assertOk();
});

test('payments that were not confirmed have no receipt', function (Subscription $purchase) {
    $this->actingAs($purchase->user)->get(route('package.receipt', $purchase))->assertForbidden();
})->with([
    'waiting' => fn () => Subscription::factory()->pending()->create(),
    'rejected' => fn () => Subscription::factory()->pending()->create(['status' => SubscriptionStatus::Rejected, 'reviewed_at' => now()]),
    'free' => fn () => Subscription::factory()->create(['amount' => 0, 'reviewed_at' => now()]),
]);

test('deleting a user deletes their receipts', function () {
    $this->receipts->put('receipts/theirs.jpg', 'theirs');
    $this->receipts->put('receipts/other.jpg', 'other');
    $purchase = Subscription::factory()->pending()->create(['receipt_path' => 'receipts/theirs.jpg']);
    Subscription::factory()->pending()->create(['receipt_path' => 'receipts/other.jpg']);

    app(UserDeleter::class)->delete($purchase->user);

    $this->receipts->assertMissing('receipts/theirs.jpg');
    $this->receipts->assertExists('receipts/other.jpg');
});
