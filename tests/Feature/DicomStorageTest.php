<?php

use App\Models\DicomInstance;
use App\Models\Series;
use App\Models\Study;
use App\Services\Dicom\DicomStorage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('dicom');
});

test('files are laid out by uploader, study date, study, series and instance', function () {
    $path = app(DicomStorage::class)->pathFor(7, '1.2.3', '1.2.3.4', '1.2.3.4.5', CarbonImmutable::parse('2024-03-15'));

    expect($path)->toBe('users/7/2024/03/15/1.2.3/1.2.3.4/1.2.3.4.5.dcm');
});

test('studies without an uploader or date are stored under unowned and undated', function () {
    expect(app(DicomStorage::class)->pathFor(null, '1.2.3', '1.2.3.4', '1.2.3.4.5', null))
        ->toBe('unowned/undated/1.2.3/1.2.3.4/1.2.3.4.5.dcm');
});

test('malformed uids cannot add path segments', function () {
    $path = app(DicomStorage::class)->pathFor(7, '../../etc', '1.2', '1.2.3', null);

    expect($path)->toBe('users/7/undated/'.sha1('../../etc').'/1.2/1.2.3.dcm');
});

test('deleting files removes the directories they leave empty', function () {
    $disk = Storage::disk('dicom');
    $disk->put('2024/03/15/1.2/1.2.1/a.dcm', 'a');
    $disk->put('2024/03/15/1.3/1.3.1/b.dcm', 'b');

    app(DicomStorage::class)->delete(['2024/03/15/1.2/1.2.1/a.dcm']);

    expect($disk->directoryExists('2024/03/15/1.2'))->toBeFalse()
        ->and($disk->exists('2024/03/15/1.3/1.3.1/b.dcm'))->toBeTrue();
});

test('the reorganize command moves existing files into the study layout', function () {
    $study = Study::factory()->create(['study_instance_uid' => '1.2.9', 'study_date' => '2024-03-15']);
    $series = Series::factory()->for($study)->create(['series_instance_uid' => '1.2.9.1']);
    $instance = DicomInstance::factory()->for($series)->create(['sop_instance_uid' => '1.2.9.1.1', 'file_path' => '2026/10/old.dcm']);
    Storage::disk('dicom')->put('2026/10/old.dcm', 'dicom');

    $this->artisan('dicom:reorganize')->assertSuccessful();

    expect($instance->fresh()->file_path)->toBe("users/{$study->uploaded_by}/2024/03/15/1.2.9/1.2.9.1/1.2.9.1.1.dcm");
    Storage::disk('dicom')->assertExists($instance->fresh()->file_path);
    expect(Storage::disk('dicom')->directoryExists('2026'))->toBeFalse();
});

test('the reorganize command reports instances whose file is missing', function () {
    $instance = DicomInstance::factory()->create(['file_path' => 'missing.dcm']);

    $this->artisan('dicom:reorganize')->assertFailed();

    expect($instance->fresh()->file_path)->toBe('missing.dcm');
});

test('a dry run does not move anything', function () {
    $instance = DicomInstance::factory()->create(['file_path' => 'old.dcm']);
    Storage::disk('dicom')->put('old.dcm', 'dicom');

    $this->artisan('dicom:reorganize', ['--dry-run' => true])->assertSuccessful();

    expect($instance->fresh()->file_path)->toBe('old.dcm');
    Storage::disk('dicom')->assertExists('old.dcm');
});
