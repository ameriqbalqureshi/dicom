<?php

namespace App\Services\Dicom;

use Carbon\CarbonImmutable;

/**
 * The header attributes of a DICOM file that the application stores.
 */
final readonly class DicomMetadata
{
    public function __construct(
        public string $transferSyntaxUid,
        public string $sopInstanceUid,
        public string $studyInstanceUid,
        public string $seriesInstanceUid,
        public ?string $sopClassUid = null,
        public ?string $patientName = null,
        public ?string $patientId = null,
        public ?CarbonImmutable $patientBirthDate = null,
        public ?string $patientSex = null,
        public ?CarbonImmutable $studyDate = null,
        public ?string $studyDescription = null,
        public ?string $accessionNumber = null,
        public ?string $modality = null,
        public ?int $seriesNumber = null,
        public ?string $seriesDescription = null,
        public ?int $instanceNumber = null,
        public ?int $rows = null,
        public ?int $columns = null,
        public int $numberOfFrames = 1,
    ) {}
}
