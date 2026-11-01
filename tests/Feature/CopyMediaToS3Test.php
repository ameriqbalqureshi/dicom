<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->storagePath = sys_get_temp_dir().'/copy-media-'.uniqid();
    $this->app->useStoragePath($this->storagePath);
    Storage::fake('s3');

    File::ensureDirectoryExists("{$this->storagePath}/app/private/dicom/users/7");
    File::ensureDirectoryExists("{$this->storagePath}/app/private/signatures/3");
    File::ensureDirectoryExists("{$this->storagePath}/app/private/livewire-tmp");
    File::put("{$this->storagePath}/app/private/dicom/users/7/a.dcm", 'dicom-bytes');
    File::put("{$this->storagePath}/app/private/signatures/3/sig.png", 'png-bytes');
    File::put("{$this->storagePath}/app/private/livewire-tmp/upload.tmp", 'temporary');
});

afterEach(function () {
    File::deleteDirectory($this->storagePath);
});

test('dicom files and signatures are copied to the bucket at the same paths', function () {
    $this->artisan('media:copy-to-s3')->assertSuccessful();

    expect(Storage::disk('s3')->get('dicom/users/7/a.dcm'))->toBe('dicom-bytes')
        ->and(Storage::disk('s3')->get('signatures/3/sig.png'))->toBe('png-bytes')
        ->and(Storage::disk('s3')->allFiles())->toHaveCount(2)
        ->and(File::exists("{$this->storagePath}/app/private/dicom/users/7/a.dcm"))->toBeTrue();
});

test('files already in the bucket with the same size are skipped', function () {
    Storage::disk('s3')->put('dicom/users/7/a.dcm', 'other-bytes');

    $this->artisan('media:copy-to-s3')
        ->expectsOutputToContain('Copied 1 file(s), skipped 1 already on S3, 0 failed.')
        ->assertSuccessful();

    expect(Storage::disk('s3')->get('dicom/users/7/a.dcm'))->toBe('other-bytes');
});

test('a dry run does not copy anything', function () {
    $this->artisan('media:copy-to-s3', ['--dry-run' => true])->assertSuccessful();

    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});
