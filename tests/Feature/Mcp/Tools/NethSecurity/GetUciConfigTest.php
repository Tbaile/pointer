<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\GetUciConfig;
use App\Services\Privacy\SecretMasker;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function getUciConfig(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new GetUciConfig($executor, new SecretMasker('test-key')))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

function maskedUciValue(string $value): string
{
    return '[masked:hmac-sha256:'.substr(hash_hmac('sha256', $value, 'test-key'), 0, 12).']';
}

test('it returns the configuration with secrets masked', function () {
    $executor = new FakeRemoteExecutor(json_encode(['values' => [
        'wg1' => ['proto' => 'wireguard', 'private_key' => 'cHJpdmF0ZQ==', 'public_key' => 'cHVibGlj'],
        'peer' => ['preshared_key' => 'c2hhcmVk', 'allowed_ips' => ['10.0.0.2/32']],
    ]]));

    $result = getUciConfig($executor, ['config' => 'network']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['values' => [
        'wg1' => ['proto' => 'wireguard', 'private_key' => maskedUciValue('cHJpdmF0ZQ=='), 'public_key' => 'cHVibGlj'],
        'peer' => ['preshared_key' => maskedUciValue('c2hhcmVk'), 'allowed_ips' => ['10.0.0.2/32']],
    ]]);
    expect($executor->calledWithCommand)->toBe("ubus -S call 'uci' 'get' '{\"config\":\"network\"}'");
});

test('it filters sections by type and matching options', function () {
    $executor = new FakeRemoteExecutor('{"values":{}}');

    getUciConfig($executor, ['config' => 'firewall', 'type' => 'rule', 'match' => ['target' => 'DROP']]);

    expect($executor->calledWithCommand)->toBe("ubus -S call 'uci' 'get' '{\"config\":\"firewall\",\"type\":\"rule\",\"match\":{\"target\":\"DROP\"}}'");
});

test('it rejects invalid arguments', function (array $arguments) {
    expect(fn () => getUciConfig(new FakeRemoteExecutor('{}'), $arguments))
        ->toThrow(ValidationException::class);
})->with([
    'missing config' => [[]],
    'shell injection in config' => [['config' => "network'; reboot; '"]],
    'path traversal in config' => [['config' => '../shadow']],
    'malformed type' => [['config' => 'firewall', 'type' => 'rule; reboot']],
    'empty match' => [['config' => 'network', 'match' => []]],
]);
