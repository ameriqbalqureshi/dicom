<?php

use App\Livewire\Site\ContactForm;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
});

function fillContactForm(): Testable
{
    return Livewire::test(ContactForm::class)
        ->set('name', ' Dr. Sana Malik ')
        ->set('email', 'sana@hospital.test')
        ->set('organisation', 'City Heart Centre')
        ->set('topic', 'onboarding')
        ->set('message', 'We would like to set up accounts for our cath lab team.');
}

test('contact messages are emailed to every administrator with the sender as reply-to', function () {
    $admins = User::factory()->admin()->count(2)->create();
    $physician = User::factory()->physician()->create();

    fillContactForm()
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSet('message', '');

    Notification::assertSentTo($admins, ContactMessageReceived::class, function (ContactMessageReceived $notification) use ($admins) {
        $mail = $notification->toMail($admins->first());

        return $notification->name === 'Dr. Sana Malik'
            && $notification->topic === 'Setting up a hospital or department'
            && $mail->replyTo === [['sana@hospital.test', 'Dr. Sana Malik']];
    });
    Notification::assertNotSentTo($physician, ContactMessageReceived::class);
});

test('contact messages need a name, a valid email, a known topic and a real message', function () {
    Livewire::test(ContactForm::class)
        ->set('email', 'not-an-email')
        ->set('topic', 'other')
        ->set('message', 'Hi')
        ->call('send')
        ->assertHasErrors(['name' => 'required', 'email' => 'email', 'topic' => 'in', 'message' => 'min']);

    Notification::assertNothingSent();
});

test('messages from bots that fill in the hidden field are silently dropped', function () {
    User::factory()->admin()->create();

    fillContactForm()
        ->set('website', 'https://spam.test')
        ->call('send')
        ->assertSet('sent', true);

    Notification::assertNothingSent();
});

test('visitors cannot send more than a few messages an hour', function () {
    User::factory()->admin()->create();

    foreach (range(1, ContactForm::MAX_ATTEMPTS) as $attempt) {
        fillContactForm()->call('send')->assertHasNoErrors();
    }

    fillContactForm()
        ->call('send')
        ->assertHasErrors('message')
        ->assertSet('sent', false);

    Notification::assertCount(ContactForm::MAX_ATTEMPTS);
});
