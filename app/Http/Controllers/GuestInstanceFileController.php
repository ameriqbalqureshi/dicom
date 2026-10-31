<?php

namespace App\Http\Controllers;

use App\Models\DicomInstance;
use App\Models\GuestLink;
use App\Services\Dicom\DicomImporter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestInstanceFileController extends Controller
{
    /**
     * Stream the raw DICOM file of an instance to the viewer of someone holding a guest link to its study.
     */
    public function __invoke(string $token, DicomInstance $instance): StreamedResponse
    {
        $link = GuestLink::findByToken($token);

        abort_unless($link?->isUsable() && $link->isUnlocked() && $instance->series->study_id === $link->study_id, 404);

        $disk = Storage::disk(DicomImporter::DISK);

        abort_unless($disk->exists($instance->file_path), 404);

        return $disk->response($instance->file_path, "{$instance->sop_instance_uid}.dcm", [
            'Content-Type' => 'application/dicom',
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
