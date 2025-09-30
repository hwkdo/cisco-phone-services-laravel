<?php

// config for Hwkdo/CiscoPhoneServicesLaravel
return [
    'axl' => [
        'wsdl' => env('CISCO_AXL_WSDL',storage_path('cisco/AXLAPI.wsdl')),
        'username' => env('CISCO_AXL_USERNAME'),
        'password' => env('CISCO_AXL_PASSWORD'),
        'host' => env('CISCO_AXL_HOST'),
        'trace' => env('CISCO_AXL_TRACE', false),
        'exceptions' => env('CISCO_AXL_EXCEPTIONS', false),
        'partition' => env('CISCO_AXL_PARTITION', 'PHONES'),
        'pattern' => env('CISCO_AXL_PATTERN', '\+492315493'),
        ],
    'cupi' => [
        'base_url' => env('CISCO_CUPI_BASE_URL','https://nbg-handor-cuc1.hwkdo.local/vmrest/'),
        'username' => env('CISCO_CUPI_USERNAME',env('CISCO_AXL_USERNAME')),
        'password' => env('CISCO_CUPI_PASSWORD',env('CISCO_AXL_PASSWORD')),
    ]   
];
