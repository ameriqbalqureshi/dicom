<?php

namespace App\Services\Dicom;

use Carbon\CarbonImmutable;

/**
 * Reads the header attributes of a DICOM Part 10 file without loading its pixel data.
 */
class DicomParser
{
    public const IMPLICIT_VR_LITTLE_ENDIAN = '1.2.840.10008.1.2';

    public const EXPLICIT_VR_LITTLE_ENDIAN = '1.2.840.10008.1.2.1';

    public const DEFLATED_EXPLICIT_VR_LITTLE_ENDIAN = '1.2.840.10008.1.2.1.99';

    public const EXPLICIT_VR_BIG_ENDIAN = '1.2.840.10008.1.2.2';

    private const UNDEFINED_LENGTH = 0xFFFFFFFF;

    private const ITEM = 'FFFEE000';

    private const ITEM_DELIMITATION = 'FFFEE00D';

    private const SEQUENCE_DELIMITATION = 'FFFEE0DD';

    /**
     * Group number after which no attribute of interest can appear, so parsing stops before the pixel data.
     */
    private const LAST_GROUP_OF_INTEREST = 0x0028;

    /**
     * Value lengths above this are never one of the short attributes we extract.
     */
    private const MAX_VALUE_LENGTH = 1024;

    /**
     * Explicit VRs that use a 2-byte reserved field followed by a 4-byte length.
     */
    private const LONG_LENGTH_VRS = ['OB', 'OD', 'OF', 'OL', 'OV', 'OW', 'SQ', 'SV', 'UC', 'UN', 'UR', 'UT', 'UV'];

    /**
     * @var array<string, string>
     */
    private const ATTRIBUTES = [
        '00020010' => 'transferSyntaxUid',
        '00080016' => 'sopClassUid',
        '00080018' => 'sopInstanceUid',
        '00080020' => 'studyDate',
        '00080050' => 'accessionNumber',
        '00080060' => 'modality',
        '00081030' => 'studyDescription',
        '0008103E' => 'seriesDescription',
        '00100010' => 'patientName',
        '00100020' => 'patientId',
        '00100030' => 'patientBirthDate',
        '00100040' => 'patientSex',
        '0020000D' => 'studyInstanceUid',
        '0020000E' => 'seriesInstanceUid',
        '00200011' => 'seriesNumber',
        '00200013' => 'instanceNumber',
        '00280008' => 'numberOfFrames',
        '00280010' => 'rows',
        '00280011' => 'columns',
    ];

    /**
     * Attributes stored as binary unsigned shorts (VR US).
     */
    private const UNSIGNED_SHORT_ATTRIBUTES = ['rows', 'columns'];

    /**
     * The longest value each attribute may have in the database, where this is not 255 characters.
     *
     * @var array<string, int>
     */
    private const VALUE_LIMITS = [
        'transferSyntaxUid' => 64,
        'sopClassUid' => 64,
        'sopInstanceUid' => 64,
        'studyInstanceUid' => 64,
        'seriesInstanceUid' => 64,
        'accessionNumber' => 64,
        'patientId' => 64,
        'patientSex' => 16,
        'modality' => 16,
    ];

    /**
     * The most frames an attribute from outside may claim, since the viewer lists every frame of a study.
     */
    private const MAX_FRAMES = 65535;

    /** @var resource */
    private $stream;

    private bool $littleEndian = true;

    private bool $explicitVr = true;

    private bool $isPartial = false;

    /**
     * Read the header of a DICOM file. Pass $isPartial when the file holds only the first part of a DICOM file: running
     * out of data before the end of the attributes of interest is then an error rather than the end of the header.
     *
     * @throws InvalidDicomFileException
     */
    public function parse(string $path, bool $isPartial = false): DicomMetadata
    {
        $this->isPartial = $isPartial;

        $stream = @fopen($path, 'rb');

        if ($stream === false) {
            throw new InvalidDicomFileException('The file could not be opened.');
        }

        try {
            return $this->parseStream($stream);
        } finally {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
        }
    }

    /**
     * Build metadata from header attributes a browser read out of a zip, keyed as in ATTRIBUTES, with the same clean-up
     * as a parsed file. They arrive from outside, so anything that would not fit the database is dropped or refused.
     *
     * @param  array<array-key, mixed>  $attributes
     *
     * @throws InvalidDicomFileException
     */
    public function metadataFromAttributes(array $attributes): DicomMetadata
    {
        $values = [];

        foreach (self::ATTRIBUTES as $name) {
            $value = $attributes[$name] ?? null;

            if (in_array($name, self::UNSIGNED_SHORT_ATTRIBUTES, true)) {
                $values[$name] = is_int($value) && $value >= 0 && $value <= 0xFFFF ? $value : null;
            } elseif (is_string($value)) {
                $values[$name] = mb_substr(trim($value, " \0"), 0, self::VALUE_LIMITS[$name] ?? 255);
            }
        }

        foreach (['seriesNumber', 'instanceNumber', 'numberOfFrames'] as $name) {
            if (preg_match('/^\d{1,9}(\\\\|$)/', trim($values[$name] ?? '')) !== 1) {
                unset($values[$name]);
            }
        }

        foreach (['transferSyntaxUid', 'sopInstanceUid', 'studyInstanceUid', 'seriesInstanceUid'] as $uid) {
            if (strlen($attributes[$uid] ?? '') > self::VALUE_LIMITS[$uid]) {
                throw new InvalidDicomFileException("The file's {$uid} attribute is too long.");
            }
        }

        if (blank($values['transferSyntaxUid'] ?? null)) {
            throw new InvalidDicomFileException('The file meta information has no transfer syntax.');
        }

        if ((int) ($values['numberOfFrames'] ?? 1) > self::MAX_FRAMES) {
            throw new InvalidDicomFileException('The file has too many frames.');
        }

        return $this->buildMetadata($values);
    }

    /**
     * @param  resource  $stream
     */
    private function parseStream($stream): DicomMetadata
    {
        $this->stream = $stream;
        $this->littleEndian = true;
        $this->explicitVr = true;

        if (fseek($this->stream, 128) !== 0 || fread($this->stream, 4) !== 'DICM') {
            throw new InvalidDicomFileException('The file is not a DICOM Part 10 file.');
        }

        $values = $this->readFileMetaInformation();
        $transferSyntaxUid = $values['transferSyntaxUid'] ?? null;

        if ($transferSyntaxUid === null) {
            throw new InvalidDicomFileException('The file meta information has no transfer syntax.');
        }

        $this->useTransferSyntax($transferSyntaxUid);

        return $this->buildMetadata([...$this->readDataset(), 'transferSyntaxUid' => $transferSyntaxUid]);
    }

    /**
     * Group 0002 is always encoded as explicit VR little endian.
     *
     * @return array<string, string|int>
     */
    private function readFileMetaInformation(): array
    {
        $values = [];

        while (true) {
            $groupBytes = fread($this->stream, 2);

            if (strlen($groupBytes) < 2 || unpack('v', $groupBytes)[1] !== 0x0002) {
                fseek($this->stream, -strlen($groupBytes), SEEK_CUR);

                return $values;
            }

            fseek($this->stream, -2, SEEK_CUR);
            $this->readElementInto($values);
        }
    }

    private function useTransferSyntax(string $transferSyntaxUid): void
    {
        match ($transferSyntaxUid) {
            self::IMPLICIT_VR_LITTLE_ENDIAN => $this->explicitVr = false,
            self::EXPLICIT_VR_BIG_ENDIAN => $this->littleEndian = false,
            self::DEFLATED_EXPLICIT_VR_LITTLE_ENDIAN => $this->inflateRemainingStream(),
            default => null,
        };
    }

    private function inflateRemainingStream(): void
    {
        $inflated = @gzinflate((string) stream_get_contents($this->stream));

        if ($inflated === false) {
            throw new InvalidDicomFileException('The deflated dataset could not be decompressed.');
        }

        fclose($this->stream);
        $this->stream = fopen('php://memory', 'r+b');
        fwrite($this->stream, $inflated);
        rewind($this->stream);
    }

    /**
     * @return array<string, string|int>
     */
    private function readDataset(): array
    {
        $values = [];

        while ($this->readElementInto($values)) {
            //
        }

        return $values;
    }

    /**
     * Read the next element, storing its value when it is an attribute of interest.
     *
     * @param  array<string, string|int>  $values
     * @return bool False once the end of the stream or the last group of interest has been reached.
     */
    private function readElementInto(array &$values): bool
    {
        $header = $this->readElementHeader();

        if ($header === null && $this->isPartial) {
            throw new InvalidDicomFileException('The header extends beyond the part of the file that was read.');
        }

        if ($header === null || $header['group'] > self::LAST_GROUP_OF_INTEREST) {
            return false;
        }

        if ($header['length'] === self::UNDEFINED_LENGTH) {
            $this->skipUndefinedLengthSequence();

            return true;
        }

        $attribute = self::ATTRIBUTES[$header['tag']] ?? null;

        if ($attribute === null || $header['length'] > self::MAX_VALUE_LENGTH) {
            $this->skip($header['length']);

            return true;
        }

        $raw = $this->read($header['length']);

        $values[$attribute] = in_array($attribute, self::UNSIGNED_SHORT_ATTRIBUTES, true)
            ? $this->decodeUnsignedShort($raw)
            : $this->decodeString($raw);

        return true;
    }

    /**
     * @return array{tag: string, group: int, length: int}|null
     */
    private function readElementHeader(): ?array
    {
        $tagBytes = fread($this->stream, 4);

        if ($tagBytes === '' || $tagBytes === false) {
            return null;
        }

        if (strlen($tagBytes) < 4) {
            throw new InvalidDicomFileException('The file ends in the middle of an element.');
        }

        $format = $this->littleEndian ? 'v2' : 'n2';
        [1 => $group, 2 => $element] = unpack($format, $tagBytes);
        $tag = sprintf('%04X%04X', $group, $element);

        $isFileMeta = $group === 0x0002;
        $isItemTag = $group === 0xFFFE;

        if ($isItemTag || (! $this->explicitVr && ! $isFileMeta)) {
            return ['tag' => $tag, 'group' => $group, 'length' => $this->readUnsignedLong($isFileMeta)];
        }

        $vr = $this->read(2);

        if (in_array($vr, self::LONG_LENGTH_VRS, true)) {
            $this->skip(2);
            $length = $this->readUnsignedLong($isFileMeta);
        } else {
            $length = $this->readUnsignedShort($isFileMeta);
        }

        return ['tag' => $tag, 'group' => $group, 'length' => $length];
    }

    private function skipUndefinedLengthSequence(): void
    {
        while (true) {
            $header = $this->readElementHeader() ?? throw new InvalidDicomFileException('A sequence is not terminated.');

            if ($header['tag'] === self::SEQUENCE_DELIMITATION) {
                return;
            }

            if ($header['tag'] !== self::ITEM) {
                throw new InvalidDicomFileException('A sequence contains an unexpected element.');
            }

            if ($header['length'] === self::UNDEFINED_LENGTH) {
                $this->skipUndefinedLengthItem();
            } else {
                $this->skip($header['length']);
            }
        }
    }

    private function skipUndefinedLengthItem(): void
    {
        while (true) {
            $header = $this->readElementHeader() ?? throw new InvalidDicomFileException('A sequence item is not terminated.');

            if ($header['tag'] === self::ITEM_DELIMITATION) {
                return;
            }

            if ($header['length'] === self::UNDEFINED_LENGTH) {
                $this->skipUndefinedLengthSequence();
            } else {
                $this->skip($header['length']);
            }
        }
    }

    private function read(int $length): string
    {
        if ($length === 0) {
            return '';
        }

        $bytes = fread($this->stream, $length);

        if ($bytes === false || strlen($bytes) < $length) {
            throw new InvalidDicomFileException('The file is truncated.');
        }

        return $bytes;
    }

    private function skip(int $length): void
    {
        if ($length > 0) {
            fseek($this->stream, $length, SEEK_CUR);
        }
    }

    private function readUnsignedShort(bool $forceLittleEndian = false): int
    {
        return unpack($forceLittleEndian || $this->littleEndian ? 'v' : 'n', $this->read(2))[1];
    }

    private function readUnsignedLong(bool $forceLittleEndian = false): int
    {
        return unpack($forceLittleEndian || $this->littleEndian ? 'V' : 'N', $this->read(4))[1];
    }

    private function decodeUnsignedShort(string $raw): ?int
    {
        if (strlen($raw) < 2) {
            return null;
        }

        return unpack($this->littleEndian ? 'v' : 'n', $raw)[1];
    }

    private function decodeString(string $raw): string
    {
        $value = trim($raw, " \0");

        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * @param  array<string, string|int>  $values
     */
    private function buildMetadata(array $values): DicomMetadata
    {
        foreach (['sopInstanceUid', 'studyInstanceUid', 'seriesInstanceUid'] as $required) {
            if (blank($values[$required] ?? null)) {
                throw new InvalidDicomFileException("The file is missing the required {$required} attribute.");
            }
        }

        return new DicomMetadata(
            transferSyntaxUid: $values['transferSyntaxUid'],
            sopInstanceUid: $values['sopInstanceUid'],
            studyInstanceUid: $values['studyInstanceUid'],
            seriesInstanceUid: $values['seriesInstanceUid'],
            sopClassUid: $this->nullableString($values['sopClassUid'] ?? null),
            patientName: $this->personName($values['patientName'] ?? null),
            patientId: $this->nullableString($values['patientId'] ?? null),
            patientBirthDate: $this->date($values['patientBirthDate'] ?? null),
            patientSex: $this->nullableString($values['patientSex'] ?? null),
            studyDate: $this->date($values['studyDate'] ?? null),
            studyDescription: $this->nullableString($values['studyDescription'] ?? null),
            accessionNumber: $this->nullableString($values['accessionNumber'] ?? null),
            modality: $this->nullableString($values['modality'] ?? null),
            seriesNumber: $this->integerString($values['seriesNumber'] ?? null),
            seriesDescription: $this->nullableString($values['seriesDescription'] ?? null),
            instanceNumber: $this->integerString($values['instanceNumber'] ?? null),
            rows: $values['rows'] ?? null,
            columns: $values['columns'] ?? null,
            numberOfFrames: max(1, $this->integerString($values['numberOfFrames'] ?? null) ?? 1),
        );
    }

    private function nullableString(?string $value): ?string
    {
        return blank($value) ? null : $value;
    }

    /**
     * Convert a DICOM person name such as "DOE^JOHN" to "DOE JOHN", keeping only the alphabetic representation.
     */
    private function personName(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $alphabetic = explode('=', $value)[0];

        return $this->nullableString(trim((string) preg_replace('/\s+/', ' ', str_replace('^', ' ', $alphabetic))));
    }

    /**
     * Read the first value of an integer string (VR IS), which may hold several backslash-separated values.
     */
    private function integerString(?string $value): ?int
    {
        $first = trim(explode('\\', (string) $value)[0]);

        return is_numeric($first) ? (int) $first : null;
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{8}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Ymd', $value);

        return $date !== false && $date->format('Ymd') === $value ? $date : null;
    }
}
