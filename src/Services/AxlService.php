<?php

namespace Hwkdo\CiscoPhoneServicesLaravel\Services;

use Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface;
use Hwkdo\CiscoPhoneServicesLaravel\Support\AxlValueFormatter;
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
            ],
        ];

        $result = $this->client->listLine($payload);

        return $this->normalizeListResponse($result, 'line', function ($line) {
            return [
                'pattern' => $line->pattern ?? '',
                'description' => $line->description ?? '',
                'alerting_name' => AxlValueFormatter::stringify($line->alertingName ?? ''),
                'uuid' => AxlValueFormatter::normalizeUuid($line->uuid ?? ''),
                'usage' => AxlValueFormatter::stringify($line->usage ?? ''),
                'route_partition' => AxlValueFormatter::stringify($line->routePartitionName ?? ''),
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