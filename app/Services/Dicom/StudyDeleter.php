<?php

namespace App\Services\Dicom;

use App\Models\DicomArchive;
use App\Models\Study;

/**
 * Deletes a study with its series, instances, shares and guest links, and removes its files from disk.
 */
class StudyDeleter
{
    public function __construct(private DicomStorage $storage, private DicomArchives $archives) {}

    public function delete(Study $study): void
    {
        $paths = $study->instances()->whereNotNull('file_path')->pluck('file_path')->all();
        $archiveIds = $study->instances()->whereNotNull('dicom_archive_id')->distinct()->pluck('dicom_archive_id');

        $study->delete();
        $this->storage->delete($paths);

        DicomArchive::findMany($archiveIds)->each(fn (DicomArchive $archive) => $this->archives->deleteIfUnused($archive));
    }
}
