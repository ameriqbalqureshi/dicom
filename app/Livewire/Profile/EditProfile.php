<?php

namespace App\Livewire\Profile;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Lets every user change their name, email address and password, and physicians set the credentials and signature
 * image printed on the reports they sign. A new email address must be confirmed before the app can be used again.
 */
#[Title('Profile')]
class EditProfile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    /**
     * The current password, needed to change the email address.
     */
    public string $emailPassword = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $qualifications = '';

    public string $registrationNumber = '';

    /**
     * @var TemporaryUploadedFile|null
     */
    public $signature = null;

    public ?string $status = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->qualifications = $user->qualifications ?? '';
        $this->registrationNumber = $user->registration_number ?? '';
    }

    /**
     * Save the name and email address. Changing the email address needs the current password, and the new address
     * must be confirmed through the link sent to it.
     */
    public function saveAccount(): void
    {
        $user = auth()->user();
        $this->email = trim($this->email);
        $emailChanged = $this->email !== $user->email;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'emailPassword' => $emailChanged ? ['required', 'string', 'current_password'] : ['nullable'],
        ], [
            'emailPassword.required' => 'Enter your current password to change your email address.',
            'emailPassword.current_password' => 'That password is not correct.',
        ]);

        $user->name = trim($validated['name']);

        if ($emailChanged) {
            $user->email = $validated['email'];
            $user->email_verified_at = null;
        }

        $user->save();
        $this->reset('emailPassword');

        if ($emailChanged) {
            rescue(fn () => $user->sendEmailVerificationNotification());

            $this->redirectRoute('verification.notice');

            return;
        }

        $this->status = 'Account details saved.';
    }

    public function updatePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'currentPassword.current_password' => 'That password is not correct.',
        ]);

        auth()->user()->update(['password' => $this->password]);

        $this->reset('currentPassword', 'password', 'password_confirmation');
        $this->status = 'Password changed.';
    }

    public function save(): void
    {
        $user = $this->physician();

        $validated = $this->validate([
            'qualifications' => ['nullable', 'string', 'max:255'],
            'registrationNumber' => ['nullable', 'string', 'max:100'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ]);

        $user->fill([
            'qualifications' => filled($validated['qualifications']) ? trim($validated['qualifications']) : null,
            'registration_number' => filled($validated['registrationNumber']) ? trim($validated['registrationNumber']) : null,
        ]);

        if ($this->signature) {
            $previousPath = $user->signature_path;
            $user->signature_path = $this->signature->store("signatures/{$user->id}", User::SIGNATURE_DISK);

            if ($previousPath !== null) {
                Storage::disk(User::SIGNATURE_DISK)->delete($previousPath);
            }
        }

        $user->save();

        $this->reset('signature');
        $this->status = 'Report details saved.';
    }

    public function removeSignature(): void
    {
        $user = $this->physician();

        if ($user->signature_path !== null) {
            Storage::disk(User::SIGNATURE_DISK)->delete($user->signature_path);
            $user->update(['signature_path' => null]);
        }

        $this->status = 'Signature removed.';
    }

    private function physician(): User
    {
        $user = auth()->user();

        abort_unless($user->isPhysician(), 403);

        return $user;
    }

    public function render(): View
    {
        return view('livewire.profile.edit-profile', [
            'isPhysician' => auth()->user()->isPhysician(),
            'signaturePreview' => auth()->user()->signatureDataUri(),
        ]);
    }
}
