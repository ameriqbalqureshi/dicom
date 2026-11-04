<?php

use App\Enums\PaymentAccountType;
use App\Livewire\Billing\PaymentAccountIndex;
use App\Models\PaymentAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BillingSettings;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('only administrators can manage payment accounts', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('payment-accounts.index'))->assertOk();
    $this->actingAs(User::factory()->physician()->create())->get(route('payment-accounts.index'))->assertForbidden();
});

test('administrators add bank and mobile accounts', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(PaymentAccountIndex::class)
        ->call('create')
        ->set('name', 'Meezan Bank')
        ->set('accountTitle', 'Cath Lab Pvt Ltd')
        ->set('iban', 'PK36MEZN0001234567890123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Account added.')
        ->call('create')
        ->set('name', 'JazzCash')
        ->set('type', PaymentAccountType::MobileWallet->value)
        ->set('accountTitle', 'Cath Lab')
        ->set('accountNumber', '03001234567')
        ->set('iban', 'SHOULD-NOT-BE-KEPT')
        ->call('save')
        ->assertHasNoErrors();

    expect(PaymentAccount::where('name', 'Meezan Bank')->sole()->details())->toBe([
        'Account title' => 'Cath Lab Pvt Ltd',
        'IBAN' => 'PK36MEZN0001234567890123',
    ])->and(PaymentAccount::where('name', 'JazzCash')->sole()->details())->toBe([
        'Account title' => 'Cath Lab',
        'Mobile number' => '03001234567',
    ]);
});

test('an account needs a number or IBAN and a unique name', function () {
    $existing = PaymentAccount::factory()->create();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(PaymentAccountIndex::class)
        ->call('create')
        ->set('name', $existing->name)
        ->set('accountTitle', 'Title')
        ->call('save')
        ->assertHasErrors(['name' => 'unique', 'accountNumber' => 'required_without']);

    Livewire::actingAs($admin)->test(PaymentAccountIndex::class)
        ->call('create')
        ->set('type', PaymentAccountType::MobileWallet->value)
        ->set('iban', 'PK36')
        ->call('save')
        ->assertHasErrors(['accountNumber' => 'required']);

    Livewire::actingAs($admin)->test(PaymentAccountIndex::class)
        ->call('edit', $existing->id)
        ->call('save')
        ->assertHasNoErrors(['name']);
});

test('hidden accounts are not offered to buyers, and deleting one leaves past payments as they were', function () {
    $hidden = PaymentAccount::factory()->create(['name' => 'Hidden Bank']);
    $deleted = PaymentAccount::factory()->create(['name' => 'Deleted Bank']);
    $payment = Subscription::factory()->pending()->create(['payment_method' => 'Deleted Bank']);

    Livewire::actingAs(User::factory()->admin()->create())->test(PaymentAccountIndex::class)
        ->call('toggleActive', $hidden->id)
        ->call('delete', $deleted->id);

    $this->get(route('pricing'))->assertDontSee('Hidden Bank')->assertDontSee('Deleted Bank');

    expect($payment->fresh()->payment_method)->toBe('Deleted Bank');
});

test('administrators change the instructions and receipt email buyers see', function () {
    config(['site.contact.email' => 'contact@clinic.test']);
    $admin = User::factory()->admin()->create();
    $settings = app(BillingSettings::class);

    expect($settings->receiptEmail())->toBe('contact@clinic.test');

    Livewire::actingAs($admin)->test(PaymentAccountIndex::class)
        ->assertSet('receiptEmail', 'contact@clinic.test')
        ->set('paymentInstructions', 'Quote your user ID in the transfer.')
        ->set('receiptEmail', 'not-an-email')
        ->call('saveSettings')
        ->assertHasErrors(['receiptEmail' => 'email'])
        ->set('receiptEmail', 'billing@clinic.test')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($settings->paymentInstructions())->toBe('Quote your user ID in the transfer.')
        ->and($settings->receiptEmail())->toBe('billing@clinic.test');

    $this->get(route('payment-receipt'))->assertSee('billing@clinic.test');
});

test('upgrading brings across the accounts set up in the environment', function () {
    config(['billing.payment_accounts' => [
        'bank_transfer' => ['Bank' => 'Meezan Bank', 'Account title' => 'Cath Lab', 'Account number' => '0123', 'IBAN' => null, 'Branch' => null],
        'jazzcash' => ['Account title' => 'Cath Lab', 'Mobile number' => '03001234567'],
        'easypaisa' => ['Account title' => null, 'Mobile number' => null],
    ]]);

    Schema::drop('payment_accounts');
    (require database_path('migrations/2026_11_04_012848_create_payment_accounts_table.php'))->up();

    expect(PaymentAccount::query()->inDisplayOrder()->pluck('name')->all())->toBe(['Meezan Bank', 'JazzCash'])
        ->and(PaymentAccount::where('name', 'JazzCash')->sole()->type)->toBe(PaymentAccountType::MobileWallet);
});
