<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Sends a password reset link. The same message is shown whether or not the address has an account, so the form cannot
 * be used to find out who is registered.
 */
#[Layout('layouts::guest')]
#[Title('Forgot your password')]
class ForgotPassword extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    public ?string $status = null;

    public function sendLink(): void
    {
        $this->validate();

        $result = Password::sendResetLink(['email' => $this->email]);

        if ($result === Password::RESET_THROTTLED) {
            $this->addError('email', __($result));

            return;
        }

        $this->status = 'If an account uses that address, we have emailed it a link to reset the password.';
        $this->reset('email');
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
