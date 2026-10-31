<?php

use App\Livewire\Studies\StudyShow;
use App\Models\DicomInstance;
use App\Models\Series;
use App\Models\Study;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('the study page lists its series and image URLs for the viewer', function () {
    $series = Series::factory()->create(['modality' => 'MR', 'series_description' => 'T2 Axial']);
    $instance = DicomInstance::factory()->for($series)->create(['number_of_frames' => 2]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(StudyShow::class, ['study' => $series->study])
        ->assertOk();

    expect($component->viewData('viewerSeries'))->toBe([[
        'id' => $series->id,
        'label' => 'MR T2 Axial',
        'slices' => [
            ['url' => route('instances.file', $instance), 'frame' => 0, 'instanceNumber' => $instance->instance_number],
            ['url' => route('instances.file', $instance), 'frame' => 1, 'instanceNumber' => $instance->instance_number],
        ],
    ]]);
});

test('slices are ordered by instance number', function () {
    $series = Series::factory()->create();
    DicomInstance::factory()->for($series)->create(['instance_number' => 2]);
    DicomInstance::factory()->for($series)->create(['instance_number' => 1]);

    $component = Livewire::actingAs(User::factory()->create())->test(StudyShow::class, ['study' => $series->study]);

    expect(array_column($component->viewData('viewerSeries')[0]['slices'], 'instanceNumber'))->toBe([1, 2]);
});

test('the study page renders the playback, navigation and full screen controls', function () {
    $instance = DicomInstance::factory()->create(['number_of_frames' => 3]);

    $this->actingAs(User::factory()->create())
        ->get(route('studies.show', $instance->series->study))
        ->assertOk()
        ->assertSee('x-on:click="togglePlay()"', false)
        ->assertSee('x-on:click="first()"', false)
        ->assertSee('x-on:click="nextSeries()"', false)
        ->assertSee('x-on:click="toggleFullscreen()"', false)
        ->assertSee('aria-label="Playback speed in frames per second"', false);
});

test('the study page returns 404 for an unknown study', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('studies.show', 999))
        ->assertNotFound();
});

test('administrators can delete a study with its files', function () {
    Storage::fake('dicom');
    Storage::disk('dicom')->put('2024/03/a.dcm', 'dicom');
    $instance = DicomInstance::factory()->create(['file_path' => '2024/03/a.dcm']);
    $study = $instance->series->study;

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(StudyShow::class, ['study' => $study])
        ->call('delete')
        ->assertRedirect(route('studies.index'));

    $this->assertModelMissing($study);
    $this->assertModelMissing($instance);
    Storage::disk('dicom')->assertMissing('2024/03/a.dcm');
});

test('technicians cannot delete a study', function () {
    $study = Study::factory()->create();

    Livewire::actingAs(User::factory()->technician()->create())
        ->test(StudyShow::class, ['study' => $study])
        ->assertDontSee('Delete study')
        ->call('delete')
        ->assertForbidden();

    $this->assertModelExists($study);
});
