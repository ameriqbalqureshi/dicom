<?php

use App\Enums\Role;
use App\Livewire\Site\ContactForm;
use App\Models\Package;
use App\Models\User;

test('guests can open every public page', function (string $routeName) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee(route('register'))
        ->assertSee(route('login'));
})->with(['home', 'pricing', 'about', 'contact', 'privacy', 'terms']);

test('the contact page shows the contact form', function () {
    $this->get(route('contact'))->assertSeeLivewire(ContactForm::class);
});

test('signed-in users see the home page with a link into the app instead of the sign-in links', function () {
    $this->actingAs(User::factory()->physician()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('studies.index'))
        ->assertDontSee(route('login'));
});

test('signed-in users who open the sign-in page are sent to their studies', function () {
    $this->actingAs(User::factory()->physician()->create())
        ->get(route('login'))
        ->assertRedirect(route('studies.index'));
});

test('pricing shows the active packages of the chosen account type, cheapest first after the trial', function () {
    Package::factory()->forRole(Role::Technician)->create(['name' => 'Lab Pro', 'price' => 9000]);
    Package::factory()->forRole(Role::Technician)->create(['name' => 'Lab Basic', 'price' => 3000]);
    Package::factory()->forRole(Role::Technician)->inactive()->create(['name' => 'Lab Retired']);
    Package::factory()->forRole(Role::Physician)->create(['name' => 'Cardiology Plus']);

    $this->get(route('pricing', ['for' => Role::Technician->value]))
        ->assertOk()
        ->assertSeeInOrder(['Free trial', 'Lab Basic', '3,000', 'Lab Pro', '9,000'])
        ->assertDontSee('Lab Retired')
        ->assertDontSee('Cardiology Plus');
});

test('pricing shows physician packages unless another sign-up role is chosen', function (?string $for) {
    Package::factory()->forRole(Role::Physician)->create(['name' => 'Cardiology Plus']);
    Package::factory()->forRole(Role::Patient)->create(['name' => 'Patient Plus']);

    $this->get(route('pricing', ['for' => $for]))
        ->assertOk()
        ->assertSee('Cardiology Plus')
        ->assertDontSee('Patient Plus');
})->with([null, 'admin', 'nonsense']);

test('pricing shows limits as unlimited, a number or not included', function () {
    Package::query()->delete();
    Package::factory()->create([
        'max_uploads' => null,
        'max_reports' => 0,
        'max_shares' => 25,
        'max_storage_mb' => 2048,
    ]);

    $this->get(route('pricing'))
        ->assertSee('Unlimited study uploads')
        ->assertSee('Reports signed <span class="sr-only">not included</span>', false)
        ->assertSee('25 shares')
        ->assertSee('2 GB of storage');
});

test('pricing tells visitors to get in touch when an account type has no packages', function () {
    Package::query()->where('role', Role::Patient)->delete();

    $this->get(route('pricing', ['for' => Role::Patient->value]))
        ->assertOk()
        ->assertSee('Packages for patients are being updated.');
});

test('pricing shows the payment accounts that have details and leaves out the rest', function () {
    config([
        'billing.payment_accounts.bank_transfer' => ['Bank' => 'Meezan Bank', 'IBAN' => 'PK36MEZN0001234567890123', 'Branch' => null],
        'billing.payment_accounts.jazzcash' => ['Account title' => null, 'Mobile number' => ''],
    ]);

    $this->get(route('pricing'))
        ->assertSee(['Meezan Bank', 'PK36MEZN0001234567890123'])
        ->assertDontSee('Branch')
        ->assertDontSee('<h3 class="font-medium text-white">JazzCash</h3>', false);
});
