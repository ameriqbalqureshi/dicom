<?php

use App\Livewire\Studies\UploadStudies;
use App\Models\DicomArchive;
use App\Models\DicomInstance;
use App\Models\GuestLink;
use App\Models\Series;
use App\Models\Study;
use App\Models\User;
use App\Services\Dicom\DicomArchives;
use App\Services\Dicom\DicomImporter;
use App\Services\Dicom\StudyDeleter;
use App\Services\Dicom\ViewerSeries;
use App\Services\GuestLinkIssuer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

const ZIP_BYTES = "PK\x03\x04 the rest of a zip, which the server never opens";

beforeEach(function () {
    Storage::fake('dicom');
});

/**
 * An archive that has arrived in storage.
 */
function storedArchive(User $uploader, string $content = ZIP_BYTES): DicomArchive
{
    $archive = DicomArchive::factory()->for($uploader, 'uploader')->create(['file_size' => strlen($content)]);
    Storage::disk('dicom')->put($archive->file_path, $content);

    return $archive;
}

/**
 * An image as the browser reports it after reading a zip.
 *
 * @param  array<string, mixed>  $overrides
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function zipEntry(array $overrides = [], array $attributes = []): array
{
    return [
        'name' => 'slice.dcm',
        'offset' => 4,
        'length' => 10,
        'size' => 30,
        'method' => 8,
        'attributes' => [
            'transferSyntaxUid' => '1.2.840.10008.1.2.1',
            'sopInstanceUid' => '1.2.3.4.5',
            'studyInstanceUid' => '1.2.3',
            'seriesInstanceUid' => '1.2.3.4',
            'patientName' => 'DOE^JANE',
            'patientBirthDate' => '19800102',
            'studyDate' => '20240315',
            'modality' => 'MR',
            'seriesNumber' => '3',
            'instanceNumber' => '7',
            'numberOfFrames' => '1',
            'rows' => 256,
            'columns' => 256,
            ...$attributes,
        ],
        ...$overrides,
    ];
}

test('an image the browser reports from a zip is filed under its study and points into the zip, which stays whole', function () {
    $technician = User::factory()->technician()->create();
    $archive = storedArchive($technician);

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importArchiveEntries', $archive->id, [zipEntry()])
        ->assertSee('Imported.')
        ->assertSet('resultCounts.imported', 1);

    expect(Study::sole())
        ->uploaded_by->toBe($technician->id)
        ->patient_name->toBe('DOE JANE')
        ->study_date->toDateString()->toBe('2024-03-15');

    expect(Series::sole())->modality->toBe('MR')->series_number->toBe(3);

    expect(DicomInstance::sole())
        ->dicom_archive_id->toBe($archive->id)
        ->file_path->toBeNull()
        ->file_size->toBe(10)
        ->archive_entry_offset->toBe(4)
        ->archive_entry_length->toBe(10)
        ->archive_entry_size->toBe(30)
        ->archive_entry_method->toBe(8)
        ->instance_number->toBe(7)
        ->rows->toBe(256);

    expect(Storage::disk('dicom')->allFiles())->toBe([$archive->file_path]);
});

test('entries that could not be real images are reported, while the others are imported', function (array $entry, string $message) {
    $technician = User::factory()->technician()->create();
    $archive = storedArchive($technician);

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importArchiveEntries', $archive->id, [$entry, zipEntry()])
        ->assertSet('results.0.status', 'failed')
        ->assertSet('results.0.message', $message)
        ->assertSet('resultCounts.imported', 1);

    expect(DicomInstance::count())->toBe(1);
})->with([
    'beyond the end of the zip' => [zipEntry(['offset' => 1000]), 'The zip entry could not be read.'],
    'a method viewers cannot read' => [zipEntry(['method' => 9]), 'The zip entry could not be read.'],
    'a negative offset' => [zipEntry(['offset' => -1]), 'The zip entry could not be read.'],
    'a location that is not a number' => [zipEntry(['length' => 'lots']), 'The zip entry could not be read.'],
    'too large once extracted' => [zipEntry(['size' => DicomImporter::MAX_ARCHIVE_ENTRY_SIZE + 1]), 'The image is larger than 1 GB.'],
    'no study' => [zipEntry(['attributes' => ['transferSyntaxUid' => '1.2', 'sopInstanceUid' => '1']]), 'The file is missing the required studyInstanceUid attribute.'],
    'no attributes' => [zipEntry(['attributes' => 'none']), 'The file meta information has no transfer syntax.'],
    'an overlong UID' => [zipEntry(attributes: ['sopInstanceUid' => str_repeat('1', 65)]), "The file's sopInstanceUid attribute is too long."],
    'an impossible number of frames' => [zipEntry(attributes: ['numberOfFrames' => '999999999']), 'The file has too many frames.'],
]);

test('values that would not fit the database are shortened or dropped rather than failing the import', function () {
    $technician = User::factory()->technician()->create();
    $archive = storedArchive($technician);

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importArchiveEntries', $archive->id, [zipEntry(attributes: [
            'modality' => str_repeat('M', 40),
            'instanceNumber' => '-5',
            'seriesNumber' => '12\\13',
            'rows' => 70000,
        ])])
        ->assertSet('resultCounts.imported', 1);

    expect(Series::sole())->modality->toHaveLength(16)->series_number->toBe(12);
    expect(DicomInstance::sole())->instance_number->toBeNull()->rows->toBeNull();
});

test('an image in the zip that is already uploaded counts as skipped', function () {
    $technician = User::factory()->technician()->create();
    $archive = storedArchive($technician);

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importArchiveEntries', $archive->id, [zipEntry(), zipEntry()])
        ->assertSet('resultCounts.imported', 1)
        ->assertSet('resultCounts.skipped', 1);
});

test('a zip with a study beyond the package, or another patient study, is refused before its upload starts', function () {
    $technician = User::factory()->technician()->withLimits(['max_uploads' => 0])->create();

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('prepareArchive', 'study.zip', 1000, ['1.2.3'])
        ->assertReturned(null)
        ->assertSet('results.0.status', 'failed');

    $patient = User::factory()->patient()->create();
    $current = storedArchive($patient);
    Livewire::actingAs($patient)->test(UploadStudies::class)->call('importArchiveEntries', $current->id, [zipEntry()]);

    Livewire::actingAs($patient)
        ->test(UploadStudies::class)
        ->call('prepareArchive', 'other.zip', 1000, ['9.9.9'])
        ->assertReturned(null)
        ->assertSet('results.0.status', 'failed');

    expect(DicomArchive::whereKeyNot($current)->count())->toBe(0);
});

test('a zip over 5 GB, or beyond the storage left in the package, is refused before its upload starts', function () {
    $technician = User::factory()->technician()->withLimits(['max_storage_mb' => 1])->create();

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('prepareArchive', 'huge.zip', DicomArchives::MAX_SIZE + 1, [])
        ->assertReturned(null)
        ->call('prepareArchive', 'big.zip', 2 * 1024 * 1024, [])
        ->assertReturned(null)
        ->assertSet('results.0.message', 'A zip must be 5 GB or smaller.')
        ->assertSet('results.1.status', 'failed');

    expect(DicomArchive::count())->toBe(0);
});

test('with S3, the browser gets a signed link for the whole zip in the uploader\'s folder', function () {
    Storage::fake('dicom', ['direct_transfers' => true]);
    Storage::disk('dicom')->buildTemporaryUploadUrlsUsing(fn (string $path) => ['url' => "https://bucket.test/{$path}?signed", 'headers' => []]);
    $technician = User::factory()->technician()->create();

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('prepareArchive', 'study.zip', 1000, ['1.2.3'])
        ->assertReturned(function (array $target) use ($technician) {
            $archive = DicomArchive::sole();

            return $target['archive'] === $archive->id
                && $target['chunkSize'] === null
                && str_starts_with($archive->file_path, "users/{$technician->id}/archives/")
                && $target['url'] === "https://bucket.test/{$archive->file_path}?signed";
        });
});

test('without S3, the browser sends the zip to the application a chunk at a time and in order', function () {
    $technician = User::factory()->technician()->create();
    $archive = DicomArchive::factory()->pending()->for($technician, 'uploader')->create(['file_size' => 6]);
    $url = route('archives.chunks', ['archive' => $archive]);
    $this->actingAs($technician);

    $this->call('PUT', "{$url}?offset=0", content: 'abc')->assertNoContent();
    $this->call('PUT', "{$url}?offset=9", content: 'nope')->assertConflict();
    $this->call('PUT', "{$url}?offset=3", content: 'defg')->assertStatus(413);
    $this->call('PUT', "{$url}?offset=3", content: 'def')->assertNoContent();

    expect(Storage::disk('dicom')->get($archive->file_path))->toBe('abcdef');
});

test('chunks are only accepted from the uploader, before the zip is confirmed, and never with S3', function () {
    $technician = User::factory()->technician()->create();
    $archive = DicomArchive::factory()->pending()->for($technician, 'uploader')->create();
    $url = route('archives.chunks', ['archive' => $archive]);

    $this->actingAs(User::factory()->technician()->create());
    $this->call('PUT', "{$url}?offset=0", content: 'abc')->assertForbidden();

    $this->actingAs($technician);
    $confirmed = storedArchive($technician);
    $this->call('PUT', route('archives.chunks', ['archive' => $confirmed, 'offset' => strlen(ZIP_BYTES)]), content: 'abc')->assertForbidden();

    Storage::fake('dicom', ['direct_transfers' => true]);
    $this->call('PUT', "{$url}?offset=0", content: 'abc')->assertNotFound();
});

test('a confirmed zip takes its real size from storage, not the browser', function () {
    $technician = User::factory()->technician()->create();
    $archive = DicomArchive::factory()->pending()->for($technician, 'uploader')->create(['file_size' => 1]);
    Storage::disk('dicom')->put($archive->file_path, ZIP_BYTES);

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('confirmArchive', $archive->id)
        ->assertReturned(true);

    expect($archive->fresh())->file_size->toBe(strlen(ZIP_BYTES))->uploaded_at->not->toBeNull();
});

test('a zip that did not arrive, is not a zip, or is bigger than was allowed, is refused and then removed', function (?string $content, string $message) {
    $technician = User::factory()->technician()->withLimits(['max_storage_mb' => 1])->create();
    $archive = DicomArchive::factory()->pending()->for($technician, 'uploader')->create();

    if ($content !== null) {
        Storage::disk('dicom')->put($archive->file_path, $content);
    }

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('confirmArchive', $archive->id)
        ->assertReturned(false)
        ->assertSet('results.0.message', fn (string $actual) => str_contains($actual, $message))
        ->call('finishArchive', $archive->id);

    expect(DicomArchive::count())->toBe(0);
    Storage::disk('dicom')->assertMissing($archive->file_path);
})->with([
    'never arrived' => [null, 'The upload did not finish. Please try this zip again.'],
    'not a zip' => ['just some text', 'The file is not a zip.'],
    'bigger than the storage left' => ["PK\x03\x04".str_repeat('x', 2 * 1024 * 1024), 'This would exceed the 1 MB of storage'],
]);

test('images are not imported until the zip is confirmed, and batches are limited', function () {
    $technician = User::factory()->technician()->create();
    $pending = DicomArchive::factory()->pending()->for($technician, 'uploader')->create();

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importArchiveEntries', $pending->id, [zipEntry()])
        ->assertStatus(422);

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importArchiveEntries', storedArchive($technician)->id, array_fill(0, UploadStudies::ARCHIVE_BATCH_SIZE + 1, zipEntry()))
        ->assertStatus(422);

    expect(DicomInstance::count())->toBe(0);
});

test('nobody can use another person\'s zip', function (string $method, array $parameters) {
    $owner = User::factory()->technician()->create();
    $archive = storedArchive($owner);

    Livewire::actingAs(User::factory()->technician()->create())
        ->test(UploadStudies::class)
        ->call($method, $archive->id, ...$parameters)
        ->assertNotFound();

    expect($archive->fresh()->exists)->toBeTrue();
    Storage::disk('dicom')->assertExists($archive->file_path);
})->with([
    'importing' => ['importArchiveEntries', [[]]],
    'confirming' => ['confirmArchive', []],
    'finishing' => ['finishArchive', []],
]);

test('the whole zip counts against the uploader\'s storage, including what is not an image', function () {
    $technician = User::factory()->technician()->create();
    $archive = storedArchive($technician, str_repeat('x', 100));

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importArchiveEntries', $archive->id, [
            zipEntry(attributes: ['sopInstanceUid' => '1.2.3.4.5']),
            zipEntry(attributes: ['sopInstanceUid' => '1.2.3.4.6']),
        ])
        ->call('finishArchive', $archive->id)
        ->call('finishArchive', $archive->id);

    expect((int) DicomInstance::sum('file_size'))->toBe(100);
});

test('a zip that added no images is removed once finished', function () {
    $technician = User::factory()->technician()->create();
    $archive = storedArchive($technician);

    Livewire::actingAs($technician)->test(UploadStudies::class)->call('finishArchive', $archive->id);

    expect(DicomArchive::count())->toBe(0);
    Storage::disk('dicom')->assertMissing($archive->file_path);
});

test('the viewer is told which part of the zip holds each image', function () {
    $technician = User::factory()->technician()->create();
    $archive = storedArchive($technician);
    Livewire::actingAs($technician)->test(UploadStudies::class)->call('importArchiveEntries', $archive->id, [
        zipEntry(),
        zipEntry(attributes: ['sopInstanceUid' => '1.2.3.4.6', 'numberOfFrames' => '2']),
    ]);
    [$stacked, $run] = [DicomInstance::where('number_of_frames', 1)->sole(), DicomInstance::where('number_of_frames', 2)->sole()];

    [$stack, $cine] = ViewerSeries::forStudy(Study::sole(), fn (DicomInstance $instance) => "/file/{$instance->id}");

    expect($stack['archiveEntries'])->toBe([
        "/file/{$stacked->id}" => ['archive' => $archive->id, 'offset' => 4, 'length' => 10, 'size' => 30, 'method' => 8],
    ])->and($stack['slices'][0]['url'])->toBe("/file/{$stacked->id}")
        ->and(array_keys($cine['archiveEntries']))->toBe(["/file/{$run->id}"])
        ->and($cine['slices'])->toHaveCount(2);
});

test('images with their own file tell the viewer nothing about zips', function () {
    $instance = DicomInstance::factory()->create();

    [$entry] = ViewerSeries::forStudy($instance->series->study, fn (DicomInstance $instance) => '/file');

    expect($entry)->not->toHaveKey('archiveEntries');
});

test('the part of the zip a viewer asks for is what it receives, from the same address as any image', function () {
    $technician = User::factory()->technician()->create();
    $archive = storedArchive($technician);
    $instance = DicomInstance::factory()->for($archive, 'archive')->create(['file_path' => null, 'series_id' => Series::factory()->for(Study::factory()->for($technician, 'uploader'))]);

    $response = $this->actingAs($technician)
        ->withHeader('Range', 'bytes=4-6')
        ->get(route('instances.file', $instance))
        ->assertStatus(206)
        ->assertHeader('Content-Type', 'application/zip');

    ob_start();
    $response->baseResponse->sendContent();
    expect(ob_get_clean())->toBe(substr(ZIP_BYTES, 4, 3));
});

test('nobody without access to the study can read from its zip', function () {
    $technician = User::factory()->technician()->create();
    $archive = storedArchive($technician);
    $instance = DicomInstance::factory()->for($archive, 'archive')->create(['file_path' => null, 'series_id' => Series::factory()->for(Study::factory()->for($technician, 'uploader'))]);

    $this->actingAs(User::factory()->technician()->create())
        ->get(route('instances.file', $instance))
        ->assertForbidden();
});

test('with S3, viewers and guests are sent to a signed link to the zip', function () {
    Storage::fake('dicom', ['direct_transfers' => true]);
    Storage::disk('dicom')->buildTemporaryUrlsUsing(fn (string $path, $expiration, array $options) => "https://bucket.test/{$path}?type={$options['ResponseContentType']}");
    $physician = User::factory()->physician()->create();
    $archive = storedArchive($physician);
    $study = Study::factory()->for($physician, 'uploader')->create();
    $instance = DicomInstance::factory()->for($archive, 'archive')->create(['file_path' => null, 'series_id' => Series::factory()->for($study)]);
    $token = Str::random(GuestLinkIssuer::TOKEN_LENGTH);
    GuestLink::factory()->for($study)->withToken($token)->create(['created_by' => $physician->id]);

    $this->get(route('guest.instances.file', [$token, $instance]))
        ->assertRedirect("https://bucket.test/{$archive->file_path}?type=application/zip");

    $this->actingAs($physician)
        ->get(route('instances.file', $instance))
        ->assertRedirect("https://bucket.test/{$archive->file_path}?type=application/zip");
});

test('deleting a study removes its zip only when no other study in that zip still needs it', function () {
    $technician = User::factory()->technician()->create();
    $archive = storedArchive($technician);
    Livewire::actingAs($technician)->test(UploadStudies::class)->call('importArchiveEntries', $archive->id, [
        zipEntry(),
        zipEntry(attributes: ['sopInstanceUid' => '5.5.5.1', 'studyInstanceUid' => '5.5', 'seriesInstanceUid' => '5.5.5']),
    ]);
    [$first, $second] = Study::orderBy('id')->get();

    app(StudyDeleter::class)->delete($first);

    Storage::disk('dicom')->assertExists($archive->file_path);
    expect($archive->fresh())->not->toBeNull();

    app(StudyDeleter::class)->delete($second);

    Storage::disk('dicom')->assertMissing($archive->file_path);
    expect($archive->fresh())->toBeNull();
});

test('zips abandoned for a day without any images are cleaned up, and nothing else', function () {
    $technician = User::factory()->technician()->create();
    $abandoned = storedArchive($technician);
    $abandoned->forceFill(['created_at' => now()->subHours(DicomArchives::ABANDONED_AFTER_HOURS + 1)])->save();
    $inProgress = storedArchive($technician);
    $inUse = storedArchive($technician);
    $inUse->forceFill(['created_at' => now()->subYear()])->save();
    DicomInstance::factory()->for($inUse, 'archive')->create(['file_path' => null]);

    $this->artisan('uploads:prune-incoming')->expectsOutput('Deleted 1 abandoned upload.')->assertSuccessful();

    Storage::disk('dicom')->assertMissing($abandoned->file_path);
    Storage::disk('dicom')->assertExists($inProgress->file_path);
    Storage::disk('dicom')->assertExists($inUse->file_path);
    expect(DicomArchive::count())->toBe(2);
});
