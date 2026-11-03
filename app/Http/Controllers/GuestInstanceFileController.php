<?php

namespace App\Http\Controllers;

use App\Models\DicomInstance;
use App\Models\GuestLink;
use App\Services\Dicom\DicomStorage;
use Symfony\Component\HttpFoundation\Response;

class GuestInstanceFileController extends Controller
{
    /**
     * Send the raw DICOM file of an instance to the viewer of someone holding a guest link to its study, straight from
     * S3 when files are stored there.
     */
    public function __invoke(string $token, DicomInstance $instance, DicomStorage $storage): Response
    {
        $link = GuestLink::findByToken($token);

        abort_unless($link?->isUsable() && $link->isUnlocked() && $instance->series->study_id === $link->study_id, 404);

        return $storage->fileResponse($instance->file_path, "{$instance->sop_instance_uid}.dcm", [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
