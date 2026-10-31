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
        ];
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
     * Limit studies to those the user may view: their own uploads, or every study for administrators.
     *
     * @param  Builder<Study>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->whereBelongsTo($user, 'uploader');
        }
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
