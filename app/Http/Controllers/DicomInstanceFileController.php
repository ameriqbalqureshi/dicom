<?php

namespace App\Http\Controllers;

use App\Models\DicomInstance;
use App\Services\Dicom\DicomImporter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DicomInstanceFileController extends Controller
{
    /**
     * Stream the raw DICOM file of an instance to the browser viewer.
     */
    public function __invoke(DicomInstance $instance): StreamedResponse
    {
        Gate::authorize('view', $instance->series->study);

        $disk = Storage::disk(DicomImporter::DISK);

        abort_unless($disk->exists($instance->file_path), 404);

        return $disk->response($instance->file_path, "{$instance->sop_instance_uid}.dcm", [
            'Content-Type' => 'application/dicom',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
