<?php

namespace Hwkdo\CiscoPhoneServicesLaravel\Interfaces;

use Illuminate\Contracts\Auth\Authenticatable;

interface AxlServiceInterface
{
    public function getLinePatternForUser(Authenticatable $user): string;
    public function getLine(string $pattern);
    public function getCallingSearchSpaceName(string $pattern) : array;
    public function getLineForwardAllDestination(string $pattern): string;
    public function setLineForwardAllDestination(string $pattern, string $destination);
}