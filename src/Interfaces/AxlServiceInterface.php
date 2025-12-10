<?php

namespace Hwkdo\CiscoPhoneServicesLaravel\Interfaces;

use Illuminate\Contracts\Auth\Authenticatable;

interface AxlServiceInterface
{
    public function getLinePatternForUser(Authenticatable $user): string;
    public function getLinePickupGroup(string $pattern) : array;

    public function getLine(string $pattern);

    public function getCallingSearchSpaceName(string $pattern): array;

    public function getLineForwardAllDestination(string $pattern): string;

    public function setLineForwardAllDestination(string $pattern, string $destination);

    public function listCallPickupGroups(): array;

    public function getCallPickupGroup(string $name): object;    

    public function getPickupGroupMembers(string $groupName): array;

    public function setLinePickupGroupName(string $pattern, string $name);

    public function executeSql(string $sql);
}