<?php

namespace App\Models;

use Database\Factories\PaymentSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A receipt sent through the public form, which anyone can fill in. What it says is unverified, so it stays apart from
 * the user's purchases until an administrator links it to an account. It then becomes a purchase awaiting confirmation.
 */
class PaymentSubmission extends Model
{
    /** @use HasFactory<PaymentSubmissionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'account_identifier',
        'phone',
        'package_id',
        'payment_method',
        'payment_reference',
        'receipt_path',
        'notes',
    ];

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
