<?php

declare(strict_types=1);

namespace Hwkdo\CiscoPhoneServicesLaravel\Interfaces;

use Illuminate\Contracts\Auth\Authenticatable;

interface AxlServiceInterface
{
    public function getLinePatternForUser(Authenticatable $user): string;

    public function getLinePickupGroup(string $pattern): array;

    public function getLine(string $pattern);

    public function getCallingSearchSpaceName(string $pattern): array;

    public function getLineForwardAllDestination(string $pattern): string;

    public function setLineForwardAllDestination(string $pattern, string $destination);

    public function listCallPickupGroups(): array;

    public function getCallPickupGroup(string $name): object;

    public function getPickupGroupMembers(string $groupName): array;

    public function setLinePickupGroupName(string $pattern, string $name);

    public function executeSql(string $sql);

    public function listPhones(): array;

    /**
     * @return array<int, array{
     *     name: string,
     *     description: string,
     *     product: string,
     *     protocol: string,
     *     device_pool: string,
     *     lines: array<int, array{index: int, pattern: string, route_partition: string}>
     * }>
     */
    public function listPhonesForUser(string $userId): array;

    public function getPhone(string $identifier): object;

    public function addPhone(array $phone): mixed;

    public function updatePhone(string $identifier, array $phone): mixed;

    public function removePhone(string $identifier): mixed;

    public function applyPhone(string $name): mixed;

    public function listLines(): array;

    public function addLine(array $line): mixed;

    public function updateLineByPattern(string $pattern, array $line): mixed;

    public function removeLine(string $pattern): mixed;

    public function listUsers(?string $search = null): array;

    public function getUser(string $identifier): object;

    public function addUser(array $user): mixed;

    public function updateUser(string $identifier, array $user): mixed;

    public function removeUser(string $identifier): mixed;
}
