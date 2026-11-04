<?php

namespace App\Models;

use App\Enums\Quota;
use App\Enums\Role;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something users of one role can buy: a duration and limits on what they can do. A null limit means unlimited and a
 * null duration means it never ends. Each role's trial package is given to new users when they sign up.
 */
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'role',
        'price',
        'duration_days',
        'max_uploads',
        'max_patient_records',
        'max_storage_mb',
        'max_reports',
        'max_shares',
        'max_guest_links',
        'is_trial',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'price' => 'integer',
            'duration_days' => 'integer',
            'max_uploads' => 'integer',
            'max_patient_records' => 'integer',
            'max_storage_mb' => 'integer',
            'max_reports' => 'integer',
            'max_shares' => 'integer',
            'max_guest_links' => 'integer',
            'is_trial' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function trialFor(Role $role): ?self
    {
        return static::query()->active()->where('role', $role)->where('is_trial', true)->oldest('id')->first();
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    /**
     * The package's limits, as copied onto a subscription when it is activated.
     *
     * @return array<string, int|null>
     */
    public function limits(): array
    {
        return $this->only([
            ...array_map(fn (Quota $quota): string => $quota->limitColumn(), Quota::cases()),
            'max_storage_mb',
        ]);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @param  Builder<Package>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Packages a user can buy: active, non-trial packages for their role.
     *
     * @param  Builder<Package>  $query
     */
    public function scopePurchasableBy(Builder $query, User $user): void
    {
        $query->active()->where('is_trial', false)->where('role', $user->role)->orderBy('price');
    }
}
