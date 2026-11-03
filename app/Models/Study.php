<?php

namespace App\Models;

use Database\Factories\StudyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Study extends Model
{
    /** @use HasFactory<StudyFactory> */
    use HasFactory;

    /**
     * How long a patient's study stays available before it is deleted.
     */
    public const PATIENT_STUDY_LIFETIME_DAYS = 7;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'study_instance_uid',
        'patient_name',
        'patient_id',
        'patient_birth_date',
        'patient_sex',
        'study_date',
        'study_description',
        'accession_number',
        'uploaded_by',
        'released_by',
        'expires_at',
        'handover_ends_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_birth_date' => 'date',
            'study_date' => 'date',
            'expires_at' => 'datetime',
            'handover_ends_at' => 'datetime',
        ];
    }

    /**
     * Whether the owner has given the study up (deleted it, or it expired) and the physicians who can open it are
     * deciding whether to keep it. Such a study has no owner until one of them does.
     */
    public function isAwaitingHandover(): bool
    {
        return $this->handover_ends_at !== null;
    }

    /**
     * Whether the study is past its expiry date and waiting to be deleted. Only patients' studies expire.
     */
    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    /**
     * @return HasMany<Series, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }

    /**
     * @return HasManyThrough<DicomInstance, Series, $this>
     */
    public function instances(): HasManyThrough
    {
        return $this->hasManyThrough(DicomInstance::class, Series::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The owner who gave the study up, while it is awaiting handover.
     *
     * @return BelongsTo<User, $this>
     */
    public function formerOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * The physicians this study has been shared with, by its owner or by forwarding, including pending shares.
     *
     * @return HasMany<StudyShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(StudyShare::class);
    }

    /**
     * @return HasMany<GuestLink, $this>
     */
    public function guestLinks(): HasMany
    {
        return $this->hasMany(GuestLink::class);
    }

    /**
     * Physicians' reports on this study, including drafts only their authors may see.
     *
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Notes about the study from anyone who can open it, oldest first.
     *
     * @return HasMany<StudyComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(StudyComment::class)->oldest()->oldest('id');
    }

    /**
     * Limit studies to those the user may view: their own uploads and studies shared with them,
     * or every study for administrators. Expired studies are hidden from everyone.
     *
     * @param  Builder<Study>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->unexpired();

        if ($user->isAdmin()) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereBelongsTo($user, 'uploader')
            ->orWhere(fn (Builder $query) => $query->sharedWith($user)));
    }

    /**
     * Limit studies to those shared with the user that they can open, optionally only those a given person shared.
     *
     * @param  Builder<Study>  $query
     */
    public function scopeSharedWith(Builder $query, User $user, ?User $sharer = null): void
    {
        $query->whereHas('shares', fn (Builder $query) => $query
            ->accepted()
            ->whereBelongsTo($user, 'recipient')
            ->when($sharer, fn (Builder $query) => $query->whereBelongsTo($sharer, 'sharer')));
    }

    /**
     * @param  Builder<Study>  $query
     */
    public function scopeUnexpired(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * @param  Builder<Study>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }

    /**
     * @param  Builder<Study>  $query
     */
    public function scopeAwaitingHandover(Builder $query): void
    {
        $query->whereNotNull('handover_ends_at');
    }

    /**
     * Studies awaiting handover that nobody will keep: the time to decide is over, or nobody can open them any more.
     *
     * @param  Builder<Study>  $query
     */
    public function scopeAbandoned(Builder $query): void
    {
        $query->awaitingHandover()->where(fn (Builder $query) => $query
            ->where('handover_ends_at', '<=', now())
            ->orWhereDoesntHave('shares', fn (Builder $query) => $query->accepted()));
    }

    /**
     * Filter studies by patient name, patient ID, accession number or description.
     *
     * @param  Builder<Study>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.trim($term).'%';

        $query->where(function (Builder $query) use ($like) {
            $query->where('patient_name', 'like', $like)
                ->orWhere('patient_id', 'like', $like)
                ->orWhere('accession_number', 'like', $like)
                ->orWhere('study_description', 'like', $like);
        });
    }
}
