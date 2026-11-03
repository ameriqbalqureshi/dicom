<?php

namespace App\Http\Controllers;

use App\Models\DicomInstance;
use App\Services\Dicom\DicomStorage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DicomInstanceFileController extends Controller
{
    /**
     * Send the raw DICOM file of an instance to the browser viewer, straight from S3 when files are stored there.
     */
    public function __invoke(DicomInstance $instance, DicomStorage $storage): Response
    {
        Gate::authorize('view', $instance->series->study);

        return $storage->fileResponse($instance->file_path, "{$instance->sop_instance_uid}.dcm", [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
