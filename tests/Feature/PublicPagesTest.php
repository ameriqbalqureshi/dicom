<?php

use App\Enums\Role;
use App\Livewire\Site\ContactForm;
use App\Models\Package;
use App\Models\PaymentAccount;
use App\Models\User;

test('guests can open every public page', function (string $routeName) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee(route('register'))
        ->assertSee(route('login'));
})->with(['home', 'pricing', 'about', 'contact', 'privacy', 'terms', 'payment-receipt']);

test('public pages and the sign-in pages offer the share image in link previews', function (string $routeName) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.asset('og-image.jpg').'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);

    expect(public_path('og-image.jpg'))->toBeFile();
})->with(['home', 'pricing', 'about', 'contact', 'privacy', 'terms', 'login', 'register']);

test('search engines may index the marketing and sign-in pages', function (string $routeName) {
    $this->get(route($routeName))
        ->assertSee('<link rel="canonical" href="'.route($routeName).'">', false)
        ->assertDontSee('name="robots"', false);
})->with(['home', 'pricing', 'about', 'contact', 'privacy', 'terms', 'login', 'register']);

test('search engines are told not to index utility pages', function (string $routeName) {
    $this->get(route($routeName))
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertDontSee('rel="canonical"', false);
})->with(['payment-receipt', 'password.request']);

test('the password reset page, whose address holds a token, is not indexed', function () {
    $this->get(route('password.reset', ['token' => 'secret-token', 'email' => 'someone@example.com']))
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertDontSee('rel="canonical"', false);
});

test('pricing variants share one canonical address', function () {
    $this->get(route('pricing', ['for' => Role::Technician->value]))
        ->assertSee('<link rel="canonical" href="'.route('pricing').'">', false);
});

test('the home page describes itself and its questions to search engines', function () {
    $response = $this->get(route('home'))
        ->assertSee('<title>Online DICOM Viewer for Coronary Angiograms · '.config('app.name').'</title>', false);

    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $response->getContent(), $matches);
    $types = collect(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR)['@graph'])->keyBy('@type');

    expect($types->keys()->all())->toContain('WebSite', 'Organization', 'WebApplication', 'FAQPage')
        ->and($types['FAQPage']['mainEntity'][0]['name'])->toBe('Do I need to install anything?');
});

test('the sitemap lists the pages to index and nothing private', function () {
    $response = $this->get(route('sitemap'))->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('application/xml');

    $addresses = collect(simplexml_load_string($response->getContent())->xpath('//*[local-name()="loc"]'))
        ->map(fn (SimpleXMLElement $address): string => (string) $address)
        ->all();

    expect($addresses)->toEqualCanonicalizing([
        route('home'),
        route('pricing'),
        route('about'),
        route('contact'),
        route('privacy'),
        route('terms'),
        route('register'),
        route('marketing.apply'),
    ]);
});

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
        ->assertSee('Unlimited studies stored')
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

test('pricing shows the active payment accounts and only the details they have', function () {
    PaymentAccount::factory()->create(['name' => 'Meezan Bank', 'iban' => 'PK36MEZN0001234567890123', 'branch' => null]);
    PaymentAccount::factory()->inactive()->mobileWallet()->create(['name' => 'JazzCash']);

    $this->get(route('pricing'))
        ->assertSee(['Meezan Bank', 'PK36MEZN0001234567890123'])
        ->assertDontSee('Branch')
        ->assertDontSee('<h3 class="font-medium text-white">JazzCash</h3>', false);
});
