<?php

use App\Models\DicomInstance;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('dicom');
});

test('authenticated users can download an instance file', function () {
    Storage::disk('dicom')->put('2024/03/a.dcm', 'dicom-bytes');
    $instance = DicomInstance::factory()->create(['file_path' => '2024/03/a.dcm']);

    $response = $this->actingAs(User::factory()->create())->get(route('instances.file', $instance));

    $response->assertOk()->assertHeader('Content-Type', 'application/dicom');
    expect($response->streamedContent())->toBe('dicom-bytes');
});

test('guests cannot download instance files', function () {
    $instance = DicomInstance::factory()->create();

    $this->get(route('instances.file', $instance))->assertRedirect(route('login'));
});

test('a missing file returns 404', function () {
    $instance = DicomInstance::factory()->create(['file_path' => 'missing.dcm']);

    $this->actingAs(User::factory()->create())
        ->get(route('instances.file', $instance))
        ->assertNotFound();
});
