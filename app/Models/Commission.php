<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\Role;
use Database\Factories\CommissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of what a marketing officer earned. It keeps its own copy of the names, amount and rate, so it stays correct
 * after rates change or accounts are deleted. Patients' names are shortened in the copy, because officers see it.
 */
class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'officer_id',
        'officer_name',
        'client_id',
        'client_name',
        'client_role',
        'subscription_id',
        'reverses_id',
        'type',
        'status',
        'base_amount',
        'rate_percent',
        'amount',
        'note',
        'created_by',
        'available_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CommissionType::class,
            'status' => CommissionStatus::class,
            'client_role' => Role::class,
            'base_amount' => 'integer',
            'rate_percent' => 'float',
            'amount' => 'integer',
            'available_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function isPayable(): bool
    {
        return $this->status === CommissionStatus::Pending && ! $this->available_at->isFuture();
    }

    /**
     * Whether an administrator can still cancel this line. Adjustments are themselves reversals, so they cannot be.
     */
    public function isReversible(): bool
    {
        return $this->reversed_at === null && $this->type !== CommissionType::Adjustment;
    }

    /**
     * Where this line has got to, as shown to officers and administrators.
     */
    public function stageLabel(): string
    {
        if ($this->status === CommissionStatus::Reversed) {
            return 'Reversed';
        }

        if ($this->status === CommissionStatus::Paid) {
            return $this->reversed_at === null ? 'Paid' : 'Paid, later reversed';
        }

        return $this->isPayable() ? 'Payable' : 'On hold until '.$this->available_at->toFormattedDateString();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<CommissionPayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(CommissionPayout::class, 'payout_id');
    }

    /**
     * Unpaid lines whose hold has ended.
     *
     * @param  Builder<Commission>  $query
     */
    public function scopePayable(Builder $query): void
    {
        $query->where('status', CommissionStatus::Pending)->where('available_at', '<=', now());
    }

    /**
     * Unpaid lines still within their hold.
     *
     * @param  Builder<Commission>  $query
     */
    public function scopeOnHold(Builder $query): void
    {
        $query->where('status', CommissionStatus::Pending)->where('available_at', '>', now());
    }
}
