<?php

namespace Hwkdo\CiscoPhoneServicesLaravel\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Hwkdo\CiscoPhoneServicesLaravel\Interfaces\CupiServiceInterface;
use Illuminate\Support\Facades\Http;

class CupiService implements CupiServiceInterface
{
    protected $client;

    public function __construct()
    {
        $this->client = Http::withoutVerifying()->withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->withBasicAuth(config('cisco-phone-services-laravel.cupi.username'),config('cisco-phone-services-laravel.cupi.password'));        
    }
    
    public function getUserObjectId(string $username): string | null
    {
        $result  = $this->client->get(config('cisco-phone-services-laravel.cupi.base_url').'users?query=(alias startswith '.$username.')');                    
        return $result->ok() && $result->json()["@total"] > 0 ? $result->json()["User"]["ObjectId"] : null;
    }    

    public function deleteUser(string $objectId): bool
    {
        $result  = $this->client->delete(
            config('cisco-phone-services-laravel.cupi.base_url').'users/'.$objectId.'?method=resetmwi'
        );
        return $result->status() == 204;
    }
}