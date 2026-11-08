<?php

namespace App\Models;

use Database\Factories\MarketingProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What makes a user a working marketing officer: their referral code, how to pay them, and any rates agreed with them
 * alone. A deactivated officer's code stops working and they earn nothing more, but what they already earned stays theirs.
 */
class MarketingProfile extends Model
{
    /** @use HasFactory<MarketingProfileFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'referral_code',
        'phone',
        'payout_details',
        'first_payment_rate',
        'renewal_rate',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_payment_rate' => 'float',
            'renewal_rate' => 'float',
            'deactivated_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * The link clients sign up through.
     */
    public function referralLink(): string
    {
        return route('register', ['ref' => $this->referral_code]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
