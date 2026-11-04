<?php

namespace App\Models;

use App\Enums\PaymentAccountType;
use Database\Factories\PaymentAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An account administrators receive payments into, shown to buyers at checkout and on the public pages. Purchases record
 * the account's name when they are made, so accounts can be changed or removed without affecting past payments.
 */
class PaymentAccount extends Model
{
    /** @use HasFactory<PaymentAccountFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'account_title',
        'account_number',
        'iban',
        'branch',
        'instructions',
        'is_active',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentAccountType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The details buyers need to pay into the account, leaving out any that are empty.
     *
     * @return array<string, string>
     */
    public function details(): array
    {
        return array_filter([
            'Account title' => $this->account_title,
            $this->type->numberLabel() => $this->account_number,
            'IBAN' => $this->iban,
            'Branch' => $this->branch,
        ], 'filled');
    }

    /**
     * @param  Builder<PaymentAccount>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<PaymentAccount>  $query
     */
    public function scopeInDisplayOrder(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
