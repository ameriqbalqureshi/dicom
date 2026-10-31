<?php

use App\Livewire\Studies\StudyIndex;
use App\Models\Series;
use App\Models\Study;
use App\Models\User;
use Livewire\Livewire;

test('authenticated users see the study list', function () {
    $study = Study::factory()->create(['patient_name' => 'DOE JANE']);

    $this->actingAs(User::factory()->create())
        ->get(route('studies.index'))
        ->assertOk()
        ->assertSee('DOE JANE')
        ->assertSee(route('studies.show', $study));
});

test('studies can be searched by patient name, patient ID or accession number', function (string $term) {
    Study::factory()->create(['patient_name' => 'DOE JANE', 'patient_id' => 'PID001', 'accession_number' => 'ACC777']);
    Study::factory()->create(['patient_name' => 'ROE RICHARD', 'patient_id' => 'PID002', 'accession_number' => 'ACC888']);

    Livewire::actingAs(User::factory()->create())
        ->test(StudyIndex::class)
        ->set('search', $term)
        ->assertSee('DOE JANE')
        ->assertDontSee('ROE RICHARD');
})->with(['patient name' => 'jane', 'patient ID' => 'PID001', 'accession number' => 'ACC777']);

test('studies can be filtered by modality', function () {
    Series::factory()->for(Study::factory()->state(['patient_name' => 'CT PATIENT']))->create(['modality' => 'CT']);
    Series::factory()->for(Study::factory()->state(['patient_name' => 'MR PATIENT']))->create(['modality' => 'MR']);

    Livewire::actingAs(User::factory()->create())
        ->test(StudyIndex::class)
        ->set('modality', 'MR')
        ->assertSee('MR PATIENT')
        ->assertDontSee('CT PATIENT');
});

test('an empty list explains that nothing matches the filters', function () {
    Study::factory()->create(['patient_name' => 'DOE JANE']);

    Livewire::actingAs(User::factory()->create())
        ->test(StudyIndex::class)
        ->set('search', 'nobody')
        ->assertSee('No studies match your filters.');
});
