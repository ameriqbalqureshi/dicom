<?php

namespace App\Models;

use Database\Factories\DicomArchiveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A zip of DICOM files kept in storage as uploaded. It is never unpacked on the server: viewers read each image out of
 * it with a range request.
 */
class DicomArchive extends Model
{
    /** @use HasFactory<DicomArchiveFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uploaded_by',
        'original_name',
        'file_path',
        'file_size',
        'uploaded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<DicomInstance, $this>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(DicomInstance::class);
    }
}
