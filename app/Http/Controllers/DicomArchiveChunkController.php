<?php

namespace App\Http\Controllers;

use App\Models\DicomArchive;
use App\Services\Dicom\DicomArchives;
use App\Services\Dicom\DicomStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DicomArchiveChunkController extends Controller
{
    /**
     * Add the next chunk of a zip being uploaded, on installations where files are not stored on S3. (On S3 the browser
     * sends the whole zip straight to the bucket.)
     */
    public function __invoke(Request $request, DicomArchive $archive, DicomStorage $storage): Response
    {
        abort_if($storage->supportsDirectUploads(), 404);
        abort_unless($archive->uploaded_by === $request->user()->id && $archive->uploaded_at === null, 403);

        $disk = $storage->disk();
        $offset = $request->integer('offset');

        abort_unless($offset === ($disk->exists($archive->file_path) ? $disk->size($archive->file_path) : 0), 409);

        $chunk = $request->getContent();

        abort_if(strlen($chunk) > DicomArchives::CHUNK_SIZE || $offset + strlen($chunk) > $archive->file_size, 413);

        $disk->makeDirectory(dirname($archive->file_path));
        file_put_contents($disk->path($archive->file_path), $chunk, $offset === 0 ? 0 : FILE_APPEND);

        return response()->noContent();
    }
}
