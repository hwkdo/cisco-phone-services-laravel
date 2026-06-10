<?php

declare(strict_types=1);

namespace Hwkdo\CiscoPhoneServicesLaravel\Support;

use SoapFault;
use Throwable;

class AxlExceptionMessage
{
    public static function from(Throwable $throwable): string
    {
        if ($throwable instanceof SoapFault) {
            $message = trim($throwable->faultstring ?: $throwable->getMessage());

            if ($message !== '') {
                return $message;
            }

            $faultCode = trim((string) ($throwable->faultcode ?? ''));

            return $faultCode !== ''
                ? 'AXL-Anfrage fehlgeschlagen ('.$faultCode.')'
                : 'AXL-Anfrage fehlgeschlagen';
        }

        return $throwable->getMessage();
    }
}
