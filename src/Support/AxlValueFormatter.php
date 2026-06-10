<?php

declare(strict_types=1);

namespace Hwkdo\CiscoPhoneServicesLaravel\Support;

class AxlValueFormatter
{
    public static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value)) {
            foreach (['_', '_value_1', 'name', 'value'] as $property) {
                if (isset($value->{$property}) && (is_scalar($value->{$property}) || $value->{$property} === null)) {
                    return (string) ($value->{$property} ?? '');
                }
            }

            foreach (['_', '_value_1', 'name', 'value'] as $property) {
                if (isset($value->{$property})) {
                    return self::stringify($value->{$property});
                }
            }
        }

        return '';
    }

    public static function normalizeUuid(?string $uuid): string
    {
        return trim((string) $uuid, '{}');
    }

    public static function isUuid(string $value): bool
    {
        $cleaned = self::normalizeUuid($value);

        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $cleaned);
    }

    public static function formatUuidForAxl(string $value): string
    {
        $cleaned = self::normalizeUuid($value);

        return '{'.$cleaned.'}';
    }
}
