<?php

namespace App\Livewire\PatientRecords;

use App\Models\PatientNote;
use App\Models\PatientRecord;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Lists the dated notes on a patient record and lets its physician add, edit and delete them.
 */
class PatientNotes extends Component
{
    public PatientRecord $patientRecord;

    public string $notedOn = '';

    public string $body = '';

    /**
     * The note being edited, its date and its new text.
     */
    public ?int $editingId = null;

    public string $editingNotedOn = '';

    public string $editingBody = '';

    public function mount(PatientRecord $patientRecord): void
    {
        $this->authorize('view', $patientRecord);

        $this->notedOn = now()->toDateString();
    }

    public function addNote(): void
    {
        $this->authorize('update', $this->patientRecord);

        $validated = $this->validate([
            'notedOn' => ['required', 'date'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $this->patientRecord->notes()->create(['noted_on' => $validated['notedOn'], 'body' => $validated['body']]);

        $this->reset('body');
    }

    public function startEditing(int $noteId): void
    {
        $this->authorize('update', $this->patientRecord);

        $note = $this->findNote($noteId);

        $this->editingId = $note->id;
        $this->editingNotedOn = $note->noted_on->toDateString();
        $this->editingBody = $note->body;
        $this->resetValidation(['editingNotedOn', 'editingBody']);
    }

    public function cancelEditing(): void
    {
        $this->reset('editingId', 'editingNotedOn', 'editingBody');
        $this->resetValidation(['editingNotedOn', 'editingBody']);
    }

    public function saveEdit(): void
    {
        $this->authorize('update', $this->patientRecord);

        $note = $this->findNote((int) $this->editingId);

        $validated = $this->validate([
            'editingNotedOn' => ['required', 'date'],
            'editingBody' => ['required', 'string', 'max:10000'],
        ]);

        $note->update(['noted_on' => $validated['editingNotedOn'], 'body' => $validated['editingBody']]);

        $this->cancelEditing();
    }

    public function deleteNote(int $noteId): void
    {
        $this->authorize('update', $this->patientRecord);

        $note = $this->findNote($noteId);
        $note->delete();

        if ($this->editingId === $note->id) {
            $this->cancelEditing();
        }
    }

    private function findNote(int $noteId): PatientNote
    {
        return $this->patientRecord->notes()->findOrFail($noteId);
    }

    public function render(): View
    {
        return view('livewire.patient-records.patient-notes', [
            'notes' => $this->patientRecord->notes()->get(),
        ]);
    }
}
