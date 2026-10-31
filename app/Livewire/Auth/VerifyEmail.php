<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Asks users who have not confirmed their email address yet to use the link sent to them, and lets them send it again.
 */
#[Layout('layouts::guest')]
#[Title('Verify your email')]
class VerifyEmail extends Component
{
    private const MAX_RESENDS_PER_HOUR = 3;

    public ?string $status = null;

    public function mount(): void
    {
        if (auth()->user()->hasVerifiedEmail()) {
            $this->redirectRoute('studies.index');
        }
    }

    public function resend(): void
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectRoute('studies.index');

            return;
        }

        $throttleKey = "verify-email:{$user->id}";

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_RESENDS_PER_HOUR)) {
            $this->addError('resend', 'Too many emails sent. Try again in '.ceil(RateLimiter::availableIn($throttleKey) / 60).' minutes.');

            return;
        }

        RateLimiter::hit($throttleKey, 3600);
        $user->sendEmailVerificationNotification();

        $this->status = 'We have sent a new link to '.$user->email.'.';
    }

    public function render(): View
    {
        return view('livewire.auth.verify-email');
    }
}
