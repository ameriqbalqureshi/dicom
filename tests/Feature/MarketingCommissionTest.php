<?php

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Models\Commission;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\CommissionEarned;
use App\Notifications\CommissionPaidOut;
use App\Services\Billing\SubscriptionManager;
use App\Services\Marketing\CommissionManager;
use App\Services\Marketing\MarketingSettings;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->admin = User::factory()->admin()->create();
    $this->officer = User::factory()->marketingOfficer()->create();
});

/**
 * Confirm a payment by the client, as an administrator does on the payments page.
 */
function confirmClientPayment(User $client, int $amount = 5000): Subscription
{
    $subscription = Subscription::factory()->pending()->for($client)->create(['amount' => $amount]);

    return app(SubscriptionManager::class)->approve($subscription, User::factory()->admin()->create());
}

test('a client\'s first confirmed payment earns the officer the first-payment rate, on hold', function () {
    $this->freezeSecond();
    $client = User::factory()->physician()->create(['referred_by' => $this->officer->id]);

    $subscription = confirmClientPayment($client);

    $commission = Commission::sole();
    expect($commission->officer_id)->toBe($this->officer->id)
        ->and($commission->subscription_id)->toBe($subscription->id)
        ->and($commission->type)->toBe(CommissionType::FirstPayment)
        ->and($commission->base_amount)->toBe(5000)
        ->and($commission->rate_percent)->toBe(20.0)
        ->and($commission->amount)->toBe(1000)
        ->and($commission->available_at->equalTo(now()->addDays(14)))->toBeTrue()
        ->and($commission->isPayable())->toBeFalse();

    Notification::assertSentTo($this->officer, CommissionEarned::class);
});

test('commission is earned at the standard rate for each kind of client', function (string $state, int $firstAmount, ?int $renewalAmount) {
    $client = User::factory()->{$state}()->create(['referred_by' => $this->officer->id]);

    confirmClientPayment($client);
    confirmClientPayment($client);

    $amounts = Commission::query()->oldest('id')->pluck('amount')->all();

    expect($amounts)->toBe($renewalAmount === null ? [$firstAmount] : [$firstAmount, $renewalAmount]);
})->with([
    'physician' => ['physician', 1000, 500],
    'technician' => ['technician', 1000, 500],
    'patient, whose renewals earn nothing' => ['patient', 500, null],
]);

test('renewals stop earning once the renewal period after the first payment has passed', function () {
    $client = User::factory()->physician()->create(['referred_by' => $this->officer->id]);
    confirmClientPayment($client);

    $this->travel(11)->months();
    confirmClientPayment($client);
    expect(Commission::query()->where('type', CommissionType::Renewal)->count())->toBe(1);

    $this->travel(2)->months();
    confirmClientPayment($client);
    expect(Commission::query()->where('type', CommissionType::Renewal)->count())->toBe(1);
});

test('nothing is earned without a working officer behind a real payment', function (Closure $makeClient) {
    $client = $makeClient($this->officer);

    confirmClientPayment($client);

    expect(Commission::count())->toBe(0);
})->with([
    'client without an officer' => fn (User $officer) => User::factory()->physician()->create(),
    'officer has been deactivated' => function (User $officer) {
        $officer->marketingProfile->forceFill(['deactivated_at' => now()])->save();

        return User::factory()->physician()->create(['referred_by' => $officer->id]);
    },
]);

test('trials, assigned packages and rejected payments earn nothing', function () {
    $client = User::factory()->physician()->create(['referred_by' => $this->officer->id]);
    $subscriptions = app(SubscriptionManager::class);

    $subscriptions->startTrial($client);
    $subscriptions->assign($client, Package::factory()->create(), $this->admin);
    $subscriptions->reject(Subscription::factory()->pending()->for($client)->create(), $this->admin, 'Not received');

    expect(Commission::count())->toBe(0);
});

test('a payment an administrator records earns commission on the amount recorded', function () {
    $client = User::factory()->physician()->create(['referred_by' => $this->officer->id]);
    $package = Package::factory()->forRole($client->role)->create(['price' => 5000]);

    app(SubscriptionManager::class)->recordPayment($client, $package, 'JazzCash', 3000, $this->admin);

    expect(Commission::sole()->amount)->toBe(600);
});

test('an officer\'s custom rates replace the standard rates for professionals but not for patients', function () {
    $this->officer->marketingProfile->update(['first_payment_rate' => 30, 'renewal_rate' => 15]);

    confirmClientPayment(User::factory()->physician()->create(['referred_by' => $this->officer->id]));
    confirmClientPayment(User::factory()->patient()->create(['referred_by' => $this->officer->id]));

    expect(Commission::query()->oldest('id')->pluck('rate_percent')->all())->toBe([30.0, 10.0]);
});

test('changing the rules does not change commissions already earned', function () {
    $client = User::factory()->physician()->create(['referred_by' => $this->officer->id]);
    confirmClientPayment($client);

    $settings = app(MarketingSettings::class);
    $rules = $settings->all();
    $rules['rates']['physician'] = ['first' => 50, 'renewal' => 40];
    $settings->update($rules);

    confirmClientPayment($client);

    expect(Commission::query()->oldest('id')->pluck('amount')->all())->toBe([1000, 2000]);
});

test('commission records shorten patients\' names but keep other clients\' names whole', function () {
    confirmClientPayment(User::factory()->patient()->create(['name' => 'Ali Raza Khan', 'referred_by' => $this->officer->id]));
    confirmClientPayment(User::factory()->physician()->create(['name' => 'Dr Sana Malik', 'referred_by' => $this->officer->id]));

    expect(Commission::query()->oldest('id')->pluck('client_name')->all())->toBe(['Ali K.', 'Dr Sana Malik']);
});

test('a payout covers everything payable and leaves what is still on hold', function () {
    $payable = Commission::factory()->create(['officer_id' => $this->officer->id, 'amount' => 1000]);
    $bonus = app(CommissionManager::class)->addBonus($this->officer, 500, 'Great month', $this->admin);
    $onHold = Commission::factory()->onHold()->create(['officer_id' => $this->officer->id]);
    $someoneElses = Commission::factory()->create();

    $payout = app(CommissionManager::class)->pay($this->officer, 'JazzCash', 'TXN1', null, $this->admin);

    expect($payout->amount)->toBe(1500)
        ->and($payable->fresh()->status)->toBe(CommissionStatus::Paid)
        ->and($payable->fresh()->payout_id)->toBe($payout->id)
        ->and($bonus->fresh()->status)->toBe(CommissionStatus::Paid)
        ->and($onHold->fresh()->status)->toBe(CommissionStatus::Pending)
        ->and($someoneElses->fresh()->status)->toBe(CommissionStatus::Pending);

    Notification::assertSentTo($this->officer, CommissionPaidOut::class);
});

test('commission becomes payable when its hold ends', function () {
    $client = User::factory()->physician()->create(['referred_by' => $this->officer->id]);
    confirmClientPayment($client);
    $commissions = app(CommissionManager::class);

    expect($commissions->totalsFor($this->officer))->toBe(['onHold' => 1000, 'payable' => 0, 'paid' => 0]);

    $this->travel(15)->days();

    expect($commissions->totalsFor($this->officer))->toBe(['onHold' => 0, 'payable' => 1000, 'paid' => 0]);
});

test('an officer with nothing payable cannot be paid', function () {
    Commission::factory()->onHold()->create(['officer_id' => $this->officer->id]);

    app(CommissionManager::class)->pay($this->officer, 'JazzCash', null, null, $this->admin);
})->throws(InvalidArgumentException::class);

test('reversing an unpaid commission cancels it', function () {
    $commission = Commission::factory()->create(['officer_id' => $this->officer->id]);
    $commissions = app(CommissionManager::class);

    $commissions->reverse($commission, $this->admin, 'Payment was a mistake');

    expect($commission->fresh()->status)->toBe(CommissionStatus::Reversed)
        ->and($commission->fresh()->reversal_reason)->toBe('Payment was a mistake')
        ->and($commissions->totalsFor($this->officer)['payable'])->toBe(0)
        ->and(Commission::count())->toBe(1);
});

test('reversing a paid commission takes the amount off the next payout', function () {
    $commission = Commission::factory()->create(['officer_id' => $this->officer->id, 'amount' => 1000]);
    $commissions = app(CommissionManager::class);
    $commissions->pay($this->officer, 'JazzCash', null, null, $this->admin);

    $commissions->reverse($commission, $this->admin, 'Client refunded');

    expect($commissions->totalsFor($this->officer)['payable'])->toBe(-1000);

    Commission::factory()->create(['officer_id' => $this->officer->id, 'amount' => 1500]);
    $nextPayout = $commissions->pay($this->officer, 'JazzCash', null, null, $this->admin);

    expect($nextPayout->amount)->toBe(500)
        ->and($commission->fresh()->stageLabel())->toBe('Paid, later reversed');
});

test('an officer who owes more than they earned cannot be paid', function () {
    $commission = Commission::factory()->create(['officer_id' => $this->officer->id, 'amount' => 1000]);
    $commissions = app(CommissionManager::class);
    $commissions->pay($this->officer, 'JazzCash', null, null, $this->admin);
    $commissions->reverse($commission, $this->admin, 'Client refunded');
    Commission::factory()->create(['officer_id' => $this->officer->id, 'amount' => 400]);

    $commissions->pay($this->officer, 'JazzCash', null, null, $this->admin);
})->throws(InvalidArgumentException::class);

test('a commission cannot be reversed twice, and adjustments cannot be reversed', function () {
    $commission = Commission::factory()->create(['officer_id' => $this->officer->id]);
    $commissions = app(CommissionManager::class);
    $commissions->pay($this->officer, 'JazzCash', null, null, $this->admin);
    $commissions->reverse($commission, $this->admin, 'Client refunded');

    expect(fn () => $commissions->reverse($commission, $this->admin, 'Again'))->toThrow(InvalidArgumentException::class);

    $adjustment = Commission::query()->where('type', CommissionType::Adjustment)->sole();

    expect(fn () => $commissions->reverse($adjustment, $this->admin, 'Undo'))->toThrow(InvalidArgumentException::class);
});

test('a client moved to another officer earns the new officer renewals within the same period', function () {
    $client = User::factory()->physician()->create(['referred_by' => $this->officer->id]);
    confirmClientPayment($client);
    $newOfficer = User::factory()->marketingOfficer()->create();
    $client->forceFill(['referred_by' => $newOfficer->id])->save();

    confirmClientPayment($client->fresh());

    $renewal = Commission::query()->where('type', CommissionType::Renewal)->sole();
    expect($renewal->officer_id)->toBe($newOfficer->id)
        ->and($renewal->amount)->toBe(500)
        ->and($this->officer->commissions()->count())->toBe(1);
});
