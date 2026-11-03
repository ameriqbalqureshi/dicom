<section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h2 class="text-sm font-medium">
            Comments
            <span class="ml-1 text-slate-400">({{ $comments->count() }})</span>
        </h2>

        @if ($comments->isNotEmpty())
            <a href="{{ route('studies.comments.pdf', $study) }}" target="_blank" class="text-sm text-sky-400 hover:text-sky-300">Download PDF</a>
        @endif
    </div>

    @if ($comments->isEmpty())
        <p class="text-sm text-slate-400">No comments yet.</p>
    @endif

    @foreach ($comments as $comment)
        <article wire:key="comment-{{ $comment->id }}" class="flex flex-col gap-2 rounded-md border border-slate-800 p-3 text-sm">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <span class="font-medium">{{ $comment->authorName() }}</span>
                    <span class="ml-1 text-xs text-slate-400" title="{{ $comment->created_at->toDayDateTimeString() }}">
                        {{ $comment->created_at->diffForHumans() }}@if ($comment->isEdited()) · edited @endif
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    @can('update', $comment)
                        @if ($editingId !== $comment->id)
                            <button type="button" wire:click="startEditing({{ $comment->id }})" class="text-slate-300 hover:text-white">Edit</button>
                        @endif
                    @endcan
                    @can('delete', $comment)
                        <button type="button" wire:click="deleteComment({{ $comment->id }})" wire:confirm="Delete this comment?"
                            class="text-red-400 hover:text-red-300">Delete</button>
                    @endcan
                </div>
            </header>

            @if ($editingId === $comment->id)
                <form wire:submit="saveEdit" class="flex flex-col gap-2">
                    <textarea wire:model="editingBody" rows="3" aria-label="Edit comment"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none"></textarea>
                    @error('editingBody') <span class="text-red-400">{{ $message }}</span> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-md border border-sky-600 px-3 py-1.5 text-sky-300 hover:bg-sky-950">Save</button>
                        <button type="button" wire:click="cancelEditing" class="text-slate-400 hover:text-white">Cancel</button>
                    </div>
                </form>
            @else
                <p class="whitespace-pre-line text-slate-200">{{ $comment->body }}</p>
            @endif
        </article>
    @endforeach

    @if ($canComment)
        <form wire:submit="addComment" class="flex flex-col gap-2">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Add a comment</span>
                <textarea wire:model="body" rows="3" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none"></textarea>
                @error('body') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>
            <p class="text-xs text-slate-400">Everyone who can open this study, including guests with a link, can read comments.</p>
            <div>
                <button type="submit" class="rounded-md border border-sky-600 px-3 py-1.5 text-sm text-sky-300 hover:bg-sky-950">Post comment</button>
            </div>
        </form>
    @endif
</section>
