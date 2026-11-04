<?php

namespace App\Services;

use App\Models\Study;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dicom\StudyDeleter;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a user together with everything that belongs to them: the studies they uploaded (with their files, reports,
 * shares and guest links), every share they made or received, their patient records, their signature image and their
 * payment receipts. Reports they wrote on other people's studies are kept, without an author.
 */
class UserDeleter
{
    public function __construct(private StudySharing $sharing, private StudyDeleter $studies) {}

    /**
     * @return int The number of studies deleted with the user.
     */
    public function delete(User $user): int
    {
        $this->sharing->revokeInvolving($user);

        $studyCount = 0;

        $user->uploadedStudies()->lazyById()->each(function (Study $study) use (&$studyCount): void {
            $this->studies->delete($study);
            $studyCount++;
        });

        if ($user->signature_path !== null) {
            Storage::disk(User::SIGNATURE_DISK)->delete($user->signature_path);
        }

        Storage::disk(Subscription::RECEIPT_DISK)->delete(
            $user->subscriptions()->whereNotNull('receipt_path')->pluck('receipt_path')->all(),
        );

        $user->delete();

        return $studyCount;
    }
}
