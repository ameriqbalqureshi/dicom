<?php

use App\Livewire\Studies\UploadStudies;
use App\Models\DicomInstance;
use App\Models\GuestLink;
use App\Models\Study;
use App\Models\User;
use App\Services\Dicom\DicomImporter;
use App\Services\Dicom\DicomStorage;
use App\Services\GuestLinkIssuer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\DicomFileBuilder;

/**
 * Stands in for an S3 disk: direct transfers on, with signed URLs that show what they were signed for.
 */
function fakeDirectTransfers(): void
{
    Storage::fake('dicom', ['direct_transfers' => true]);
    Storage::disk('dicom')->buildTemporaryUrlsUsing(
        fn (string $path, CarbonInterface $expiration, array $options) => "https://bucket.test/{$path}?expires={$expiration->timestamp}&type={$options['ResponseContentType']}",
    );
    Storage::disk('dicom')->buildTemporaryUploadUrlsUsing(fn (string $path) => [
        'url' => "https://bucket.test/{$path}?signed",
        'headers' => ['Host' => ['bucket.test'], 'x-amz-meta-origin' => ['viewer']],
    ]);
}

beforeEach(function () {
    $this->freezeSecond();
    fakeDirectTransfers();
});

test('viewers are sent to a short-lived signed link instead of the file passing through the application', function () {
    $instance = DicomInstance::factory()->create(['file_path' => '2024/03/a.dcm']);
    $expires = now()->addMinutes(DicomStorage::DOWNLOAD_LINK_MINUTES)->timestamp;

    $this->actingAs($instance->series->study->uploader)
        ->get(route('instances.file', $instance))
        ->assertRedirect("https://bucket.test/2024/03/a.dcm?expires={$expires}&type=application/dicom")
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('guests with an unlocked link are sent to a signed link too, and only after the checks pass', function () {
    $instance = DicomInstance::factory()->create(['file_path' => '2024/03/a.dcm']);
    $token = Str::random(GuestLinkIssuer::TOKEN_LENGTH);
    GuestLink::factory()->for($instance->series->study)->withToken($token)->create(['created_by' => $instance->series->study->uploaded_by]);

    $this->get(route('guest.instances.file', [$token, DicomInstance::factory()->create()]))->assertNotFound();

    $this->get(route('guest.instances.file', [$token, $instance]))
        ->assertRedirectContains('https://bucket.test/2024/03/a.dcm');
});

test('the upload page gives a signed link to upload each file straight to storage, in the uploader\'s own folder', function () {
    $technician = User::factory()->technician()->create();

    $target = Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('prepareUpload', 'slice.dcm', 2048)
        ->assertReturned(fn (array $target) => str_starts_with($target['url'], "https://bucket.test/incoming/{$technician->id}/"))
        ->effects['returns'][0];

    expect(app(DicomStorage::class)->isIncomingPathOf($target['path'], $technician->id))->toBeTrue()
        ->and($target['headers'])->toBe(['x-amz-meta-origin' => 'viewer']);
});

test('files over 100 MB or beyond the storage left in the package are refused before they are uploaded', function () {
    $technician = User::factory()->technician()->withLimits(['max_storage_mb' => 1])->create();

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('prepareUpload', 'huge.dcm', DicomImporter::MAX_FILE_SIZE + 1)
        ->assertReturned(null)
        ->call('prepareUpload', 'big.dcm', 2 * 1024 * 1024)
        ->assertReturned(null)
        ->assertSet('results.0.message', 'Each file must be 100 MB or smaller.')
        ->assertSet('results.1.status', 'failed');
});

test('a file uploaded straight to storage is imported and moved into place, leaving nothing in the incoming folder', function () {
    $technician = User::factory()->technician()->create();
    $path = app(DicomStorage::class)->incomingPathFor($technician->id);
    Storage::disk('dicom')->put($path, (new DicomFileBuilder)->build());

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importUpload', $path, 'slice.dcm')
        ->assertSee('Imported.');

    $instance = DicomInstance::sole();
    expect($instance->series->study->uploaded_by)->toBe($technician->id)
        ->and($instance->file_size)->toBe(strlen((new DicomFileBuilder)->build()));
    Storage::disk('dicom')->assertExists($instance->file_path);
    expect(Storage::disk('dicom')->allFiles(DicomStorage::INCOMING_DIRECTORY))->toBe([]);
});

test('a header longer than the part read at first is still imported, by reading the whole file', function () {
    $technician = User::factory()->technician()->create();
    $path = app(DicomStorage::class)->incomingPathFor($technician->id);
    $largePrivateElement = str_repeat("\0", DicomImporter::HEADER_BYTES + 1024);
    Storage::disk('dicom')->put($path, (new DicomFileBuilder)->with(0x0009, 0x1010, 'OB', $largePrivateElement)->build());

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importUpload', $path, 'slice.dcm')
        ->assertSee('Imported.');

    expect(DicomInstance::sole()->rows)->toBe(2);
});

test('an upload that is not DICOM, or never arrived, is reported and removed', function () {
    $technician = User::factory()->technician()->create();
    $storage = app(DicomStorage::class);
    $notDicom = $storage->incomingPathFor($technician->id);
    Storage::disk('dicom')->put($notDicom, 'just some text');

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importUpload', $notDicom, 'notes.txt')
        ->call('importUpload', $storage->incomingPathFor($technician->id), 'lost.dcm')
        ->assertSet('results.0.message', 'The file is not a DICOM Part 10 file.')
        ->assertSet('results.1.message', 'The upload did not finish. Please try this file again.');

    expect(Study::count())->toBe(0);
    Storage::disk('dicom')->assertMissing($notDicom);
});

test('nobody can import another person\'s upload or any other file on the disk', function (string $path) {
    $technician = User::factory()->technician()->create();
    $other = User::factory()->technician()->create();
    $path = str_replace('{other}', (string) $other->id, $path);
    Storage::disk('dicom')->put($path, (new DicomFileBuilder)->build());

    Livewire::actingAs($technician)
        ->test(UploadStudies::class)
        ->call('importUpload', $path, 'slice.dcm')
        ->assertForbidden();

    Storage::disk('dicom')->assertExists($path);
})->with([
    'someone else\'s upload' => ['incoming/{other}/0f8fad5b-d9cb-469f-a165-70867728950e.dcm'],
    'a stored study file' => ['users/{other}/2024/03/15/1.2/1.2.3/1.2.3.4.dcm'],
    'a path escaping the folder' => ['incoming/{other}/../../users/{other}/x.dcm'],
]);

test('without direct transfers the upload page does not hand out upload links', function () {
    Storage::fake('dicom');

    Livewire::actingAs(User::factory()->technician()->create())
        ->test(UploadStudies::class)
        ->call('prepareUpload', 'slice.dcm', 2048)
        ->assertNotFound();
});
