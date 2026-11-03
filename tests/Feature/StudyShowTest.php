<?php

use App\Livewire\Studies\StudyShow;
use App\Models\DicomInstance;
use App\Models\Series;
use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('single-frame images of a series are shown as one stack', function () {
    $series = Series::factory()->create(['modality' => 'MR', 'series_description' => 'T2 Axial']);
    $first = DicomInstance::factory()->for($series)->create(['instance_number' => 1]);
    $second = DicomInstance::factory()->for($series)->create(['instance_number' => 2]);

    $component = Livewire::actingAs($series->study->uploader)
        ->test(StudyShow::class, ['study' => $series->study])
        ->assertOk();

    expect($component->viewData('viewerSeries'))->toBe([[
        'id' => "series-{$series->id}",
        'label' => 'MR T2 Axial',
        'isRun' => false,
        'slices' => [
            ['url' => route('instances.file', $first), 'frame' => 0, 'instanceNumber' => 1],
            ['url' => route('instances.file', $second), 'frame' => 0, 'instanceNumber' => 2],
        ],
    ]]);
});

test('each multi-frame image in a series is shown as its own run', function () {
    $series = Series::factory()->create(['modality' => 'XA', 'series_description' => 'CORONARY ANGIO']);
    $second = DicomInstance::factory()->for($series)->create(['instance_number' => 2, 'number_of_frames' => 3]);
    $first = DicomInstance::factory()->for($series)->create(['instance_number' => 1, 'number_of_frames' => 2]);

    $entries = Livewire::actingAs($series->study->uploader)
        ->test(StudyShow::class, ['study' => $series->study])
        ->viewData('viewerSeries');

    expect(array_column($entries, 'id'))->toBe(["instance-{$first->id}", "instance-{$second->id}"])
        ->and(array_column($entries, 'label'))->toBe(['XA CORONARY ANGIO · Run 1', 'XA CORONARY ANGIO · Run 2'])
        ->and(array_column($entries, 'isRun'))->toBe([true, true])
        ->and(array_map(fn (array $entry): array => array_column($entry['slices'], 'frame'), $entries))->toBe([[0, 1], [0, 1, 2]]);
});

test('slices are ordered by instance number', function () {
    $series = Series::factory()->create();
    DicomInstance::factory()->for($series)->create(['instance_number' => 2]);
    DicomInstance::factory()->for($series)->create(['instance_number' => 1]);

    $component = Livewire::actingAs($series->study->uploader)->test(StudyShow::class, ['study' => $series->study]);

    expect(array_column($component->viewData('viewerSeries')[0]['slices'], 'instanceNumber'))->toBe([1, 2]);
});

test('the study page renders the playback, navigation and full screen controls', function () {
    $instance = DicomInstance::factory()->create(['number_of_frames' => 3]);

    $this->actingAs($instance->series->study->uploader)
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

test('uploaders can delete their own study', function () {
    $study = Study::factory()->for(User::factory()->technician(), 'uploader')->create();

    Livewire::actingAs($study->uploader)
        ->test(StudyShow::class, ['study' => $study])
        ->assertSee('Delete study')
        ->call('delete')
        ->assertRedirect(route('studies.index'));

    $this->assertModelMissing($study);
});

test('users cannot open a study uploaded by someone else', function () {
    $study = Study::factory()->create();

    $this->actingAs(User::factory()->technician()->create())
        ->get(route('studies.show', $study))
        ->assertForbidden();

    $this->assertModelExists($study);
});

test('a physician can remove a study shared with them from their study list', function () {
    $share = StudyShare::factory()->create();

    Livewire::actingAs($share->recipient)
        ->test(StudyShow::class, ['study' => $share->study])
        ->assertSee('Remove from my list')
        ->call('removeFromList')
        ->assertRedirect(route('studies.index'));

    expect(StudyShare::count())->toBe(0)
        ->and(Study::count())->toBe(1);
});

test('the owner of a study is not offered to remove it from their list', function () {
    $study = Study::factory()->create();

    Livewire::actingAs($study->uploader)
        ->test(StudyShow::class, ['study' => $study])
        ->assertDontSee('Remove from my list')
        ->call('removeFromList')
        ->assertForbidden();
});
