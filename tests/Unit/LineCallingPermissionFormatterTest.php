<?php

declare(strict_types=1);

use Hwkdo\CiscoPhoneServicesLaravel\Support\LineCallingPermissionFormatter;

test('line calling permission formatter maps international css names', function () {
    expect(LineCallingPermissionFormatter::label('CSS_International'))->toBe('International');
    expect(LineCallingPermissionFormatter::label('INT_OUT'))->toBe('International');
});

test('line calling permission formatter maps national css names', function () {
    expect(LineCallingPermissionFormatter::label('CSS_National'))->toBe('National');
    expect(LineCallingPermissionFormatter::label('NAT_OUT'))->toBe('National');
});

test('line calling permission formatter maps internal css names', function () {
    expect(LineCallingPermissionFormatter::label('NUR_INTERN'))->toBe('Nur intern');
    expect(LineCallingPermissionFormatter::label('ONNET_ONLY'))->toBe('Nur intern');
});

test('line calling permission formatter uses configured labels', function () {
    config()->set('cisco-phone-services-laravel.axl.line.calling_permission_labels', [
        'HWK_Special' => 'Sonderberechtigung',
    ]);

    expect(LineCallingPermissionFormatter::label('HWK_Special'))->toBe('Sonderberechtigung');
});

test('line calling permission formatter returns dash for empty css', function () {
    expect(LineCallingPermissionFormatter::label(''))->toBe('—');
});
