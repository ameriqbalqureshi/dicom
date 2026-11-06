<?php

namespace App\Models;

use Database\Factories\DicomInstanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DicomInstance extends Model
{
    /** @use HasFactory<DicomInstanceFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'series_id',
        'sop_instance_uid',
        'sop_class_uid',
        'transfer_syntax_uid',
        'instance_number',
        'rows',
        'columns',
        'number_of_frames',
        'file_path',
        'file_size',
        'uploaded_by',
        'dicom_archive_id',
        'archive_entry_offset',
        'archive_entry_length',
        'archive_entry_size',
        'archive_entry_method',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'instance_number' => 'integer',
            'rows' => 'integer',
            'columns' => 'integer',
            'number_of_frames' => 'integer',
            'file_size' => 'integer',
            'archive_entry_offset' => 'integer',
            'archive_entry_length' => 'integer',
            'archive_entry_size' => 'integer',
            'archive_entry_method' => 'integer',
        ];
    }

    /**
     * The zip holding this instance's bytes, when it does not have a file of its own.
     *
     * @return BelongsTo<DicomArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(DicomArchive::class, 'dicom_archive_id');
    }

    /**
     * @return BelongsTo<Series, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }
}
