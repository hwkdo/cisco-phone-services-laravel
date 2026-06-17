<?php

namespace Hwkdo\CiscoPhoneServicesLaravel\Services;

use Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface;
use Hwkdo\CiscoPhoneServicesLaravel\Support\AxlValueFormatter;
use Hwkdo\CiscoPhoneServicesLaravel\Support\LineCallingPermissionFormatter;
use Illuminate\Contracts\Auth\Authenticatable;
use SoapClient;

class AxlService implements AxlServiceInterface
{
    protected $client;

    public function __construct()
    {
        $this->client = new SoapClient(
            config('cisco-phone-services-laravel.axl.wsdl'),
            [
                'trace' => config('cisco-phone-services-laravel.axl.trace'),
                'exceptions' => config('cisco-phone-services-laravel.axl.exceptions'),
                'location' => config('cisco-phone-services-laravel.axl.host'),
                'login' => config('cisco-phone-services-laravel.axl.username'),
                'password' => config('cisco-phone-services-laravel.axl.password'),
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ]),
            ]
        );
    }
    
    public function getLine(string $pattern)
    {
        $payload = [
            'pattern' => $pattern,
            'routePartitionName' => config('cisco-phone-services-laravel.axl.partition'),            
        ];

        $result  = $this->client->getLine(
            $payload
        );

        return $result->return->line;
    }

    public function getLineForwardAllDestination(string $pattern) : string
    {
        $payload = [
            'pattern' => $pattern,
            'routePartitionName' => config('cisco-phone-services-laravel.axl.partition'),
            'returnedTags' => ["callForwardAll" => [
                "destination" => "",
            ]]
        ];

        $result  = $this->client->getLine(
            $payload
        );

        return $result->return->line->callForwardAll->destination;
    }    

    public function getLinePickupGroup(string $pattern) : array
    {
        $payload = [
            'pattern' => $pattern,
            'routePartitionName' => config('cisco-phone-services-laravel.axl.partition'),
            'returnedTags' => ["callPickupGroupName" => [
                "_" => "",
                "uuid" => "",
            ]]
        ];

        $result  = $this->client->getLine(
            $payload
        );        

        return [
            "name" => $result->return->line->callPickupGroupName?->_ ?? null,
            "uuid" => $result->return->line->callPickupGroupName?->uuid ?? null,
        ];        
    }   

    public function getCallingSearchSpaceName(string $pattern) : array
    {
        $line = $this->getLine($pattern);
        return [
            "_" => $line->callForwardAll->callingSearchSpaceName->_ ?? null,
            "uuid" => $line->callForwardAll->callingSearchSpaceName->uuid ?? null,
        ];
    }

    public function setLineForwardAllDestination(string $pattern, string $destination)
    {
        $line = $this->getLine($pattern);
        $callingSearchSpaceName = [
            "_" => $line->callForwardAll->callingSearchSpaceName->_ ?? null,
            "uuid" => $line->callForwardAll->callingSearchSpaceName->uuid ?? null,
        ];
        $isForwardToVoiceMailEnabled = filter_var(
            $line->callForwardAll->forwardToVoiceMail ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if ($destination !== '' && $isForwardToVoiceMailEnabled) {
            $this->client->updateLine([
                'pattern' => $pattern,
                'routePartitionName' => config('cisco-phone-services-laravel.axl.partition'),
                'callForwardAll' => [
                    'callingSearchSpaceName' => $callingSearchSpaceName,
                    'destination' => '',
                    'forwardToVoiceMail' => 'f',
                ],
            ]);
        }

        $payload = [
            'pattern' => $pattern,
            'routePartitionName' => config('cisco-phone-services-laravel.axl.partition'),    
            'callForwardAll' => [
                'callingSearchSpaceName' => $callingSearchSpaceName,
                'destination' => $destination,
            ]
        ];

        if ($destination !== '') {
            $payload['callForwardAll']['forwardToVoiceMail'] = 'f';
        }

        $result  = $this->client->updateLine(
            $payload
        );

        return $result->return;
    }

    public function setLinePickupGroupName(string $pattern, string $name)
    {
        $payload = [
            'pattern' => $pattern,
            'routePartitionName' => config('cisco-phone-services-laravel.axl.partition'),
            'callPickupGroupName' => $name,
        ];

        $result  = $this->client->updateLine(
            $payload
        );

        return $result->return;
    }

    public function getLinePatternForUser(Authenticatable $user): string
    {
        $short = str($user->telefon)->afterLast('-')->value();

        return config('cisco-phone-services-laravel.axl.pattern').$short;
    }

    public function executeSql(string $sql)
    {
        $payload = [
            'sql' => $sql,
        ];

        $result = $this->client->executeSQLQuery($payload);

        return $result->return;
    }

    public function getPickupGroupMembers(string $groupName): array
    {
       $sql = 'select numplan.dnorpattern from numplan, pickupgrouplinemap, pickupgroup where
    numplan.pkid = pickupgrouplinemap.fknumplan_line and
    pickupgrouplinemap.fkpickupgroup = pickupgroup.pkid and
    pickupgroup.name = \''.$groupName.'\'';

       $result = $this->executeSql($sql);

       if (!isset($result->row) || empty($result->row)) {
           return [];
       }

       $rows = is_array($result->row) ? $result->row : [$result->row];

       return array_map(function ($row) {
           return $row->dnorpattern ?? '';
       }, $rows);
    }

    public function listCallPickupGroups(): array
    {
        $payload = [
            'searchCriteria' => [
                'pattern' => '%',
            ],
            'returnedTags' => [
                'pattern' => '',
                'description' => '',
                'uuid' => '',
                'name' => '',                
            ],
        ];

        $result = $this->client->listCallPickupGroup($payload);

        if (! isset($result->return->callPickupGroup)) {
            return [];
        }

        $groups = is_array($result->return->callPickupGroup)
            ? $result->return->callPickupGroup
            : [$result->return->callPickupGroup];

        return array_map(function ($group) {
            $uuid = $group->uuid ?? '';
            // Remove curly braces from UUID if present (Cisco AXL format)
            $uuid = trim($uuid, '{}');
            
            return [
                'name' => $group->name ?? '',
                'description' => $group->description ?? '',
                'uuid' => $uuid,
                'pattern' => $group->pattern ?? '',
            ];
        }, $groups);
    }

    public function getCallPickupGroup(string $name): object
    {
        // Try UUID first, then fall back to name
        $payload = [];
        
        // Remove curly braces from UUID if present (Cisco AXL format)
        $cleanedName = trim($name, '{}');
        
        // Check if it's a UUID (format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $cleanedName)) {
            // Cisco AXL expects UUID with curly braces
            $payload['uuid'] = '{' . $cleanedName . '}';
        } else {
            $payload['name'] = $name;
        }

        
        $payload['returnedTags'] = [
            'pattern' => '',
            'description' => '',
            'name' => '',
            'members' => [
                'member' => [
                    'uuid' => '',
                    'priority' => '',
                    'pickupDnAndPartition' => [
                        'dnPattern' => '',
                        'routePartitionName' => '',
                    ],
                ],
            ],
        ];

        $result = $this->client->getCallPickupGroup($payload);

        return $result->return->callPickupGroup;
    }

    public function listPhones(): array
    {
        $payload = [
            'searchCriteria' => [
                'name' => '%',
            ],
            'returnedTags' => [
                'name' => '',
                'description' => '',
                'uuid' => '',
                'product' => '',
                'protocol' => '',
                'devicePoolName' => '',
            ],
        ];

        $result = $this->client->listPhone($payload);

        $linesByDevice = $this->fetchDeviceLinesMap();

        return $this->normalizeListResponse($result, 'phone', function ($phone) use ($linesByDevice) {
            $name = $phone->name ?? '';

            return [
                'name' => $name,
                'description' => $phone->description ?? '',
                'uuid' => AxlValueFormatter::normalizeUuid($phone->uuid ?? ''),
                'product' => AxlValueFormatter::stringify($phone->product ?? ''),
                'protocol' => AxlValueFormatter::stringify($phone->protocol ?? ''),
                'device_pool' => AxlValueFormatter::stringify($phone->devicePoolName ?? ''),
                'lines' => $linesByDevice[$name] ?? [],
            ];
        });
    }

    public function listPhonesForUser(string $userId): array
    {
        $userResult = $this->client->getUser([
            'userid' => $userId,
            'returnedTags' => [
                'associatedDevices' => [
                    'device' => '',
                ],
            ],
        ]);

        $associatedDevices = $userResult->return->user->associatedDevices ?? null;

        if ($associatedDevices === null) {
            return [];
        }

        $deviceProperty = $associatedDevices->device ?? null;

        if ($deviceProperty === null || $deviceProperty === '') {
            return [];
        }

        $deviceNames = is_array($deviceProperty) ? $deviceProperty : [$deviceProperty];

        $phones = [];

        foreach ($deviceNames as $deviceName) {
            $name = AxlValueFormatter::stringify($deviceName);

            if ($name === '') {
                continue;
            }

            try {
                $phones[] = $this->summarizePhone($name);
            } catch (\Throwable) {
                $phones[] = [
                    'name' => $name,
                    'description' => '',
                    'product' => '',
                    'protocol' => '',
                    'device_pool' => '',
                    'lines' => [],
                ];
            }
        }

        return $phones;
    }

    public function getPhone(string $identifier): object
    {
        $result = $this->client->getPhone($this->resolveNameOrUuidPayload($identifier));

        return $result->return->phone;
    }

    public function addPhone(array $phone): mixed
    {
        $defaults = config('cisco-phone-services-laravel.axl.defaults.phone', []);

        $payload = array_merge([
            'class' => $defaults['class'] ?? 'Phone',
            'protocol' => $defaults['protocol'] ?? 'SIP',
            'protocolSide' => $defaults['protocol_side'] ?? 'User',
            'commonPhoneConfigName' => $defaults['common_phone_config'] ?? 'Standard Common Phone Profile',
            'devicePoolName' => $defaults['device_pool'] ?? 'Default',
            'locationName' => $defaults['location'] ?? 'Hub_None',
            'securityProfileName' => $defaults['security_profile'] ?? 'Universal Device Template - Model-independent Security Profile',
            'sipProfileName' => $defaults['sip_profile'] ?? 'Standard SIP Profile',
            'product' => $defaults['product'] ?? 'Cisco Unified Client Services Framework',
        ], $phone);

        $result = $this->client->addPhone([
            'phone' => $payload,
        ]);

        return $result->return;
    }

    public function updatePhone(string $identifier, array $phone): mixed
    {
        $payload = array_merge($this->resolveNameOrUuidPayload($identifier), $phone);

        $result = $this->client->updatePhone($payload);

        return $result->return;
    }

    public function removePhone(string $identifier): mixed
    {
        $result = $this->client->removePhone($this->resolveNameOrUuidPayload($identifier));

        return $result->return;
    }

    public function applyPhone(string $name): mixed
    {
        $result = $this->client->applyPhone([
            'name' => $name,
        ]);

        return $result->return;
    }

    public function listLines(): array
    {
        $payload = [
            'searchCriteria' => [
                'pattern' => '%',
                'routePartitionName' => $this->partitionName(),
            ],
            'returnedTags' => [
                'pattern' => '',
                'description' => '',
                'alertingName' => '',
                'uuid' => '',
                'usage' => '',
                'routePartitionName' => '',
                'shareLineAppearanceCssName' => '',
            ],
        ];

        $result = $this->client->listLine($payload);

        return $this->normalizeListResponse($result, 'line', function ($line) {
            $callingSearchSpace = AxlValueFormatter::stringify($line->shareLineAppearanceCssName ?? '');

            return [
                'pattern' => $line->pattern ?? '',
                'description' => $line->description ?? '',
                'alerting_name' => AxlValueFormatter::stringify($line->alertingName ?? ''),
                'uuid' => AxlValueFormatter::normalizeUuid($line->uuid ?? ''),
                'usage' => AxlValueFormatter::stringify($line->usage ?? ''),
                'route_partition' => AxlValueFormatter::stringify($line->routePartitionName ?? ''),
                'calling_search_space' => $callingSearchSpace,
                'calling_permission' => LineCallingPermissionFormatter::label($callingSearchSpace),
            ];
        });
    }

    public function addLine(array $line): mixed
    {
        $defaults = config('cisco-phone-services-laravel.axl.defaults.line', []);

        $payload = array_merge([
            'usage' => $defaults['usage'] ?? 'Device',
            'routePartitionName' => $this->partitionName(),
        ], $line);

        $result = $this->client->addLine([
            'line' => $payload,
        ]);

        return $result->return;
    }

    public function updateLineByPattern(string $pattern, array $line): mixed
    {
        $payload = array_merge([
            'pattern' => $pattern,
            'routePartitionName' => $this->partitionName(),
        ], $line);

        $result = $this->client->updateLine($payload);

        return $result->return;
    }

    public function removeLine(string $pattern): mixed
    {
        $result = $this->client->removeLine([
            'pattern' => $pattern,
            'routePartitionName' => $this->partitionName(),
        ]);

        return $result->return;
    }

    public function listCallingSearchSpaces(): array
    {
        $payload = [
            'searchCriteria' => [
                'name' => '%',
            ],
            'returnedTags' => [
                'name' => '',
                'description' => '',
            ],
        ];

        $result = $this->client->listCss($payload);

        return $this->normalizeListResponse($result, 'css', function ($css) {
            $name = AxlValueFormatter::stringify($css->name ?? '');

            return [
                'name' => $name,
                'description' => AxlValueFormatter::stringify($css->description ?? ''),
                'label' => LineCallingPermissionFormatter::label($name),
            ];
        });
    }

    public function listUsers(?string $search = null): array
    {
        $payload = [
            'searchCriteria' => [
                'userid' => $search !== null && $search !== '' ? '%'.$search.'%' : '%',
            ],
            'returnedTags' => [
                'userid' => '',
                'firstName' => '',
                'lastName' => '',
                'mailid' => '',
                'department' => '',
                'uuid' => '',
            ],
        ];

        $result = $this->client->listUser($payload);

        return $this->normalizeListResponse($result, 'user', function ($user) {
            return [
                'userid' => $user->userid ?? '',
                'first_name' => $user->firstName ?? '',
                'last_name' => $user->lastName ?? '',
                'mailid' => $user->mailid ?? '',
                'department' => $user->department ?? '',
                'uuid' => AxlValueFormatter::normalizeUuid($user->uuid ?? ''),
            ];
        });
    }

    public function getUser(string $identifier): object
    {
        $result = $this->client->getUser($this->resolveUserIdentifierPayload($identifier));

        return $result->return->user;
    }

    public function addUser(array $user): mixed
    {
        $result = $this->client->addUser([
            'user' => $user,
        ]);

        return $result->return;
    }

    public function updateUser(string $identifier, array $user): mixed
    {
        $payload = array_merge($this->resolveUserIdentifierPayload($identifier), $user);

        $result = $this->client->updateUser($payload);

        return $result->return;
    }

    public function removeUser(string $identifier): mixed
    {
        $result = $this->client->removeUser($this->resolveUserIdentifierPayload($identifier));

        return $result->return;
    }

    public function listHuntPilots(): array
    {
        $payload = [
            'searchCriteria' => [
                'pattern' => '%',
                'routePartitionName' => $this->partitionName(),
            ],
            'returnedTags' => [
                'pattern' => '',
                'description' => '',
                'alertingName' => '',
                'uuid' => '',
                'huntListName' => '',
                'routePartitionName' => '',
            ],
        ];

        $result = $this->client->listHuntPilot($payload);

        return $this->normalizeListResponse($result, 'huntPilot', function ($huntPilot) {
            return [
                'pattern' => $huntPilot->pattern ?? '',
                'description' => $huntPilot->description ?? '',
                'alerting_name' => AxlValueFormatter::stringify($huntPilot->alertingName ?? ''),
                'uuid' => AxlValueFormatter::normalizeUuid($huntPilot->uuid ?? ''),
                'hunt_list_name' => AxlValueFormatter::stringify($huntPilot->huntListName ?? ''),
                'route_partition' => AxlValueFormatter::stringify($huntPilot->routePartitionName ?? ''),
            ];
        });
    }

    public function getHuntPilot(string $identifier): object
    {
        $payload = array_merge($this->resolveHuntPilotIdentifierPayload($identifier), [
            'returnedTags' => [
                'pattern' => '',
                'description' => '',
                'alertingName' => '',
                'uuid' => '',
                'huntListName' => '',
                'routePartitionName' => '',
            ],
        ]);

        $result = $this->client->getHuntPilot($payload);

        return $result->return->huntPilot;
    }

    public function addHuntPilot(array $huntPilot): mixed
    {
        $payload = array_merge([
            'blockEnable' => 'f',
            'useCallingPartyPhoneMask' => 'Default',
            'routePartitionName' => $this->partitionName(),
        ], $huntPilot);

        $result = $this->client->addHuntPilot([
            'huntPilot' => $payload,
        ]);

        return $result->return;
    }

    public function updateHuntPilotByPattern(string $pattern, array $huntPilot): mixed
    {
        $payload = array_merge([
            'pattern' => $pattern,
            'routePartitionName' => $this->partitionName(),
        ], $huntPilot);

        $result = $this->client->updateHuntPilot($payload);

        return $result->return;
    }

    public function removeHuntPilot(string $pattern): mixed
    {
        $result = $this->client->removeHuntPilot([
            'pattern' => $pattern,
            'routePartitionName' => $this->partitionName(),
        ]);

        return $result->return;
    }

    public function listHuntLists(): array
    {
        $payload = [
            'searchCriteria' => [
                'name' => '%',
            ],
            'returnedTags' => [
                'name' => '',
                'description' => '',
                'uuid' => '',
                'callManagerGroupName' => '',
                'routeListEnabled' => '',
                'voiceMailUsage' => '',
            ],
        ];

        $result = $this->client->listHuntList($payload);

        return $this->normalizeListResponse($result, 'huntList', function ($huntList) {
            return [
                'name' => $huntList->name ?? '',
                'description' => $huntList->description ?? '',
                'uuid' => AxlValueFormatter::normalizeUuid($huntList->uuid ?? ''),
                'call_manager_group' => AxlValueFormatter::stringify($huntList->callManagerGroupName ?? ''),
                'route_list_enabled' => filter_var($huntList->routeListEnabled ?? false, FILTER_VALIDATE_BOOLEAN),
                'voice_mail_usage' => filter_var($huntList->voiceMailUsage ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        });
    }

    public function getHuntList(string $identifier): object
    {
        $payload = array_merge($this->resolveNameOrUuidPayload($identifier), [
            'returnedTags' => [
                'name' => '',
                'description' => '',
                'uuid' => '',
                'callManagerGroupName' => '',
                'routeListEnabled' => '',
                'voiceMailUsage' => '',
                'members' => [
                    'member' => [
                        'lineGroupName' => '',
                        'selectionOrder' => '',
                        'uuid' => '',
                    ],
                ],
            ],
        ]);

        $result = $this->client->getHuntList($payload);

        return $result->return->huntList;
    }

    public function getHuntListMembers(string $identifier): array
    {
        $huntList = $this->getHuntList($identifier);

        return $this->extractHuntListMembers($huntList);
    }

    public function addHuntList(array $huntList): mixed
    {
        $defaults = config('cisco-phone-services-laravel.axl.defaults.hunt_list', []);

        $payload = array_merge([
            'callManagerGroupName' => $defaults['call_manager_group'] ?? 'Default',
            'routeListEnabled' => 'f',
            'voiceMailUsage' => 'f',
        ], $huntList);

        $result = $this->client->addHuntList([
            'huntList' => $payload,
        ]);

        return $result->return;
    }

    public function updateHuntList(string $identifier, array $huntList): mixed
    {
        $payload = array_merge($this->resolveNameOrUuidPayload($identifier), $huntList);

        $result = $this->client->updateHuntList($payload);

        return $result->return;
    }

    public function addHuntListMember(string $huntListIdentifier, string $lineGroupName, int $selectionOrder = 1): mixed
    {
        return $this->updateHuntList($huntListIdentifier, [
            'addMembers' => [
                'member' => [
                    [
                        'lineGroupName' => $lineGroupName,
                        'selectionOrder' => $selectionOrder,
                    ],
                ],
            ],
        ]);
    }

    public function removeHuntListMember(string $huntListIdentifier, string $lineGroupName): mixed
    {
        return $this->updateHuntList($huntListIdentifier, [
            'removeMembers' => [
                'member' => [
                    [
                        'lineGroupName' => $lineGroupName,
                    ],
                ],
            ],
        ]);
    }

    public function removeHuntList(string $identifier): mixed
    {
        $result = $this->client->removeHuntList($this->resolveNameOrUuidPayload($identifier));

        return $result->return;
    }

    public function listLineGroups(): array
    {
        $payload = [
            'searchCriteria' => [
                'name' => '%',
            ],
            'returnedTags' => [
                'name' => '',
                'uuid' => '',
                'distributionAlgorithm' => '',
                'rnaReversionTimeOut' => '',
                'autoLogOffHunt' => '',
            ],
        ];

        $result = $this->client->listLineGroup($payload);

        return $this->normalizeListResponse($result, 'lineGroup', function ($lineGroup) {
            return [
                'name' => $lineGroup->name ?? '',
                'uuid' => AxlValueFormatter::normalizeUuid($lineGroup->uuid ?? ''),
                'distribution_algorithm' => AxlValueFormatter::stringify($lineGroup->distributionAlgorithm ?? ''),
                'rna_reversion_timeout' => (int) ($lineGroup->rnaReversionTimeOut ?? 0),
                'auto_log_off_hunt' => filter_var($lineGroup->autoLogOffHunt ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        });
    }

    public function getLineGroup(string $identifier): object
    {
        $payload = array_merge($this->resolveNameOrUuidPayload($identifier), [
            'returnedTags' => [
                'name' => '',
                'uuid' => '',
                'distributionAlgorithm' => '',
                'rnaReversionTimeOut' => '',
                'autoLogOffHunt' => '',
                'huntAlgorithmNoAnswer' => '',
                'huntAlgorithmBusy' => '',
                'huntAlgorithmNotAvailable' => '',
                'members' => [
                    'member' => [
                        'lineSelectionOrder' => '',
                        'directoryNumber' => [
                            'pattern' => '',
                            'routePartitionName' => '',
                        ],
                        'uuid' => '',
                    ],
                ],
            ],
        ]);

        $result = $this->client->getLineGroup($payload);

        return $result->return->lineGroup;
    }

    public function getLineGroupMembers(string $identifier): array
    {
        $lineGroup = $this->getLineGroup($identifier);

        return $this->extractLineGroupMembers($lineGroup);
    }

    public function addLineGroup(array $lineGroup): mixed
    {
        $defaults = config('cisco-phone-services-laravel.axl.defaults.line_group', []);

        $payload = array_merge([
            'distributionAlgorithm' => $defaults['distribution_algorithm'] ?? 'Longest Idle Time',
            'rnaReversionTimeOut' => $defaults['rna_reversion_timeout'] ?? 10,
            'huntAlgorithmNoAnswer' => 'Try next member; then, try next group in Hunt List',
            'huntAlgorithmBusy' => 'Try next member; then, try next group in Hunt List',
            'huntAlgorithmNotAvailable' => 'Try next member; then, try next group in Hunt List',
            'autoLogOffHunt' => 'f',
        ], $lineGroup);

        $result = $this->client->addLineGroup([
            'lineGroup' => $payload,
        ]);

        return $result->return;
    }

    public function updateLineGroup(string $identifier, array $lineGroup): mixed
    {
        $payload = array_merge($this->resolveNameOrUuidPayload($identifier), $lineGroup);

        $result = $this->client->updateLineGroup($payload);

        return $result->return;
    }

    public function addLineGroupMember(string $lineGroupIdentifier, string $pattern, ?string $routePartition = null, int $lineSelectionOrder = 1): mixed
    {
        return $this->updateLineGroup($lineGroupIdentifier, [
            'addMembers' => [
                'member' => [
                    [
                        'lineSelectionOrder' => $lineSelectionOrder,
                        'directoryNumber' => [
                            'pattern' => $pattern,
                            'routePartitionName' => $routePartition ?? $this->partitionName(),
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function removeLineGroupMember(string $lineGroupIdentifier, string $pattern, ?string $routePartition = null): mixed
    {
        return $this->updateLineGroup($lineGroupIdentifier, [
            'removeMembers' => [
                'member' => [
                    [
                        'directoryNumber' => [
                            'pattern' => $pattern,
                            'routePartitionName' => $routePartition ?? $this->partitionName(),
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function removeLineGroup(string $identifier): mixed
    {
        $result = $this->client->removeLineGroup($this->resolveNameOrUuidPayload($identifier));

        return $result->return;
    }

    private function partitionName(): string
    {
        return (string) config('cisco-phone-services-laravel.axl.partition', 'PHONES');
    }

    /**
     * @return array<string, string>
     */
    private function resolveNameOrUuidPayload(string $identifier): array
    {
        if (AxlValueFormatter::isUuid($identifier)) {
            return [
                'uuid' => AxlValueFormatter::formatUuidForAxl($identifier),
            ];
        }

        return [
            'name' => $identifier,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolveUserIdentifierPayload(string $identifier): array
    {
        if (AxlValueFormatter::isUuid($identifier)) {
            return [
                'uuid' => AxlValueFormatter::formatUuidForAxl($identifier),
            ];
        }

        return [
            'userid' => $identifier,
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     description: string,
     *     product: string,
     *     protocol: string,
     *     device_pool: string,
     *     lines: array<int, array{index: int, pattern: string, route_partition: string}>
     * }
     */
    private function summarizePhone(string $name): array
    {
        $result = $this->client->getPhone([
            'name' => $name,
            'returnedTags' => [
                'name' => '',
                'description' => '',
                'product' => '',
                'protocol' => '',
                'devicePoolName' => '',
                'lines' => [
                    'line' => [
                        'index' => '',
                        'dirn' => [
                            'pattern' => '',
                            'routePartitionName' => '',
                        ],
                    ],
                ],
            ],
        ]);

        $phone = $result->return->phone;

        return [
            'name' => AxlValueFormatter::stringify($phone->name ?? $name),
            'description' => AxlValueFormatter::stringify($phone->description ?? ''),
            'product' => AxlValueFormatter::stringify($phone->product ?? ''),
            'protocol' => AxlValueFormatter::stringify($phone->protocol ?? ''),
            'device_pool' => AxlValueFormatter::stringify($phone->devicePoolName ?? ''),
            'lines' => $this->extractPhoneLines($phone),
        ];
    }

    /**
     * @return array<string, array<int, array{index: int, pattern: string, route_partition: string}>>
     */
    private function fetchDeviceLinesMap(): array
    {
        try {
            $sql = 'select device.name as device_name, numplan.dnorpattern as pattern, '
                .'routepartition.name as partition_name, devicenumplanmap.numplanindex as line_index '
                .'from device '
                .'inner join devicenumplanmap on devicenumplanmap.fkdevice = device.pkid '
                .'inner join numplan on numplan.pkid = devicenumplanmap.fknumplan '
                .'left join routepartition on routepartition.pkid = numplan.fkroutepartition';

            $result = $this->executeSql($sql);

            if (! isset($result->row) || empty($result->row)) {
                return [];
            }

            $rows = is_array($result->row) ? $result->row : [$result->row];
            $map = [];

            foreach ($rows as $row) {
                $deviceName = (string) ($row->device_name ?? '');

                if ($deviceName === '') {
                    continue;
                }

                $map[$deviceName][] = [
                    'index' => (int) ($row->line_index ?? 0),
                    'pattern' => (string) ($row->pattern ?? ''),
                    'route_partition' => (string) ($row->partition_name ?? ''),
                ];
            }

            foreach ($map as &$lines) {
                usort($lines, fn (array $a, array $b): int => $a['index'] <=> $b['index']);
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{index: int, pattern: string, route_partition: string}>
     */
    private function extractPhoneLines(mixed $phone): array
    {
        $linesContainer = $phone->lines ?? null;

        if ($linesContainer === null) {
            return [];
        }

        $lineItems = $linesContainer->line ?? null;

        if ($lineItems === null) {
            return [];
        }

        $lines = is_array($lineItems) ? $lineItems : [$lineItems];
        $result = [];

        foreach ($lines as $line) {
            $dirn = $line->dirn ?? null;

            if ($dirn === null) {
                continue;
            }

            $pattern = AxlValueFormatter::stringify($dirn->pattern ?? '');

            if ($pattern === '') {
                continue;
            }

            $result[] = [
                'index' => (int) ($line->index ?? 0),
                'pattern' => $pattern,
                'route_partition' => AxlValueFormatter::stringify($dirn->routePartitionName ?? ''),
            ];
        }

        usort($result, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function resolveHuntPilotIdentifierPayload(string $identifier): array
    {
        if (AxlValueFormatter::isUuid($identifier)) {
            return [
                'uuid' => AxlValueFormatter::formatUuidForAxl($identifier),
            ];
        }

        return [
            'pattern' => $identifier,
            'routePartitionName' => $this->partitionName(),
        ];
    }

    /**
     * @return array<int, array{line_group_name: string, selection_order: int, uuid: string}>
     */
    private function extractHuntListMembers(mixed $huntList): array
    {
        $membersContainer = $huntList->members ?? null;

        if ($membersContainer === null) {
            return [];
        }

        $memberItems = $membersContainer->member ?? null;

        if ($memberItems === null) {
            return [];
        }

        $members = is_array($memberItems) ? $memberItems : [$memberItems];
        $result = [];

        foreach ($members as $member) {
            $lineGroupName = AxlValueFormatter::stringify($member->lineGroupName ?? '');

            if ($lineGroupName === '') {
                continue;
            }

            $result[] = [
                'line_group_name' => $lineGroupName,
                'selection_order' => (int) ($member->selectionOrder ?? 0),
                'uuid' => AxlValueFormatter::normalizeUuid($member->uuid ?? ''),
            ];
        }

        usort($result, fn (array $a, array $b): int => $a['selection_order'] <=> $b['selection_order']);

        return $result;
    }

    /**
     * @return array<int, array{pattern: string, route_partition: string, line_selection_order: int, uuid: string}>
     */
    private function extractLineGroupMembers(mixed $lineGroup): array
    {
        $membersContainer = $lineGroup->members ?? null;

        if ($membersContainer === null) {
            return [];
        }

        $memberItems = $membersContainer->member ?? null;

        if ($memberItems === null) {
            return [];
        }

        $members = is_array($memberItems) ? $memberItems : [$memberItems];
        $result = [];

        foreach ($members as $member) {
            $directoryNumber = $member->directoryNumber ?? null;

            if ($directoryNumber === null) {
                continue;
            }

            $pattern = AxlValueFormatter::stringify($directoryNumber->pattern ?? '');

            if ($pattern === '') {
                continue;
            }

            $result[] = [
                'pattern' => $pattern,
                'route_partition' => AxlValueFormatter::stringify($directoryNumber->routePartitionName ?? ''),
                'line_selection_order' => (int) ($member->lineSelectionOrder ?? 0),
                'uuid' => AxlValueFormatter::normalizeUuid($member->uuid ?? ''),
            ];
        }

        usort($result, fn (array $a, array $b): int => $a['line_selection_order'] <=> $b['line_selection_order']);

        return $result;
    }

    private function normalizeListResponse(mixed $result, string $property, callable $mapper): array
    {
        if (! isset($result->return->{$property})) {
            return [];
        }

        $items = is_array($result->return->{$property})
            ? $result->return->{$property}
            : [$result->return->{$property}];

        return array_values(array_map($mapper, $items));
    }
}