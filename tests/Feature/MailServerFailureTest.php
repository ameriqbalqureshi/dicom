<?php

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Livewire\Auth\Register;
use App\Livewire\Billing\PaymentIndex;
use App\Livewire\Profile\EditProfile;
use App\Livewire\Site\ContactForm;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StudySharing;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends mail through a transport that fails, either always or only for one address.
 */
function useFailingMailServer(?string $failingAddress = null): void
{
    Exceptions::fake();

    Mail::extend('failing', fn () => new class($failingAddress) extends AbstractTransport
    {
        public function __construct(private ?string $failingAddress)
        {
            parent::__construct();
        }

        protected function doSend(SentMessage $message): void
        {
            $addresses = array_map(fn ($recipient) => $recipient->getAddress(), $message->getEnvelope()->getRecipients());

            if ($this->failingAddress === null || in_array($this->failingAddress, $addresses, true)) {
                throw new TransportException('The mail server is down.');
            }
        }

        public function __toString(): string
        {
            return 'failing';
        }
    });

    config(['mail.default' => 'failing', 'mail.mailers.failing' => ['transport' => 'failing']]);
}

function fillInContactForm(): Testable
{
    return Livewire::test(ContactForm::class)
        ->set('name', 'Dr. Sana Malik')
        ->set('email', 'sana@hospital.test')
        ->set('topic', 'general')
        ->set('message', 'We would like to set up accounts for our team.');
}

test('visitors are told when their contact message could not be sent, and keep what they wrote', function () {
    User::factory()->admin()->create();
    useFailingMailServer();

    fillInContactForm()
        ->call('send')
        ->assertHasErrors('message')
        ->assertSet('sent', false)
        ->assertSet('message', 'We would like to set up accounts for our team.');

    Exceptions::assertReported(TransportException::class);
});

test('visitors are told when there is no administrator to receive their contact message', function () {
    fillInContactForm()
        ->call('send')
        ->assertHasErrors('message')
        ->assertSet('sent', false);
});

test('a contact message counts as sent when one of several administrators can receive it', function () {
    User::factory()->admin()->create(['email' => 'rejected@clinic.test']);
    User::factory()->admin()->create(['email' => 'working@clinic.test']);
    useFailingMailServer('rejected@clinic.test');

    fillInContactForm()
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('sent', true);
});

test('a payment is still confirmed when the mail server is down', function () {
    $admin = User::factory()->admin()->create();
    $subscription = Subscription::factory()->pending()->create();
    useFailingMailServer();

    Livewire::actingAs($admin)->test(PaymentIndex::class)
        ->call('approve', $subscription->id)
        ->assertSuccessful();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
    Exceptions::assertReported(TransportException::class);
});

test('a study is still shared when the mail server is down', function () {
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();
    $physician = User::factory()->physician()->create();
    allowSender($physician, $study->uploader);
    useFailingMailServer();

    app(StudySharing::class)->share($study, $study->uploader, $physician);

    expect(StudyShare::sole()->user_id)->toBe($physician->id);
});

test('people can still register and are logged in when the mail server is down', function () {
    User::factory()->admin()->create();
    useFailingMailServer();

    Livewire::test(Register::class)
        ->set('role', Role::Physician->value)
        ->set('name', 'Dr. Jane Heart')
        ->set('email', 'jane@clinic.test')
        ->set('registrationNumber', 'PMDC-1')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('register')
        ->assertRedirect(route('verification.notice'));

    expect(auth()->user()?->email)->toBe('jane@clinic.test');
});

test('a changed email address is saved and leads to the verification page when the mail server is down', function () {
    $user = User::factory()->admin()->create(['password' => 'secret-password']);
    useFailingMailServer();

    Livewire::actingAs($user)->test(EditProfile::class)
        ->set('email', 'new@clinic.test')
        ->set('emailPassword', 'secret-password')
        ->call('saveAccount')
        ->assertRedirect(route('verification.notice'));

    expect($user->fresh()->email)->toBe('new@clinic.test');
});

test('a reminder that could not be sent is tried again without holding up the others', function () {
    $rejected = Subscription::factory()->create(['ends_at' => now()->addDays(2)]);
    $delivered = Subscription::factory()->create(['ends_at' => now()->addDays(2)]);
    useFailingMailServer($rejected->user->email);

    $this->artisan('packages:send-expiry-reminders')->assertSuccessful();

    expect($rejected->fresh()->expiry_reminder_sent_at)->toBeNull()
        ->and($delivered->fresh()->expiry_reminder_sent_at)->not->toBeNull();
});
