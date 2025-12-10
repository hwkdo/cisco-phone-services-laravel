<?php

namespace Hwkdo\CiscoPhoneServicesLaravel\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface;
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
        $payload = [
            'pattern' => $pattern,
            'routePartitionName' => config('cisco-phone-services-laravel.axl.partition'),    
            'callForwardAll' => [
                "callingSearchSpaceName" => $this->getCallingSearchSpaceName($pattern),
                'destination' => $destination,
            ]
        ];

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
        #dd($result);
        return $result->return->callPickupGroup;
    }    
}