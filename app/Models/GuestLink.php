<?php

namespace App\Models;

use Database\Factories\GuestLinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A temporary link that lets someone without an account view one study, optionally protected by a PIN. Only hashes of
 * the token and PIN are stored, so they can be shown just once, when the link is created.
 */
class GuestLink extends Model
{
    /** @use HasFactory<GuestLinkFactory> */
    use HasFactory, Prunable;

    public const LIFETIME_HOURS = 24;

    /**
     * How long expired and revoked links are kept, so their owners can still see who viewed them.
     */
    public const RETENTION_DAYS = 30;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'study_id',
        'created_by',
        'token_hash',
        'pin_hash',
        'expires_at',
        'revoked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
        'pin_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', self::hashToken($token))->first();
    }

    /**
     * @return BelongsTo<Study, $this>
     */
    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requiresPin(): bool
    {
        return $this->pin_hash !== null;
    }

    /**
     * Whether the link still opens the study: it has not expired or been revoked, and its creator may still share the
     * study this way (they may since have lost access to it).
     */
    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $this->creator->can('createGuestLink', $this->study);
    }

    /**
     * Whether the current browser session may view the study: the link has no PIN, or the right PIN was entered.
     */
    public function isUnlocked(): bool
    {
        return ! $this->requiresPin() || session()->get($this->sessionKey('unlocked'), false);
    }

    public function unlock(): void
    {
        session()->put($this->sessionKey('unlocked'), true);
    }

    /**
     * Count a view, once per browser session.
     */
    public function recordView(): void
    {
        if (session()->get($this->sessionKey('viewed'), false)) {
            return;
        }

        session()->put($this->sessionKey('viewed'), true);
        $this->increment('view_count', 1, ['last_viewed_at' => now()]);
    }

    private function sessionKey(string $name): string
    {
        return "guest_links.{$this->id}.{$name}";
    }

    /**
     * @param  Builder<GuestLink>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * @return Builder<GuestLink>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
