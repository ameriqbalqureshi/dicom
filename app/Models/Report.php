<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physician's report on a study. A draft is only visible to its author and can be edited; once finalized it is locked,
 * visible to everyone who can open the study, and corrected only by adding addenda. The author's name and credentials
 * are copied onto the report when it is finalized, so the signed report does not change if their profile does.
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'study_id',
        'author_id',
        'indication',
        'findings',
        'impression',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
        ];
    }

    public function isFinal(): bool
    {
        return $this->finalized_at !== null;
    }

    /**
     * Lock the report and record who signed it.
     */
    public function finalize(): void
    {
        $this->forceFill([
            'finalized_at' => now(),
            'author_name' => $this->author->name,
            'author_qualifications' => $this->author->qualifications,
            'author_registration_number' => $this->author->registration_number,
        ])->save();
    }

    /**
     * The name shown for the author: as signed for a final report, otherwise their current name.
     */
    public function authorName(): string
    {
        return $this->author_name ?? $this->author?->name ?? 'Unknown physician';
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<ReportAddendum, $this>
     */
    public function addenda(): HasMany
    {
        return $this->hasMany(ReportAddendum::class)->oldest()->oldest('id');
    }

    /**
     * @param  Builder<Report>  $query
     */
    public function scopeFinal(Builder $query): void
    {
        $query->whereNotNull('finalized_at');
    }

    /**
     * Limit reports to those the user may read: final reports, and their own drafts.
     *
     * @param  Builder<Report>  $query
     */
    public function scopeReadableBy(Builder $query, ?User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNotNull('finalized_at')
            ->when($user, fn (Builder $query) => $query->orWhereBelongsTo($user, 'author')));
    }
}
