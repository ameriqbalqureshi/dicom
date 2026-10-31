<?php

use App\Services\Dicom\DicomMetadata;
use App\Services\Dicom\DicomParser;
use App\Services\Dicom\InvalidDicomFileException;
use Tests\Support\DicomFileBuilder;

function parseDicom(DicomFileBuilder|string $file): DicomMetadata
{
    $path = tempnam(sys_get_temp_dir(), 'dcm');
    file_put_contents($path, $file instanceof DicomFileBuilder ? $file->build() : $file);

    try {
        return (new DicomParser)->parse($path);
    } finally {
        unlink($path);
    }
}

test('it reads study, series and instance attributes', function (string $transferSyntaxUid) {
    $metadata = parseDicom(new DicomFileBuilder($transferSyntaxUid));

    expect($metadata->transferSyntaxUid)->toBe($transferSyntaxUid)
        ->and($metadata->sopClassUid)->toBe('1.2.840.10008.5.1.4.1.1.2')
        ->and($metadata->sopInstanceUid)->toBe('1.2.826.0.1.3680043.2.1.1')
        ->and($metadata->studyInstanceUid)->toBe('1.2.826.0.1.3680043.2.1')
        ->and($metadata->seriesInstanceUid)->toBe('1.2.826.0.1.3680043.2.1.1.1')
        ->and($metadata->patientName)->toBe('DOE JANE')
        ->and($metadata->patientId)->toBe('PID001')
        ->and($metadata->patientBirthDate?->toDateString())->toBe('1980-01-02')
        ->and($metadata->patientSex)->toBe('F')
        ->and($metadata->studyDate?->toDateString())->toBe('2024-03-15')
        ->and($metadata->studyDescription)->toBe('CT Chest')
        ->and($metadata->accessionNumber)->toBe('ACC123')
        ->and($metadata->modality)->toBe('CT')
        ->and($metadata->seriesNumber)->toBe(3)
        ->and($metadata->seriesDescription)->toBe('Axial 5mm')
        ->and($metadata->instanceNumber)->toBe(7)
        ->and($metadata->rows)->toBe(2)
        ->and($metadata->columns)->toBe(2)
        ->and($metadata->numberOfFrames)->toBe(1);
})->with([
    'explicit VR little endian' => DicomParser::EXPLICIT_VR_LITTLE_ENDIAN,
    'implicit VR little endian' => DicomParser::IMPLICIT_VR_LITTLE_ENDIAN,
    'explicit VR big endian' => DicomParser::EXPLICIT_VR_BIG_ENDIAN,
    'deflated explicit VR little endian' => DicomParser::DEFLATED_EXPLICIT_VR_LITTLE_ENDIAN,
]);

test('it skips sequences of undefined length', function (string $transferSyntaxUid) {
    $metadata = parseDicom((new DicomFileBuilder($transferSyntaxUid))->withUndefinedLengthSequence());

    expect($metadata->seriesDescription)->toBe('Axial 5mm')
        ->and($metadata->patientName)->toBe('DOE JANE');
})->with([
    'explicit VR' => DicomParser::EXPLICIT_VR_LITTLE_ENDIAN,
    'implicit VR' => DicomParser::IMPLICIT_VR_LITTLE_ENDIAN,
]);

test('it reads the frame count of multi-frame instances', function () {
    $metadata = parseDicom((new DicomFileBuilder)->with(0x0028, 0x0008, 'IS', '24'));

    expect($metadata->numberOfFrames)->toBe(24);
});

test('it keeps only the alphabetic part of a person name', function () {
    $metadata = parseDicom((new DicomFileBuilder)->with(0x0010, 0x0010, 'PN', 'YAMADA^TARO==ideographic'));

    expect($metadata->patientName)->toBe('YAMADA TARO');
});

test('it ignores invalid dates', function () {
    $metadata = parseDicom((new DicomFileBuilder)->with(0x0008, 0x0020, 'DA', '20241345'));

    expect($metadata->studyDate)->toBeNull();
});

test('it rejects files without the DICM prefix', function () {
    parseDicom(str_repeat('x', 200));
})->throws(InvalidDicomFileException::class, 'not a DICOM Part 10 file');

test('it rejects files missing a required UID', function () {
    parseDicom((new DicomFileBuilder)->without(0x0020, 0x000E));
})->throws(InvalidDicomFileException::class, 'seriesInstanceUid');

test('it rejects truncated files', function () {
    $bytes = (new DicomFileBuilder)->build();

    parseDicom(substr($bytes, 0, 300));
})->throws(InvalidDicomFileException::class);
