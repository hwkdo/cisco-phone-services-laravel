<?php

namespace Hwkdo\CiscoPhoneServicesLaravel\Interfaces;

interface CupiServiceInterface
{
    public function getUserObjectId(string $username): string | null;
    public function deleteUser(string $objectId): bool;
}