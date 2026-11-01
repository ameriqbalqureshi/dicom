<?php

namespace App\Livewire\Site;

use App\Models\User;
use App\Notifications\ContactMessageReceived;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * The public contact form. Messages are emailed to the administrators, with the sender as the reply-to address.
 */
class ContactForm extends Component
{
    /**
     * Messages one visitor may send before having to wait.
     */
    public const MAX_ATTEMPTS = 3;

    public string $name = '';

    public string $email = '';

    public string $organisation = '';

    public string $topic = 'general';

    public string $message = '';

    /**
     * A field hidden from people; bots that fill it in are ignored.
     */
    public string $website = '';

    public bool $sent = false;

    /**
     * @return array<string, string>
     */
    public static function topics(): array
    {
        return [
            'general' => 'General question',
            'sales' => 'Packages and pricing',
            'onboarding' => 'Setting up a hospital or department',
            'support' => 'Help with my account',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'topic' => ['required', 'in:'.implode(',', array_keys(self::topics()))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function send(): void
    {
        $validated = $this->validate();

        $throttleKey = 'contact-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'message' => 'You have sent several messages already. Please try again in '.ceil(RateLimiter::availableIn($throttleKey) / 60).' minutes.',
            ]);
        }

        RateLimiter::hit($throttleKey, 3600);

        if ($this->website === '') {
            Notification::send(User::admins()->get(), new ContactMessageReceived(
                name: trim($validated['name']),
                email: $validated['email'],
                organisation: filled($validated['organisation']) ? trim($validated['organisation']) : null,
                topic: self::topics()[$validated['topic']],
                message: trim($validated['message']),
            ));
        }

        $this->reset('name', 'email', 'organisation', 'topic', 'message');
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.site.contact-form', [
            'topics' => self::topics(),
        ]);
    }
}
