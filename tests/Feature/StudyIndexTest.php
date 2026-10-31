<?php

use App\Livewire\Studies\StudyIndex;
use App\Models\Series;
use App\Models\Study;
use App\Models\User;
use Livewire\Livewire;

test('users see the studies they uploaded', function () {
    $user = User::factory()->create();
    $study = Study::factory()->for($user, 'uploader')->create(['patient_name' => 'DOE JANE']);

    $this->actingAs($user)
        ->get(route('studies.index'))
        ->assertOk()
        ->assertSee('DOE JANE')
        ->assertSee(route('studies.show', $study));
});

test('users do not see studies uploaded by someone else', function () {
    $user = User::factory()->technician()->create();
    Study::factory()->create(['patient_name' => 'ROE RICHARD']);
    Series::factory()->create(['modality' => 'MG']);

    Livewire::actingAs($user)
        ->test(StudyIndex::class)
        ->assertDontSee('ROE RICHARD')
        ->assertViewHas('modalities', fn ($modalities) => $modalities->isEmpty());
});

test('administrators see every study', function () {
    Study::factory()->create(['patient_name' => 'DOE JANE']);
    Study::factory()->create(['patient_name' => 'ROE RICHARD']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(StudyIndex::class)
        ->assertSee('DOE JANE')
        ->assertSee('ROE RICHARD');
});

test('studies can be searched by patient name, patient ID or accession number', function (string $term) {
    $user = User::factory()->create();
    Study::factory()->for($user, 'uploader')->create(['patient_name' => 'DOE JANE', 'patient_id' => 'PID001', 'accession_number' => 'ACC777']);
    Study::factory()->for($user, 'uploader')->create(['patient_name' => 'ROE RICHARD', 'patient_id' => 'PID002', 'accession_number' => 'ACC888']);

    Livewire::actingAs($user)
        ->test(StudyIndex::class)
        ->set('search', $term)
        ->assertSee('DOE JANE')
        ->assertDontSee('ROE RICHARD');
})->with(['patient name' => 'jane', 'patient ID' => 'PID001', 'accession number' => 'ACC777']);

test('studies can be filtered by modality', function () {
    $user = User::factory()->create();
    Series::factory()->for(Study::factory()->for($user, 'uploader')->state(['patient_name' => 'CT PATIENT']))->create(['modality' => 'CT']);
    Series::factory()->for(Study::factory()->for($user, 'uploader')->state(['patient_name' => 'MR PATIENT']))->create(['modality' => 'MR']);

    Livewire::actingAs($user)
        ->test(StudyIndex::class)
        ->set('modality', 'MR')
        ->assertSee('MR PATIENT')
        ->assertDontSee('CT PATIENT');
});

test('an empty list explains that nothing matches the filters', function () {
    $user = User::factory()->create();
    Study::factory()->for($user, 'uploader')->create(['patient_name' => 'DOE JANE']);

    Livewire::actingAs($user)
        ->test(StudyIndex::class)
        ->set('search', 'nobody')
        ->assertSee('No studies match your filters.');
});
