<?php

declare(strict_types=1);

namespace Hwkdo\CiscoPhoneServicesLaravel\Support;

class LineCallingPermissionFormatter
{
    public static function label(?string $callingSearchSpace): string
    {
        $callingSearchSpace = trim((string) $callingSearchSpace);

        if ($callingSearchSpace === '') {
            return '—';
        }

        $configuredLabels = config('cisco-phone-services-laravel.axl.line.calling_permission_labels', []);

        if (isset($configuredLabels[$callingSearchSpace])) {
            return (string) $configuredLabels[$callingSearchSpace];
        }

        $normalized = strtoupper(str_replace(['-', ' '], '_', $callingSearchSpace));

        if (str_contains($normalized, 'INTERNATIONAL') || preg_match('/(^|_)INT($|_)/', $normalized) === 1) {
            return 'International';
        }

        if (str_contains($normalized, 'NATIONAL') || preg_match('/(^|_)NAT($|_)/', $normalized) === 1) {
            return 'National';
        }

        if (str_contains($normalized, 'INTERN') || str_contains($normalized, 'ONNET') || str_contains($normalized, 'ON_NET')) {
            return 'Nur intern';
        }

        return $callingSearchSpace;
    }
}
