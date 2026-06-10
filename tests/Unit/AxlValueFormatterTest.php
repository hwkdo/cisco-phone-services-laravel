<?php

declare(strict_types=1);

use Hwkdo\CiscoPhoneServicesLaravel\Support\AxlValueFormatter;

test('axl value formatter stringifies scalar values', function () {
    expect(AxlValueFormatter::stringify('SIP'))->toBe('SIP')
        ->and(AxlValueFormatter::stringify(42))->toBe('42');
});

test('axl value formatter stringifies cisco fk objects', function () {
    $value = (object) ['_' => 'PHONES', 'uuid' => '{ABC}'];

    expect(AxlValueFormatter::stringify($value))->toBe('PHONES');
});

test('axl value formatter stringifies owner user name fk objects', function () {
    $value = (object) ['_' => 'max.mustermann', 'uuid' => '{ABC}'];

    expect(AxlValueFormatter::stringify($value))->toBe('max.mustermann');
});

test('axl value formatter normalizes uuids', function () {
    expect(AxlValueFormatter::normalizeUuid('{ABC-DEF}'))->toBe('ABC-DEF')
        ->and(AxlValueFormatter::isUuid('12345678-1234-1234-1234-123456789012'))->toBeTrue()
        ->and(AxlValueFormatter::formatUuidForAxl('12345678-1234-1234-1234-123456789012'))
        ->toBe('{12345678-1234-1234-1234-123456789012}');
});
