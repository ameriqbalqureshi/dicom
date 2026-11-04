<section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
    <h2 class="font-semibold">
        Notes
        <span class="ml-1 font-normal text-slate-400">({{ $notes->count() }})</span>
    </h2>

    <form wire:submit="addNote" class="flex flex-col gap-2">
        <div class="flex flex-wrap items-start gap-4">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Date</span>
                <input wire:model="notedOn" type="date" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                @error('notedOn') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>
            <label class="flex min-w-72 flex-1 flex-col gap-1 text-sm">
                <span class="text-slate-300">Add a note</span>
                <textarea wire:model="body" rows="3" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none"></textarea>
                @error('body') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>
        </div>
        <div>
            <button type="submit" class="rounded-md border border-sky-600 px-3 py-1.5 text-sm text-sky-300 hover:bg-sky-950">Save note</button>
        </div>
    </form>

    @foreach ($notes as $note)
        <article wire:key="note-{{ $note->id }}" class="flex flex-col gap-2 rounded-md border border-slate-800 p-3 text-sm">
            @if ($editingId === $note->id)
                <form wire:submit="saveEdit" class="flex flex-col gap-2">
                    <input wire:model="editingNotedOn" type="date" aria-label="Note date" class="self-start rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('editingNotedOn') <span class="text-red-400">{{ $message }}</span> @enderror
                    <textarea wire:model="editingBody" rows="3" aria-label="Edit note"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none"></textarea>
                    @error('editingBody') <span class="text-red-400">{{ $message }}</span> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-md border border-sky-600 px-3 py-1.5 text-sky-300 hover:bg-sky-950">Save</button>
                        <button type="button" wire:click="cancelEditing" class="text-slate-400 hover:text-white">Cancel</button>
                    </div>
                </form>
            @else
                <header class="flex flex-wrap items-start justify-between gap-3">
                    <span class="font-medium">{{ $note->noted_on->toFormattedDateString() }}</span>
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="startEditing({{ $note->id }})" class="text-slate-300 hover:text-white">Edit</button>
                        <button type="button" wire:click="deleteNote({{ $note->id }})" wire:confirm="Delete this note?" class="text-red-400 hover:text-red-300">Delete</button>
                    </div>
                </header>
                <p class="whitespace-pre-line text-slate-200">{{ $note->body }}</p>
            @endif
        </article>
    @endforeach
</section>
