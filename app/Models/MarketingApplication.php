<?php

namespace App\Models;

use App\Enums\MarketingApplicationStatus;
use Database\Factories\MarketingApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A request from the public website to become a marketing officer. No account exists until an administrator approves it.
 */
class MarketingApplication extends Model
{
    /** @use HasFactory<MarketingApplicationFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'city',
        'experience',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MarketingApplicationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === MarketingApplicationStatus::Pending;
    }

    /**
     * @param  Builder<MarketingApplication>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', MarketingApplicationStatus::Pending);
    }
}
