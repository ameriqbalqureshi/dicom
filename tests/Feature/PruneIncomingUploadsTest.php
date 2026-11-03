<?php

use App\Console\Commands\PruneIncomingUploads;
use Illuminate\Support\Facades\Storage;

test('it deletes uploads abandoned in the incoming folder for a day, and nothing else', function () {
    $disk = Storage::fake('dicom');
    $disk->put('incoming/5/abandoned.dcm', 'old');
    $disk->put('incoming/5/in-progress.dcm', 'new');
    $disk->put('users/5/2024/03/15/1.2/1.2.3/1.2.3.4.dcm', 'stored');
    touch($disk->path('incoming/5/abandoned.dcm'), now()->subHours(PruneIncomingUploads::MAX_AGE_HOURS + 1)->getTimestamp());
    touch($disk->path('users/5/2024/03/15/1.2/1.2.3/1.2.3.4.dcm'), now()->subYear()->getTimestamp());

    $this->artisan('uploads:prune-incoming')
        ->expectsOutput('Deleted 1 abandoned upload.')
        ->assertSuccessful();

    $disk->assertMissing('incoming/5/abandoned.dcm');
    $disk->assertExists('incoming/5/in-progress.dcm');
    $disk->assertExists('users/5/2024/03/15/1.2/1.2.3/1.2.3.4.dcm');
});
