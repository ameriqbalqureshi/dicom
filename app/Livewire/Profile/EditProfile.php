<?php

namespace App\Livewire\Profile;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Lets physicians set the credentials and signature image printed on the reports they sign.
 */
#[Title('Profile')]
class EditProfile extends Component
{
    use WithFileUploads;

    public string $qualifications = '';

    public string $registrationNumber = '';

    /**
     * @var TemporaryUploadedFile|null
     */
    public $signature = null;

    public ?string $status = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isPhysician(), 403);

        $this->qualifications = auth()->user()->qualifications ?? '';
        $this->registrationNumber = auth()->user()->registration_number ?? '';
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
        $this->status = 'Profile saved.';
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
            'signaturePreview' => auth()->user()->signatureDataUri(),
        ]);
    }
}
