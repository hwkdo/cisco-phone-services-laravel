<?php

namespace Hwkdo\CiscoPhoneServicesLaravel\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface;
use SoapClient;

class CiscoAxlService implements AxlServiceInterface
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
    public function getLinePatternForUser(Authenticatable $user): string
    {
        $short = str($user->telefon)->afterLast('-')->value();
        return config('cisco-phone-services-laravel.axl.pattern').$short;
    }
}