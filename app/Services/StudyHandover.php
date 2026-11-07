<?php

namespace App\Services;

use App\Enums\Quota;
use App\Models\DicomInstance;
use App\Models\Study;
use App\Models\User;
use App\Notifications\StudyReleased;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\QuotaExceededException;
use App\Services\Dicom\StudyDeleter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hands a study over when its owner gives it up, by deleting it or by a patient's study expiring. The physicians who can
 * open it are asked whether to keep it: the first to keep it becomes its owner, and the others keep their access. It is
 * deleted if nobody keeps it in time.
 */
class StudyHandover
{
    public function __construct(private StudyDeleter $deleter, private PackageLimits $limits) {}

    /**
     * Take the study away from its owner and ask the physicians who can open it whether to keep it, or delete it now
     * when there is nobody to ask.
     *
     * @return bool Whether the study now awaits handover; false when it was deleted.
     */
    public function release(Study $study): bool
    {
        $physicians = User::query()
            ->whereHas('receivedShares', fn ($query) => $query->accepted()->whereBelongsTo($study))
            ->get();

        if ($physicians->isEmpty()) {
            $this->deleter->delete($study);

            return false;
        }

        $expired = $study->isExpired();
        $formerOwnerId = $study->uploaded_by;
        $formerOwnerName = $study->uploader?->name;

        DB::transaction(function () use ($study, $formerOwnerId): void {
            $study->shares()->pending()->delete();

            if ($formerOwnerId !== null) {
                $study->guestLinks()->where('created_by', $formerOwnerId)->delete();
            }

            $this->instancesOf($study)->update(['uploaded_by' => null]);

            $study->update([
                'uploaded_by' => null,
                'released_by' => $formerOwnerId,
                'expires_at' => null,
                'handover_ends_at' => now()->addDays(config('studies.handover_days')),
            ]);
        });

        BestEffortNotifier::send($physicians, new StudyReleased($study, $formerOwnerName, $expired, $study->handover_ends_at));

        return true;
    }

    /**
     * Make a physician the owner of a study awaiting handover. It then counts against their package like a study they
     * uploaded, and the others who can open it keep their access, now as if shared by the new owner.
     *
     * @throws InvalidArgumentException
     * @throws QuotaExceededException
     */
    public function keep(Study $study, User $physician): void
    {
        DB::transaction(function () use ($study, $physician): void {
            $study = Study::query()->lockForUpdate()->findOrFail($study->id);

            if (! $study->isAwaitingHandover()) {
                throw new InvalidArgumentException('Another physician has already kept this study. You can still open it.');
            }

            if ($physician->uploadedStudies()->where('study_instance_uid', $study->study_instance_uid)->exists()) {
                throw new InvalidArgumentException('You already have your own copy of this study, so remove this one from your list instead.');
            }

            $this->limits->ensureStorageFor($physician, (int) $this->instancesOf($study)->sum('file_size'));
            $this->limits->spend($physician, Quota::Uploads);

            $study->shares()->whereBelongsTo($physician, 'recipient')->delete();

            if ($study->released_by !== null) {
                $study->shares()->where('shared_by', $study->released_by)->update(['shared_by' => $physician->id]);
            }

            $this->instancesOf($study)->update(['uploaded_by' => $physician->id]);

            $study->update([
                'uploaded_by' => $physician->id,
                'released_by' => null,
                'handover_ends_at' => null,
            ]);
        });
    }

    /**
     * Delete the studies nobody kept in time, or that nobody can open any more.
     *
     * @return int The number of studies deleted.
     */
    public function deleteAbandoned(): int
    {
        $count = 0;

        Study::query()->abandoned()->lazyById()->each(function (Study $study) use (&$count): void {
            $this->deleter->delete($study);
            $count++;
        });

        return $count;
    }

    /**
     * @return Builder<DicomInstance>
     */
    private function instancesOf(Study $study): Builder
    {
        return DicomInstance::query()->whereIn('series_id', $study->series()->select('id'));
    }
}
