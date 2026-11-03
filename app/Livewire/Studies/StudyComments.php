<?php

namespace App\Livewire\Studies;

use App\Models\Study;
use App\Models\StudyComment;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Lists the comments on a study and lets everyone who can open it add their own, edit them, and delete them. The
 * study's owner can also delete anyone's comment.
 */
class StudyComments extends Component
{
    public Study $study;

    public string $body = '';

    /**
     * The comment being edited, and its new text.
     */
    public ?int $editingId = null;

    public string $editingBody = '';

    public function mount(Study $study): void
    {
        $this->authorize('view', $study);

        $this->study = $study;
    }

    public function addComment(): void
    {
        $this->authorize('create', [StudyComment::class, $this->study]);

        $validated = $this->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->study->comments()->create(['author_id' => auth()->id(), 'body' => $validated['body']]);

        $this->reset('body');
    }

    public function startEditing(int $commentId): void
    {
        $comment = $this->findComment($commentId);
        $this->authorize('update', $comment);

        $this->editingId = $comment->id;
        $this->editingBody = $comment->body;
        $this->resetValidation('editingBody');
    }

    public function cancelEditing(): void
    {
        $this->reset('editingId', 'editingBody');
        $this->resetValidation('editingBody');
    }

    public function saveEdit(): void
    {
        $comment = $this->findComment((int) $this->editingId);
        $this->authorize('update', $comment);

        $validated = $this->validate(['editingBody' => ['required', 'string', 'max:5000']]);

        $comment->update(['body' => $validated['editingBody']]);

        $this->cancelEditing();
    }

    public function deleteComment(int $commentId): void
    {
        $comment = $this->findComment($commentId);
        $this->authorize('delete', $comment);

        $comment->delete();

        if ($this->editingId === $comment->id) {
            $this->cancelEditing();
        }
    }

    private function findComment(int $commentId): StudyComment
    {
        return $this->study->comments()->findOrFail($commentId)->setRelation('study', $this->study);
    }

    public function render(): View
    {
        $comments = $this->study->comments()
            ->with('author')
            ->get()
            ->each(fn (StudyComment $comment) => $comment->setRelation('study', $this->study));

        return view('livewire.studies.study-comments', [
            'comments' => $comments,
            'canComment' => auth()->user()->can('create', [StudyComment::class, $this->study]),
        ]);
    }
}
