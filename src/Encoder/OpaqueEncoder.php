<?php

declare(strict_types=1);

namespace AlAminFirdows\LaravelOpaqueId\Encoder;

/**
 * Class OpaqueEncoder
 *
 * Provides a reversible, obfuscated mapping between 32-bit integers and
 * encoded strings (hex or URL-safe base64). Useful for hiding raw IDs
 * in URLs or APIs without heavy encryption.
 *
 * Inspired by the original implementation by Marek Z. (c) 2011.
 */
final class OpaqueEncoder
{
    public const ENCODING_INT = 0;
    public const ENCODING_HEX = 1;
    public const ENCODING_BASE64 = 2;

    private const EXTRA_CHARS = '.-';

    private int $key;
    private int $encoding;

    public function __construct(int $key, int $encoding = self::ENCODING_HEX)
    {
        $this->key = $key;
        $this->encoding = $encoding;
    }

    /**
     * Encodes an integer into its obfuscated form based on the selected encoding mode.
     */
    public function encode(int $value): int|string
    {
        return match ($this->encoding) {
            self::ENCODING_INT => $this->transcode($value),
            self::ENCODING_BASE64 => $this->encodeBase64($value),
            self::ENCODING_HEX => $this->encodeHex($value),
            default => $this->encodeHex($value),
        };
    }

    /**
     * Decodes an encoded value back to its original integer.
     */
    public function decode(int|string $encoded): int
    {
        return match ($this->encoding) {
            self::ENCODING_INT => $this->transcode((int) $encoded),
            self::ENCODING_BASE64 => $this->decodeBase64((string) $encoded),
            self::ENCODING_HEX => $this->decodeHex((string) $encoded),
            default => $this->decodeHex((string) $encoded),
        };
    }

    /**
     * Internal reversible integer transform function (16-bit).
     */
    private function transform(int $i): int
    {
        $i = ($this->key ^ $i) * 0x9e3b;
        return ($i >> ($i & 0xF)) & 0xFFFF;
    }

    /**
     * Reversibly scrambles a 32-bit integer.
     */
    private function transcode(int $i): int
    {
        $r = $i & 0xFFFF;
        $l = (($i >> 16) & 0xFFFF) ^ $this->transform($r);
        return (($r ^ $this->transform($l)) << 16) + $l;
    }

    /**
     * Transcodes and returns an 8-character hexadecimal string.
     */
    private function encodeHex(int $i): string
    {
        return str_pad(dechex($this->transcode($i)), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Transcodes and returns a 6-character URL-safe base64 string.
     */
    private function encodeBase64(int $i): string
    {
        $packed = pack('N', $this->transcode($i));
        return strtr(substr(base64_encode($packed), 0, 6), '+/', self::EXTRA_CHARS);
    }

    /**
     * Decodes an 8-character hexadecimal string back to the original integer.
     */
    private function decodeHex(string $s): int
    {
        return $this->transcode((int) hexdec($s));
    }

    /**
     * Decodes a 6-character base64 URL-safe string back to the original integer.
     */
    private function decodeBase64(string $s): int
    {
        $decoded = base64_decode(strtr($s, self::EXTRA_CHARS, '+/'));
        $unpacked = unpack('N', $decoded);
        return $this->transcode($unpacked[1]);
    }
}
