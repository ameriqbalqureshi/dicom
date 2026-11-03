<?php

namespace App\Models;

use Database\Factories\StudyCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note about a study as a whole, visible to everyone who can open it (guests included). Its author can edit it, and
 * its author or the study's owner can delete it. Comments stay with the study when it changes hands, and are kept
 * without an author when the author's account is deleted.
 */
class StudyComment extends Model
{
    /** @use HasFactory<StudyCommentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'study_id',
        'author_id',
        'body',
    ];

    public function authorName(): string
    {
        return $this->author?->name ?? 'Deleted user';
    }

    /**
     * Whether the comment was changed after it was posted.
     */
    public function isEdited(): bool
    {
        return $this->updated_at->gt($this->created_at);
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
}
