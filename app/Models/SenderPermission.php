<?php

namespace App\Models;

use App\Enums\SenderStatus;
use Database\Factories\SenderPermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A physician's decision about one person who shares studies with them: allowed, suspended or blocked. Without one, the
 * sender is new and their shares wait for the physician to accept them.
 */
class SenderPermission extends Model
{
    /** @use HasFactory<SenderPermissionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recipient_id',
        'sender_id',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SenderStatus::class,
        ];
    }

    /**
     * The status a physician has given a sender, or null when the sender is new to them.
     */
    public static function statusOf(User $recipient, User $sender): ?SenderStatus
    {
        return static::query()
            ->whereBelongsTo($recipient, 'recipient')
            ->whereBelongsTo($sender, 'sender')
            ->first()
            ?->status;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
