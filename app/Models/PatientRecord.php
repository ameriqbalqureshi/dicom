<?php

namespace App\Models;

use App\Enums\RiskFactor;
use App\Enums\Sex;
use Database\Factories\PatientRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physician's own record of a patient: clinical details, dated notes and any number of linked studies. Only the
 * physician who created it can open it, administrators included. The clinical details are encrypted in the database,
 * so they can be read with the application's key but not searched.
 */
class PatientRecord extends Model
{
    /** @use HasFactory<PatientRecordFactory> */
    use HasFactory;

    /**
     * The clinical sections of a record, in the order they are shown and printed, mapped to their labels.
     *
     * @var array<string, string>
     */
    public const CLINICAL_SECTIONS = [
        'diagnosis' => 'Diagnosis',
        'history' => 'Clinical history',
        'medications' => 'Medications',
        'allergies' => 'Allergies',
        'examination' => 'Examination',
        'investigations' => 'Investigations',
        'procedures' => 'Procedures',
        'plan' => 'Plan',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'medical_record_number',
        'date_of_birth',
        'sex',
        'phone',
        'risk_factors',
        'diagnosis',
        'history',
        'medications',
        'allergies',
        'examination',
        'investigations',
        'procedures',
        'plan',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'sex' => Sex::class,
            'risk_factors' => 'encrypted:array',
            ...array_fill_keys(array_keys(self::CLINICAL_SECTIONS), 'encrypted'),
        ];
    }

    /**
     * The risk factors ticked on this record.
     *
     * @return list<RiskFactor>
     */
    public function riskFactors(): array
    {
        return array_values(array_filter(array_map(RiskFactor::tryFrom(...), $this->risk_factors ?? [])));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The physician's dated notes, most recent first.
     *
     * @return HasMany<PatientNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(PatientNote::class)->latest('noted_on')->latest('id');
    }

    /**
     * The studies linked to this record, most recent first.
     *
     * @return HasMany<PatientRecordStudy, $this>
     */
    public function linkedStudies(): HasMany
    {
        return $this->hasMany(PatientRecordStudy::class)->latest('study_date')->latest('id');
    }

    /**
     * Filter records by patient name, medical record number or phone number.
     *
     * @param  Builder<PatientRecord>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.trim($term).'%';

        $query->where(function (Builder $query) use ($like) {
            $query->where('name', 'like', $like)
                ->orWhere('medical_record_number', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }
}
