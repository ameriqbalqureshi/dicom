<?php

namespace App\Models;

use App\Enums\Quota;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's purchase of a package. While pending it records the payment for an administrator to confirm; once active it
 * holds a copy of the package's limits (so later changes to the package do not affect it) and what has been used.
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'package_id',
        'status',
        'amount',
        'payment_method',
        'payment_reference',
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
            'status' => SubscriptionStatus::class,
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uploads' => 'integer',
            'max_storage_mb' => 'integer',
            'max_reports' => 'integer',
            'max_shares' => 'integer',
            'max_guest_links' => 'integer',
            'uploads_used' => 'integer',
            'reports_used' => 'integer',
            'shares_used' => 'integer',
            'guest_links_used' => 'integer',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === SubscriptionStatus::Pending;
    }

    /**
     * Whether the subscription is active and has not run out.
     */
    public function isCurrent(): bool
    {
        return $this->status === SubscriptionStatus::Active
            && $this->starts_at?->isPast() !== false
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * The status to show users, telling current packages apart from ones that have run out.
     */
    public function statusLabel(): string
    {
        if ($this->status === SubscriptionStatus::Active) {
            return $this->isCurrent() ? 'Current' : 'Ended';
        }

        return $this->status->label();
    }

    /**
     * The limit for a quota, or null if it is unlimited.
     */
    public function limit(Quota $quota): ?int
    {
        return $this->{$quota->limitColumn()};
    }

    public function used(Quota $quota): int
    {
        return $this->{$quota->usageColumn()};
    }

    /**
     * How many more times the quota can be used, or null if it is unlimited.
     */
    public function remaining(Quota $quota): ?int
    {
        $limit = $this->limit($quota);

        return $limit === null ? null : max(0, $limit - $this->used($quota));
    }

    public function storageLimitBytes(): ?int
    {
        return $this->max_storage_mb === null ? null : $this->max_storage_mb * 1024 * 1024;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Active subscriptions that have started and not yet ended.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Active)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Pending);
    }
}
