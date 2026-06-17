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

    /**
     * @return array<int, array{
     *     pattern: string,
     *     description: string,
     *     alerting_name: string,
     *     uuid: string,
     *     usage: string,
     *     route_partition: string,
     *     calling_search_space: string,
     *     calling_permission: string
     * }>
     */
    public function listLines(): array;

    public function addLine(array $line): mixed;

    public function updateLineByPattern(string $pattern, array $line): mixed;

    public function removeLine(string $pattern): mixed;

    /**
     * @return array<int, array{name: string, description: string, label: string}>
     */
    public function listCallingSearchSpaces(): array;

    public function listUsers(?string $search = null): array;

    public function getUser(string $identifier): object;

    public function addUser(array $user): mixed;

    public function updateUser(string $identifier, array $user): mixed;

    public function removeUser(string $identifier): mixed;

    /**
     * @return array<int, array{
     *     pattern: string,
     *     description: string,
     *     alerting_name: string,
     *     uuid: string,
     *     hunt_list_name: string,
     *     route_partition: string
     * }>
     */
    public function listHuntPilots(): array;

    public function getHuntPilot(string $identifier): object;

    public function addHuntPilot(array $huntPilot): mixed;

    public function updateHuntPilotByPattern(string $pattern, array $huntPilot): mixed;

    public function removeHuntPilot(string $pattern): mixed;

    /**
     * @return array<int, array{
     *     name: string,
     *     description: string,
     *     uuid: string,
     *     call_manager_group: string,
     *     route_list_enabled: bool,
     *     voice_mail_usage: bool
     * }>
     */
    public function listHuntLists(): array;

    public function getHuntList(string $identifier): object;

    /**
     * @return array<int, array{
     *     line_group_name: string,
     *     selection_order: int,
     *     uuid: string
     * }>
     */
    public function getHuntListMembers(string $identifier): array;

    public function addHuntList(array $huntList): mixed;

    public function updateHuntList(string $identifier, array $huntList): mixed;

    public function addHuntListMember(string $huntListIdentifier, string $lineGroupName, int $selectionOrder = 1): mixed;

    public function removeHuntListMember(string $huntListIdentifier, string $lineGroupName): mixed;

    public function removeHuntList(string $identifier): mixed;

    /**
     * @return array<int, array{
     *     name: string,
     *     uuid: string,
     *     distribution_algorithm: string,
     *     rna_reversion_timeout: int,
     *     auto_log_off_hunt: bool
     * }>
     */
    public function listLineGroups(): array;

    public function getLineGroup(string $identifier): object;

    /**
     * @return array<int, array{
     *     pattern: string,
     *     route_partition: string,
     *     line_selection_order: int,
     *     uuid: string
     * }>
     */
    public function getLineGroupMembers(string $identifier): array;

    public function addLineGroup(array $lineGroup): mixed;

    public function updateLineGroup(string $identifier, array $lineGroup): mixed;

    public function addLineGroupMember(string $lineGroupIdentifier, string $pattern, ?string $routePartition = null, int $lineSelectionOrder = 1): mixed;

    public function removeLineGroupMember(string $lineGroupIdentifier, string $pattern, ?string $routePartition = null): mixed;

    public function removeLineGroup(string $identifier): mixed;
}
