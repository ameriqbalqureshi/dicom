<?php

namespace App\Services\Dicom;

use App\Models\Study;

/**
 * Deletes a study with its series, instances, shares and guest links, and removes its files from disk.
 */
class StudyDeleter
{
    public function __construct(private DicomStorage $storage) {}

    public function delete(Study $study): void
    {
        $paths = $study->instances()->pluck('file_path')->all();

        $study->delete();
        $this->storage->delete($paths);
    }
}
