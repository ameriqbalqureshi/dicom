<?php

namespace App\Livewire\Studies;

use App\Models\GuestLink;
use App\Models\Study;
use App\Services\GuestLinkIssuer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Lets physicians (for any study they can open) and patients (for their own) create 24-hour guest links, optionally
 * protected by a PIN, for people without an account. The owner sees and can revoke every link to the study; others
 * only the links they created.
 */
class GuestLinks extends Component
{
    public Study $study;

    public bool $requirePin = true;

    /**
     * The link just created, shown once because only its hash is stored.
     *
     * @var array{url: string, pin: string|null, expiresAt: string}|null
     */
    public ?array $issued = null;

    public ?string $status = null;

    public function mount(Study $study): void
    {
        $this->authorize('manageGuestLinks', $study);

        $this->study = $study;
    }

    public function create(GuestLinkIssuer $issuer): void
    {
        $this->authorize('createGuestLink', $this->study);

        ['link' => $link, 'url' => $url, 'pin' => $pin] = $issuer->issue($this->study, auth()->user(), $this->requirePin);

        $this->issued = ['url' => $url, 'pin' => $pin, 'expiresAt' => $link->expires_at->toDayDateTimeString()];
        $this->status = null;
    }

    public function revoke(int $linkId, GuestLinkIssuer $issuer): void
    {
        $link = $this->study->guestLinks()->findOrFail($linkId);
        $this->authorize('delete', $link);

        $issuer->revoke($link);

        $this->status = 'Guest link revoked. It no longer opens this study.';
    }

    public function dismissIssued(): void
    {
        $this->issued = null;
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.studies.guest-links', [
            'canCreate' => $user->can('createGuestLink', $this->study),
            'links' => $this->study->guestLinks()
                ->active()
                ->unless($this->study->uploader()->is($user), fn ($query) => $query->whereBelongsTo($user, 'creator'))
                ->with('creator')
                ->latest()
                ->get()
                ->each(fn (GuestLink $link) => $link->setRelation('study', $this->study))
                ->filter(fn (GuestLink $link): bool => $link->isUsable())
                ->values(),
            'lifetimeHours' => GuestLink::LIFETIME_HOURS,
        ]);
    }
}
