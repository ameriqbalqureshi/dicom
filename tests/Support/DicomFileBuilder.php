<?php

namespace Tests\Support;

use App\Services\Dicom\DicomParser;

/**
 * Builds small but valid DICOM Part 10 files for tests.
 */
final class DicomFileBuilder
{
    /**
     * @var array<string, array{vr: string, value: string}>
     */
    private array $elements = [];

    private bool $includesUndefinedLengthSequence = false;

    public function __construct(private string $transferSyntaxUid = DicomParser::EXPLICIT_VR_LITTLE_ENDIAN)
    {
        $this
            ->with(0x0008, 0x0016, 'UI', '1.2.840.10008.5.1.4.1.1.2')
            ->with(0x0008, 0x0018, 'UI', '1.2.826.0.1.3680043.2.1.1')
            ->with(0x0008, 0x0020, 'DA', '20240315')
            ->with(0x0008, 0x0050, 'SH', 'ACC123')
            ->with(0x0008, 0x0060, 'CS', 'CT')
            ->with(0x0008, 0x1030, 'LO', 'CT Chest')
            ->with(0x0008, 0x103E, 'LO', 'Axial 5mm')
            ->with(0x0010, 0x0010, 'PN', 'DOE^JANE')
            ->with(0x0010, 0x0020, 'LO', 'PID001')
            ->with(0x0010, 0x0030, 'DA', '19800102')
            ->with(0x0010, 0x0040, 'CS', 'F')
            ->with(0x0020, 0x000D, 'UI', '1.2.826.0.1.3680043.2.1')
            ->with(0x0020, 0x000E, 'UI', '1.2.826.0.1.3680043.2.1.1.1')
            ->with(0x0020, 0x0011, 'IS', '3')
            ->with(0x0020, 0x0013, 'IS', '7')
            ->withUnsignedShort(0x0028, 0x0002, 1)
            ->with(0x0028, 0x0004, 'CS', 'MONOCHROME2')
            ->withUnsignedShort(0x0028, 0x0010, 2)
            ->withUnsignedShort(0x0028, 0x0011, 2)
            ->withUnsignedShort(0x0028, 0x0100, 16)
            ->withUnsignedShort(0x0028, 0x0101, 16)
            ->withUnsignedShort(0x0028, 0x0102, 15)
            ->withUnsignedShort(0x0028, 0x0103, 0)
            ->withBinary(0x7FE0, 0x0010, 'OW', $this->pack16(0, 100, 200, 300));
    }

    public function with(int $group, int $element, string $vr, string $value): self
    {
        $padding = $vr === 'UI' ? "\0" : ' ';
        $value = strlen($value) % 2 === 0 ? $value : $value.$padding;

        return $this->withBinary($group, $element, $vr, $value);
    }

    public function withUnsignedShort(int $group, int $element, int $value): self
    {
        return $this->withBinary($group, $element, 'US', $this->pack16($value));
    }

    public function without(int $group, int $element): self
    {
        unset($this->elements[$this->key($group, $element)]);

        return $this;
    }

    /**
     * Add a Referenced Image Sequence with undefined lengths that the parser must skip.
     */
    public function withUndefinedLengthSequence(): self
    {
        $this->includesUndefinedLengthSequence = true;

        return $this;
    }

    public function build(): string
    {
        $meta = $this->encodeExplicitLittleEndian(0x0002, 0x0001, 'OB', "\0\1")
            .$this->encodeExplicitLittleEndian(0x0002, 0x0002, 'UI', $this->pad($this->elements['00080016']['value'] ?? '1.2', "\0"))
            .$this->encodeExplicitLittleEndian(0x0002, 0x0003, 'UI', $this->pad($this->elements['00080018']['value'] ?? '1.2', "\0"))
            .$this->encodeExplicitLittleEndian(0x0002, 0x0010, 'UI', $this->pad($this->transferSyntaxUid, "\0"));

        $groupLength = $this->encodeExplicitLittleEndian(0x0002, 0x0000, 'UL', pack('V', strlen($meta)));

        $dataset = '';
        $elements = $this->elements;
        ksort($elements);
        $sequencePending = $this->includesUndefinedLengthSequence;

        foreach ($elements as $key => $element) {
            if ($sequencePending && $key > '00081140') {
                $dataset .= $this->undefinedLengthSequence();
                $sequencePending = false;
            }

            $dataset .= $this->encode(hexdec(substr($key, 0, 4)), hexdec(substr($key, 4)), $element['vr'], $element['value']);
        }

        if ($this->transferSyntaxUid === DicomParser::DEFLATED_EXPLICIT_VR_LITTLE_ENDIAN) {
            $dataset = gzdeflate($dataset);
        }

        return str_repeat("\0", 128).'DICM'.$groupLength.$meta.$dataset;
    }

    public function writeTo(string $path): string
    {
        file_put_contents($path, $this->build());

        return $path;
    }

    private function withBinary(int $group, int $element, string $vr, string $value): self
    {
        $this->elements[$this->key($group, $element)] = ['vr' => $vr, 'value' => $value];

        return $this;
    }

    private function undefinedLengthSequence(): string
    {
        $nested = $this->encode(0x0008, 0x1150, 'UI', $this->pad('1.2.840.10008.5.1.4.1.1.2', "\0"))
            .$this->encode(0x0008, 0x1155, 'UI', $this->pad('1.2.3.4.5.6.7', "\0"));

        return $this->sequenceMarker()
            .$this->itemTag(0xE000, 0xFFFFFFFF).$nested.$this->itemTag(0xE00D, 0)
            .$this->itemTag(0xE000, strlen($nested)).$nested
            .$this->itemTag(0xE0DD, 0);
    }

    private function sequenceMarker(): string
    {
        $tag = $this->tag(0x0008, 0x1140);

        if ($this->isImplicit()) {
            return $tag.$this->pack32(0xFFFFFFFF);
        }

        return $tag.'SQ'."\0\0".$this->pack32(0xFFFFFFFF);
    }

    private function itemTag(int $element, int $length): string
    {
        return $this->tag(0xFFFE, $element).$this->pack32($length);
    }

    private function encode(int $group, int $element, string $vr, string $value): string
    {
        if ($this->isImplicit()) {
            return $this->tag($group, $element).$this->pack32(strlen($value)).$value;
        }

        if (in_array($vr, ['OB', 'OW', 'SQ', 'UN', 'UT'], true)) {
            return $this->tag($group, $element).$vr."\0\0".$this->pack32(strlen($value)).$value;
        }

        return $this->tag($group, $element).$vr.$this->pack16(strlen($value)).$value;
    }

    private function encodeExplicitLittleEndian(int $group, int $element, string $vr, string $value): string
    {
        $header = pack('v2', $group, $element).$vr;

        return $vr === 'OB'
            ? $header."\0\0".pack('V', strlen($value)).$value
            : $header.pack('v', strlen($value)).$value;
    }

    private function tag(int $group, int $element): string
    {
        return pack($this->isBigEndian() ? 'n2' : 'v2', $group, $element);
    }

    private function pack16(int ...$values): string
    {
        return pack(($this->isBigEndian() ? 'n' : 'v').'*', ...$values);
    }

    private function pack32(int $value): string
    {
        return pack($this->isBigEndian() ? 'N' : 'V', $value);
    }

    private function pad(string $value, string $padding): string
    {
        return strlen($value) % 2 === 0 ? $value : $value.$padding;
    }

    private function key(int $group, int $element): string
    {
        return sprintf('%04X%04X', $group, $element);
    }

    private function isImplicit(): bool
    {
        return $this->transferSyntaxUid === DicomParser::IMPLICIT_VR_LITTLE_ENDIAN;
    }

    private function isBigEndian(): bool
    {
        return $this->transferSyntaxUid === DicomParser::EXPLICIT_VR_BIG_ENDIAN;
    }
}
