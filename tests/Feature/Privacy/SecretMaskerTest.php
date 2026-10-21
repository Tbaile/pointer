<?php

use App\Services\Privacy\SecretMasker;

function maskedToken(string $value): string
{
    return '[masked:hmac-sha256:'.substr(hash_hmac('sha256', $value, 'test-key'), 0, 12).']';
}

test('it masks every item matched by a wildcard', function () {
    $masked = (new SecretMasker('test-key'))->mask(
        ['items' => [['secret' => 'one', 'name' => 'a'], ['secret' => 'two', 'name' => 'b']]],
        ['items.*.secret'],
    );

    expect($masked)->toBe(['items' => [
        ['secret' => maskedToken('one'), 'name' => 'a'],
        ['secret' => maskedToken('two'), 'name' => 'b'],
    ]]);
});

test('it does not create missing fields', function () {
    $masked = (new SecretMasker('test-key'))->mask(
        ['items' => [['name' => 'a'], ['iface' => 'eth0']]],
        ['items.*.iface.secret', 'missing.secret'],
    );

    expect($masked)->toBe(['items' => [['name' => 'a'], ['iface' => 'eth0']]]);
});

test('it gives equal secrets the same token and different secrets different tokens', function () {
    $masked = (new SecretMasker('test-key'))->mask(
        ['a' => 'same', 'b' => 'same', 'c' => 'other'],
        ['a', 'b', 'c'],
    );

    expect($masked['a'])->toBe($masked['b']);
    expect($masked['a'])->not->toBe($masked['c']);
});

test('it leaves null untouched', function () {
    expect((new SecretMasker('test-key'))->mask(['secret' => null], ['secret']))->toBe(['secret' => null]);
});

test('it masks the password of URLs inside strings, keeping the user', function () {
    $masked = (new SecretMasker('test-key'))->maskUrlCredentials([
        'values' => ['global' => [
            'ban_allowurl' => ['https://NETH-1234:s3cr3t@bl.example.com/allow.txt'],
            'ban_feed' => 'https://feeds.example.com/list.txt',
        ]],
        'count' => 3,
    ]);

    expect($masked)->toBe([
        'values' => ['global' => [
            'ban_allowurl' => ['https://NETH-1234:'.maskedToken('s3cr3t').'@bl.example.com/allow.txt'],
            'ban_feed' => 'https://feeds.example.com/list.txt',
        ]],
        'count' => 3,
    ]);
});

test('it masks every value inside a list field', function () {
    $masked = (new SecretMasker('test-key'))->mask(['keys' => ['one', 'two']], ['keys']);

    expect($masked)->toBe(['keys' => [maskedToken('one'), maskedToken('two')]]);
});
