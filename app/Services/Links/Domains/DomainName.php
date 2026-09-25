<?php

namespace App\Services\Links\Domains;

final class DomainName
{
    public static function toAscii(string $value): string
    {
        return self::tryToAscii($value)
            ?? throw new \InvalidArgumentException('Invalid domain name.');
    }

    public static function toUnicode(string $value): string
    {
        $ascii = self::toAscii($value);
        $unicode = idn_to_utf8($ascii, IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46, $info);

        return $unicode !== false && $info['errors'] === 0 ? $unicode : $ascii;
    }

    public static function tryToAscii(string $value): ?string
    {
        $value = trim($value, " \t\r\n");

        if ($value === '') {
            return null;
        }

        $ascii = idn_to_ascii(
            $value,
            IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_USE_STD3_RULES | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ,
            INTL_IDNA_VARIANT_UTS46,
            $info,
        );

        if ($ascii === false || $info['errors'] !== 0) {
            return null;
        }

        $ascii = str_ends_with($ascii, '.') ? substr($ascii, 0, -1) : $ascii;

        if (strlen($ascii) > 253 || ! preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)*$/D', $ascii)) {
            return null;
        }

        return $ascii;
    }
}
