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
        'defaults' => [
            'phone' => [
                'class' => env('CISCO_AXL_DEFAULT_PHONE_CLASS', 'Phone'),
                'protocol' => env('CISCO_AXL_DEFAULT_PHONE_PROTOCOL', 'SIP'),
                'protocol_side' => env('CISCO_AXL_DEFAULT_PHONE_PROTOCOL_SIDE', 'User'),
                'product' => env('CISCO_AXL_DEFAULT_PHONE_PRODUCT', 'Cisco Unified Client Services Framework'),
                'common_phone_config' => env('CISCO_AXL_DEFAULT_COMMON_PHONE_CONFIG', 'Standard Common Phone Profile'),
                'device_pool' => env('CISCO_AXL_DEFAULT_DEVICE_POOL', 'Default'),
                'location' => env('CISCO_AXL_DEFAULT_LOCATION', 'Hub_None'),
                'security_profile' => env('CISCO_AXL_DEFAULT_SECURITY_PROFILE', 'Universal Device Template - Model-independent Security Profile'),
                'sip_profile' => env('CISCO_AXL_DEFAULT_SIP_PROFILE', 'Standard SIP Profile'),
            ],
            'line' => [
                'usage' => env('CISCO_AXL_DEFAULT_LINE_USAGE', 'Device'),
                'calling_permission_labels' => [
                    // 'CSS_NAME' => 'Anzeigename',
                ],
            ],
            'hunt_list' => [
                'call_manager_group' => env('CISCO_AXL_DEFAULT_CALL_MANAGER_GROUP', 'Default'),
            ],
            'line_group' => [
                'distribution_algorithm' => env('CISCO_AXL_DEFAULT_LINE_GROUP_DISTRIBUTION', 'Longest Idle Time'),
                'rna_reversion_timeout' => (int) env('CISCO_AXL_DEFAULT_LINE_GROUP_RNA_TIMEOUT', 10),
            ],
        ],
    ],
    'cupi' => [
        'base_url' => env('CISCO_CUPI_BASE_URL','https://nbg-handor-cuc1.hwkdo.local/vmrest/'),
        'username' => env('CISCO_CUPI_USERNAME',env('CISCO_AXL_USERNAME')),
        'password' => env('CISCO_CUPI_PASSWORD',env('CISCO_AXL_PASSWORD')),
    ]   
];
