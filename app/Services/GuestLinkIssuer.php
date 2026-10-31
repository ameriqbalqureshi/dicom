<?php

namespace App\Services;

use App\Models\GuestLink;
use App\Models\Study;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates guest links. The token and PIN are returned only here, because just their hashes are stored.
 */
class GuestLinkIssuer
{
    public const TOKEN_LENGTH = 48;

    public const PIN_LENGTH = 6;

    /**
     * @return array{link: GuestLink, url: string, pin: string|null}
     */
    public function issue(Study $study, User $creator, bool $withPin): array
    {
        $token = Str::random(self::TOKEN_LENGTH);
        $pin = $withPin ? str_pad((string) random_int(0, 10 ** self::PIN_LENGTH - 1), self::PIN_LENGTH, '0', STR_PAD_LEFT) : null;

        $link = $study->guestLinks()->create([
            'created_by' => $creator->id,
            'token_hash' => GuestLink::hashToken($token),
            'pin_hash' => $pin === null ? null : Hash::make($pin),
            'expires_at' => now()->addHours(GuestLink::LIFETIME_HOURS),
        ]);

        return ['link' => $link, 'url' => route('guest.show', $token), 'pin' => $pin];
    }

    public function revoke(GuestLink $link): void
    {
        $link->update(['revoked_at' => now()]);
    }
}
