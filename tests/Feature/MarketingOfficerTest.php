<?php

use App\Enums\CommissionStatus;
use App\Enums\MarketingApplicationStatus;
use App\Enums\Role;
use App\Livewire\Auth\Register;
use App\Livewire\Marketing\MarketingIndex;
use App\Livewire\Marketing\OfficerDashboard;
use App\Livewire\Marketing\OfficerShow;
use App\Livewire\Site\MarketingApplicationForm;
use App\Livewire\Studies\StudyIndex;
use App\Livewire\Users\UserIndex;
use App\Livewire\Users\UserShow;
use App\Models\Commission;
use App\Models\CommissionPayout;
use App\Models\MarketingApplication;
use App\Models\MarketingProfile;
use App\Models\ReferralChange;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\MarketingApplicationApproved;
use App\Notifications\MarketingApplicationRejected;
use App\Notifications\MarketingApplicationSubmitted;
use App\Services\Marketing\MarketingSettings;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
});

/**
 * The public application form filled in with valid details.
 */
function filledApplicationForm(string $email = 'applicant@example.test'): Testable
{
    return Livewire::test(MarketingApplicationForm::class)
        ->set('name', 'Sara Ahmed')
        ->set('email', $email)
        ->set('phone', '0300 1234567')
        ->set('city', 'Lahore')
        ->set('experience', 'I know many cardiologists.');
}

/**
 * The signup form filled in with valid details and the given referral code.
 */
function filledSignupForm(string $referralCode, string $email = 'client@clinic.test'): Testable
{
    return Livewire::test(Register::class)
        ->set('name', 'Client Person')
        ->set('email', $email)
        ->set('role', Role::Technician->value)
        ->set('password', 'a-long-enough-password')
        ->set('password_confirmation', 'a-long-enough-password')
        ->set('referralCode', $referralCode);
}

test('the public page shows the current rates and the application form', function () {
    $settings = app(MarketingSettings::class);
    $rules = $settings->all();
    $rules['rates']['physician']['first'] = 27.5;
    $settings->update($rules);

    $this->get(route('marketing.apply'))
        ->assertOk()
        ->assertSee('27.5%')
        ->assertSeeLivewire(MarketingApplicationForm::class);
});

test('anyone can apply, and administrators are told', function () {
    $admin = User::factory()->admin()->create();

    filledApplicationForm()->call('submit')->assertHasNoErrors()->assertSet('sent', true);

    $application = MarketingApplication::sole();
    expect($application->status)->toBe(MarketingApplicationStatus::Pending)
        ->and($application->email)->toBe('applicant@example.test');

    Notification::assertSentTo($admin, MarketingApplicationSubmitted::class);
});

test('an application needs a name, a valid email and a phone number', function () {
    Livewire::test(MarketingApplicationForm::class)
        ->set('email', 'not-an-email')
        ->call('submit')
        ->assertHasErrors(['name', 'email', 'phone']);

    expect(MarketingApplication::count())->toBe(0);
});

test('applying again before a decision replaces the earlier application', function () {
    filledApplicationForm()->call('submit');
    filledApplicationForm('APPLICANT@example.test')->set('city', 'Karachi')->call('submit');

    expect(MarketingApplication::sole()->city)->toBe('Karachi');
});

test('the form says the same thing when the email already has an account', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    filledApplicationForm('taken@example.test')->call('submit')->assertHasNoErrors()->assertSet('sent', true);
});

test('bots that fill in the hidden field are ignored', function () {
    filledApplicationForm()->set('website', 'spam.example')->call('submit')->assertSet('sent', true);

    expect(MarketingApplication::count())->toBe(0);
});

test('one visitor can only apply a few times in an hour', function () {
    foreach (range(1, MarketingApplicationForm::MAX_ATTEMPTS) as $attempt) {
        filledApplicationForm("applicant{$attempt}@example.test")->call('submit')->assertHasNoErrors();
    }

    filledApplicationForm('another@example.test')->call('submit')->assertHasErrors('email');

    expect(MarketingApplication::count())->toBe(MarketingApplicationForm::MAX_ATTEMPTS);
});

test('approving an application creates a verified officer with a code and invites them', function () {
    $admin = User::factory()->admin()->create();
    $application = MarketingApplication::factory()->create(['name' => 'Sara Ahmed', 'email' => 'sara@example.test', 'phone' => '0300']);

    Livewire::actingAs($admin)->test(MarketingIndex::class)->call('approve', $application->id)->assertHasNoErrors();

    $officer = User::query()->where('email', 'sara@example.test')->sole();
    expect($officer->isMarketingOfficer())->toBeTrue()
        ->and($officer->hasVerifiedEmail())->toBeTrue()
        ->and($officer->subscriptions()->count())->toBe(0)
        ->and($officer->marketingProfile->referral_code)->toMatch('/^SARAAH\d{4}$/')
        ->and($officer->marketingProfile->phone)->toBe('0300')
        ->and($application->fresh()->status)->toBe(MarketingApplicationStatus::Approved)
        ->and($application->fresh()->user_id)->toBe($officer->id);

    Notification::assertSentTo($officer, MarketingApplicationApproved::class, function (MarketingApplicationApproved $notification) use ($officer): bool {
        return str_contains($notification->toMail($officer)->actionUrl, '/reset-password/');
    });
});

test('an application from an address that already has an account cannot be approved', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.test']);
    $application = MarketingApplication::factory()->create(['email' => 'TAKEN@example.test']);

    Livewire::actingAs($admin)->test(MarketingIndex::class)->call('approve', $application->id)->assertHasErrors('applications');

    expect($application->fresh()->isPending())->toBeTrue()
        ->and(User::query()->marketingOfficers()->count())->toBe(0);
});

test('rejecting an application emails the applicant and creates no account', function () {
    $admin = User::factory()->admin()->create();
    $application = MarketingApplication::factory()->create(['email' => 'no@example.test']);

    Livewire::actingAs($admin)->test(MarketingIndex::class)
        ->call('startRejecting', $application->id)
        ->set('rejectionReason', 'Not in our area')
        ->call('reject')
        ->assertHasNoErrors();

    expect($application->fresh()->status)->toBe(MarketingApplicationStatus::Rejected)
        ->and(User::query()->where('email', 'no@example.test')->exists())->toBeFalse();

    Notification::assertSentOnDemand(MarketingApplicationRejected::class, fn (MarketingApplicationRejected $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'no@example.test' && $notification->reason === 'Not in our area');
});

test('an application that was already reviewed cannot be approved', function () {
    $admin = User::factory()->admin()->create();
    $application = MarketingApplication::factory()->create();
    $application->forceFill(['status' => MarketingApplicationStatus::Rejected])->save();

    Livewire::actingAs($admin)->test(MarketingIndex::class)->call('approve', $application->id)->assertHasErrors('applications');

    expect(User::query()->marketingOfficers()->count())->toBe(0);
});

test('a referral link fills in the code at signup, and the new client belongs to the officer', function () {
    $officer = User::factory()->marketingOfficer()->create();
    $code = $officer->marketingProfile->referral_code;

    Livewire::withQueryParams(['ref' => strtolower($code)])
        ->test(Register::class)
        ->assertSet('referralCode', $code)
        ->set('name', 'Client Person')
        ->set('email', 'client@clinic.test')
        ->set('role', Role::Technician->value)
        ->set('password', 'a-long-enough-password')
        ->set('password_confirmation', 'a-long-enough-password')
        ->call('register')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'client@clinic.test')->sole()->referred_by)->toBe($officer->id);
});

test('a malformed referral link does not break the signup page', function () {
    Livewire::withQueryParams(['ref' => ['X']])->test(Register::class)->assertSet('referralCode', '');
});

test('people can sign up without a referral code', function () {
    filledSignupForm('')->call('register')->assertHasNoErrors();

    expect(User::query()->where('email', 'client@clinic.test')->sole()->referred_by)->toBeNull();
});

test('a referral code that does not work stops the signup with a message', function (Closure $makeCode) {
    filledSignupForm($makeCode())->call('register')->assertHasErrors('referralCode');

    expect(User::query()->where('email', 'client@clinic.test')->exists())->toBeFalse();
})->with([
    'unknown code' => fn () => 'NOSUCHCODE',
    'deactivated officer' => function () {
        $officer = User::factory()->create(['role' => Role::MarketingOfficer]);

        return MarketingProfile::factory()->deactivated()->for($officer)->create()->referral_code;
    },
    'someone who stopped being an officer' => fn () => MarketingProfile::factory()->for(User::factory()->physician())->create()->referral_code,
]);

test('an officer cannot refer themselves using another spelling of their email address', function () {
    $officer = User::factory()->marketingOfficer()->create(['email' => 'sara.ahmed@example.test']);

    filledSignupForm($officer->marketingProfile->referral_code, 'saraahmed+client@example.test')
        ->call('register')
        ->assertHasErrors('referralCode');
});

test('marketing officers have no access to studies, uploads, patient records or packages', function () {
    $officer = User::factory()->marketingOfficer()->create();

    $this->actingAs($officer);

    $this->get(route('studies.upload'))->assertForbidden();
    $this->get(route('patient-records.index'))->assertForbidden();
    $this->get(route('package.show'))->assertForbidden();
    $this->get(route('packages.index'))->assertForbidden();
    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('marketing.index'))->assertForbidden();
});

test('marketing officers land on their own page after signing in', function () {
    $officer = User::factory()->marketingOfficer()->create();

    Livewire::actingAs($officer)->test(StudyIndex::class)->assertRedirect(route('marketing.dashboard'));
});

test('other users cannot open the officer dashboard', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('marketing.dashboard'))
        ->assertForbidden();
})->with(['admin', 'physician', 'technician', 'patient']);

test('the dashboard shows an officer their link, earnings and clients, without private details', function () {
    $officer = User::factory()->marketingOfficer()->create();
    User::factory()->patient()->create(['name' => 'Ali Raza Khan', 'email' => 'ali@private.test', 'referred_by' => $officer->id]);
    User::factory()->physician()->create(['name' => 'Dr Sana Malik', 'email' => 'sana@private.test', 'referred_by' => $officer->id]);
    User::factory()->physician()->create(['name' => 'Someone Elses Client']);
    Commission::factory()->create(['officer_id' => $officer->id, 'amount' => 1234]);

    $this->actingAs($officer)
        ->get(route('marketing.dashboard'))
        ->assertOk()
        ->assertSee($officer->marketingProfile->referralLink())
        ->assertSee('1,234')
        ->assertSee('Ali K.')
        ->assertSee('Dr Sana Malik')
        ->assertDontSee('Ali Raza Khan')
        ->assertDontSee('ali@private.test')
        ->assertDontSee('sana@private.test')
        ->assertDontSee('Someone Elses Client');
});

test('an officer can save how they want to be paid', function () {
    $officer = User::factory()->marketingOfficer()->create();

    Livewire::actingAs($officer)->test(OfficerDashboard::class)
        ->set('phone', '0300 1234567')
        ->set('payoutDetails', 'JazzCash 0300 1234567')
        ->call('savePayoutDetails')
        ->assertHasNoErrors();

    expect($officer->marketingProfile->fresh()->payout_details)->toBe('JazzCash 0300 1234567');
});

test('a statement can be opened by the officer paid and administrators only', function () {
    $officer = User::factory()->marketingOfficer()->create();
    $payout = CommissionPayout::factory()->create(['officer_id' => $officer->id]);
    Commission::factory()->paid()->create(['officer_id' => $officer->id, 'payout_id' => $payout->id]);

    $this->actingAs($officer)->get(route('marketing.statement', $payout))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs(User::factory()->admin()->create())->get(route('marketing.statement', $payout))->assertOk();
    $this->actingAs(User::factory()->marketingOfficer()->create())->get(route('marketing.statement', $payout))->assertForbidden();
    $this->actingAs(User::factory()->physician()->create())->get(route('marketing.statement', $payout))->assertForbidden();
});

test('only administrators can manage marketing', function (string $state) {
    $officer = User::factory()->marketingOfficer()->create();

    $this->actingAs(User::factory()->{$state}()->create());

    $this->get(route('marketing.index'))->assertForbidden();
    $this->get(route('marketing.officers.show', $officer))->assertForbidden();
})->with(['physician', 'technician', 'patient', 'marketingOfficer']);

test('the officer page only opens for marketing officers', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('marketing.officers.show', User::factory()->physician()->create()))
        ->assertNotFound();
});

test('administrators can change the commission rules', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(MarketingIndex::class)
        ->set('rates.physician.first', '25.5')
        ->set('renewalMonths', '6')
        ->set('holdDays', '7')
        ->call('saveRules')
        ->assertHasNoErrors();

    $rules = app(MarketingSettings::class)->all();
    expect($rules['rates']['physician']['first'])->toBe(25.5)
        ->and($rules['rates']['patient']['first'])->toEqual(10)
        ->and($rules['renewal_months'])->toBe(6)
        ->and($rules['hold_days'])->toBe(7);
});

test('commission rules must be sensible', function (string $property, string $value) {
    Livewire::actingAs(User::factory()->admin()->create())->test(MarketingIndex::class)
        ->set($property, $value)
        ->call('saveRules')
        ->assertHasErrors($property);
})->with([
    'rate above 100%' => ['rates.physician.first', '101'],
    'negative rate' => ['rates.patient.renewal', '-1'],
    'negative hold' => ['holdDays', '-1'],
    'fractional months' => ['renewalMonths', '1.5'],
]);

test('an administrator can pay an officer, add a bonus and set custom rates from the officer page', function () {
    $admin = User::factory()->admin()->create();
    $officer = User::factory()->marketingOfficer()->create();
    Commission::factory()->create(['officer_id' => $officer->id, 'amount' => 1000]);

    Livewire::actingAs($admin)->test(OfficerShow::class, ['user' => $officer])
        ->set('firstPaymentRate', '30')
        ->call('saveProfile')
        ->set('bonusAmount', '500')
        ->set('bonusReason', 'Great month')
        ->call('addBonus')
        ->set('paymentMethod', 'JazzCash')
        ->set('paymentReference', 'TXN1')
        ->call('pay')
        ->assertHasNoErrors();

    expect(CommissionPayout::sole()->amount)->toBe(1500)
        ->and($officer->marketingProfile->fresh()->first_payment_rate)->toBe(30.0)
        ->and($officer->marketingProfile->fresh()->renewal_rate)->toBeNull();
});

test('paying an officer with nothing payable shows a message', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(OfficerShow::class, ['user' => User::factory()->marketingOfficer()->create()])
        ->set('paymentMethod', 'JazzCash')
        ->call('pay')
        ->assertHasErrors('paymentMethod');

    expect(CommissionPayout::count())->toBe(0);
});

test('a reversal needs a reason', function () {
    $officer = User::factory()->marketingOfficer()->create();
    $commission = Commission::factory()->create(['officer_id' => $officer->id]);

    Livewire::actingAs(User::factory()->admin()->create())->test(OfficerShow::class, ['user' => $officer])
        ->call('startReversing', $commission->id)
        ->call('reverse')
        ->assertHasErrors('reversalReason')
        ->set('reversalReason', 'Payment was a mistake')
        ->call('reverse')
        ->assertHasNoErrors();

    expect($commission->fresh()->status)->toBe(CommissionStatus::Reversed);
});

test('the officer page cannot reverse another officer\'s commission', function () {
    $officer = User::factory()->marketingOfficer()->create();
    $otherCommission = Commission::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(OfficerShow::class, ['user' => $officer])
        ->call('startReversing', $otherCommission->id)
        ->assertNotFound();
});

test('making an officer inactive stops their code but leaves their earnings payable', function () {
    $admin = User::factory()->admin()->create();
    $officer = User::factory()->marketingOfficer()->create();
    Commission::factory()->create(['officer_id' => $officer->id]);

    Livewire::actingAs($admin)->test(OfficerShow::class, ['user' => $officer])->call('toggleActive');

    expect($officer->marketingProfile->fresh()->isActive())->toBeFalse();

    filledSignupForm($officer->marketingProfile->referral_code)->call('register')->assertHasErrors('referralCode');

    Livewire::actingAs($admin)->test(OfficerShow::class, ['user' => $officer])
        ->set('paymentMethod', 'JazzCash')
        ->call('pay')
        ->assertHasNoErrors();

    expect(CommissionPayout::count())->toBe(1);
});

test('administrators can assign a client to an officer, and the change is recorded', function () {
    $admin = User::factory()->admin()->create();
    $officer = User::factory()->marketingOfficer()->create(['name' => 'Sara Ahmed']);
    $client = User::factory()->physician()->create();

    Livewire::actingAs($admin)->test(UserShow::class, ['user' => $client])
        ->call('assignReferrer', (string) $officer->id)
        ->assertHasNoErrors()
        ->assertSee('Sara Ahmed');

    expect($client->fresh()->referred_by)->toBe($officer->id);

    $change = ReferralChange::sole();
    expect($change->client_id)->toBe($client->id)
        ->and($change->from_officer_name)->toBeNull()
        ->and($change->to_officer_name)->toBe('Sara Ahmed')
        ->and($change->changed_by)->toBe($admin->id);
});

test('choosing the same officer again records nothing', function () {
    $officer = User::factory()->marketingOfficer()->create();
    $client = User::factory()->physician()->create(['referred_by' => $officer->id]);

    Livewire::actingAs(User::factory()->admin()->create())->test(UserShow::class, ['user' => $client])
        ->call('assignReferrer', (string) $officer->id);

    expect(ReferralChange::count())->toBe(0);
});

test('a client can only be assigned to a marketing officer', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(UserShow::class, ['user' => User::factory()->physician()->create()])
        ->call('assignReferrer', (string) User::factory()->physician()->create()->id)
        ->assertNotFound();
});

test('an officer with unpaid commission cannot be deleted or change role', function () {
    $admin = User::factory()->admin()->create();
    $officer = User::factory()->marketingOfficer()->create();
    Commission::factory()->create(['officer_id' => $officer->id]);

    Livewire::actingAs($admin)->test(UserIndex::class)
        ->call('deleteUser', $officer->id)
        ->assertHasErrors('users')
        ->call('updateRole', $officer->id, Role::Physician->value)
        ->assertHasErrors('users');

    expect($officer->fresh()->isMarketingOfficer())->toBeTrue();
});

test('deleting a settled officer keeps the accounts and frees their clients', function () {
    $admin = User::factory()->admin()->create();
    $officer = User::factory()->marketingOfficer()->create(['name' => 'Sara Ahmed']);
    $client = User::factory()->physician()->create(['referred_by' => $officer->id]);
    $commission = Commission::factory()->paid()->create(['officer_id' => $officer->id, 'officer_name' => 'Sara Ahmed']);

    Livewire::actingAs($admin)->test(UserIndex::class)->call('deleteUser', $officer->id)->assertHasNoErrors();

    expect(User::find($officer->id))->toBeNull()
        ->and($client->fresh()->referred_by)->toBeNull()
        ->and($commission->fresh()->officer_id)->toBeNull()
        ->and($commission->fresh()->officer_name)->toBe('Sara Ahmed');
});

test('deleting a client keeps what their officer earned', function () {
    $admin = User::factory()->admin()->create();
    $officer = User::factory()->marketingOfficer()->create();
    $client = User::factory()->physician()->create(['name' => 'Dr Sana Malik', 'referred_by' => $officer->id]);
    $subscription = Subscription::factory()->for($client)->create();
    $commission = Commission::factory()->create([
        'officer_id' => $officer->id,
        'client_id' => $client->id,
        'client_name' => 'Dr Sana Malik',
        'subscription_id' => $subscription->id,
    ]);

    Livewire::actingAs($admin)->test(UserIndex::class)->call('deleteUser', $client->id)->assertHasNoErrors();

    expect($commission->fresh()->client_id)->toBeNull()
        ->and($commission->fresh()->client_name)->toBe('Dr Sana Malik')
        ->and($commission->fresh()->status)->toBe(CommissionStatus::Pending);
});

test('someone made a marketing officer gets a code and stops being a client', function () {
    $admin = User::factory()->admin()->create();
    $firstOfficer = User::factory()->marketingOfficer()->create();
    $user = User::factory()->technician()->create(['referred_by' => $firstOfficer->id]);

    Livewire::actingAs($admin)->test(UserIndex::class)->call('updateRole', $user->id, Role::MarketingOfficer->value);

    expect($user->fresh()->marketingProfile->referral_code)->not->toBeEmpty()
        ->and($user->fresh()->referred_by)->toBeNull();
});

test('an administrator can create an officer directly, without a trial', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(UserIndex::class)
        ->set('name', 'New Officer')
        ->set('email', 'officer@example.test')
        ->set('password', 'a-long-enough-password')
        ->set('role', Role::MarketingOfficer->value)
        ->call('createUser')
        ->assertHasNoErrors();

    $officer = User::query()->where('email', 'officer@example.test')->sole();
    expect($officer->marketingProfile)->not->toBeNull()
        ->and($officer->subscriptions()->count())->toBe(0);
});

test('the officers list can be downloaded', function () {
    $admin = User::factory()->admin()->create();
    $officer = User::factory()->marketingOfficer()->create(['name' => 'Sara Ahmed']);
    Commission::factory()->create(['officer_id' => $officer->id, 'amount' => 1234]);

    Livewire::actingAs($admin)->test(MarketingIndex::class)
        ->call('export')
        ->assertFileDownloaded('marketing-officers-'.now()->format('Y-m-d').'.csv');
});

test('administrators see officers with their results', function () {
    $officer = User::factory()->marketingOfficer()->create(['name' => 'Sara Ahmed']);
    User::factory()->physician()->create(['referred_by' => $officer->id]);
    Commission::factory()->create(['officer_id' => $officer->id, 'amount' => 1234]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('marketing.index'))
        ->assertOk()
        ->assertSee('Sara Ahmed')
        ->assertSee($officer->marketingProfile->referral_code)
        ->assertSee('1,234');
});
