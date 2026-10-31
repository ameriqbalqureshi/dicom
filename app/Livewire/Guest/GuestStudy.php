<?php

namespace App\Livewire\Guest;

use App\Models\DicomInstance;
use App\Models\GuestLink;
use App\Services\Dicom\ViewerSeries;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Shows a study to someone without an account who has a guest link, after they enter its PIN if it has one.
 */
#[Layout('layouts::guest-viewer')]
#[Title('Shared study')]
class GuestStudy extends Component
{
    public const MAX_PIN_ATTEMPTS = 5;

    public const PIN_LOCKOUT_SECONDS = 900;

    #[Locked]
    public string $token;

    public string $pin = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Check the PIN. Attempts are limited per link, so the PIN cannot be guessed before the link expires.
     */
    public function unlock(): void
    {
        $link = $this->usableLink();

        if ($link === null || $link->isUnlocked()) {
            return;
        }

        $this->validate(['pin' => ['required', 'digits:6']], ['pin.digits' => 'The PIN has 6 digits.']);

        $throttleKey = "guest-link-pin:{$link->id}";

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PIN_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'pin' => 'Too many incorrect attempts. Try again in '.ceil(RateLimiter::availableIn($throttleKey) / 60).' minutes.',
            ]);
        }

        if (! Hash::check($this->pin, $link->pin_hash)) {
            RateLimiter::hit($throttleKey, self::PIN_LOCKOUT_SECONDS);

            throw ValidationException::withMessages(['pin' => 'That PIN is not correct.']);
        }

        RateLimiter::clear($throttleKey);
        $link->unlock();
        $this->reset('pin');
    }

    public function render(): View
    {
        $link = $this->usableLink();

        if ($link?->isUnlocked()) {
            $link->recordView();
        }

        return view('livewire.guest.guest-study', [
            'link' => $link,
            'study' => $link?->study,
            'reports' => $link?->isUnlocked()
                ? $link->study->reports()->final()->with(['author', 'addenda'])->oldest('finalized_at')->get()
                : collect(),
            'viewerSeries' => $link?->isUnlocked()
                ? ViewerSeries::forStudy($link->study, fn (DicomInstance $instance): string => route('guest.instances.file', [$this->token, $instance]))
                : [],
        ]);
    }

    private function usableLink(): ?GuestLink
    {
        $link = GuestLink::findByToken($this->token);

        return $link?->isUsable() ? $link : null;
    }
}
