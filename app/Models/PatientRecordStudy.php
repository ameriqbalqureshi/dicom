<?php

namespace App\Models;

use Database\Factories\PatientRecordStudyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A study linked to a patient record. It keeps the study's date and description, so the record still shows that the
 * study existed after the study is deleted, when it has no study any more.
 */
class PatientRecordStudy extends Model
{
    /** @use HasFactory<PatientRecordStudyFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'study_id',
        'study_date',
        'study_description',
    ];

    /**
     * The relationships whose timestamps change with this link.
     *
     * @var list<string>
     */
    protected $touches = ['patientRecord'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'study_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<PatientRecord, $this>
     */
    public function patientRecord(): BelongsTo
    {
        return $this->belongsTo(PatientRecord::class);
    }

    /**
     * @return BelongsTo<Study, $this>
     */
    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }
}
