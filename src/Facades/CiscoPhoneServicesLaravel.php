<?php

namespace Hwkdo\CiscoPhoneServicesLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\CiscoPhoneServicesLaravel\CiscoPhoneServicesLaravel
 */
class CiscoPhoneServicesLaravel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\CiscoPhoneServicesLaravel\CiscoPhoneServicesLaravel::class;
    }
}
