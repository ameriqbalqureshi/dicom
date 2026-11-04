<?php

namespace App\Models;

use Database\Factories\PatientNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated note a physician keeps on a patient record. Its text is encrypted in the database.
 */
class PatientNote extends Model
{
    /** @use HasFactory<PatientNoteFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'noted_on',
        'body',
    ];

    /**
     * The relationships whose timestamps change with this note.
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
            'noted_on' => 'date',
            'body' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<PatientRecord, $this>
     */
    public function patientRecord(): BelongsTo
    {
        return $this->belongsTo(PatientRecord::class);
    }
}
