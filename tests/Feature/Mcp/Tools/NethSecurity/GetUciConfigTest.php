<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\GetUciConfig;
use App\Services\Privacy\SecretMasker;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeGetUciConfigExecutor(string $result): RemoteExecutor
{
    return new class($result) implements RemoteExecutor
    {
        public ?string $calledWithCommand = null;

        public function __construct(private string $result) {}

        public function run(string $machineUuid, string $command): CommandResult
        {
            $this->calledWithCommand = $command;

            return new CommandResult($this->result);
        }
    };
}

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
    $executor = fakeGetUciConfigExecutor(json_encode(['values' => [
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
    $executor = fakeGetUciConfigExecutor('{"values":{}}');

    getUciConfig($executor, ['config' => 'firewall', 'type' => 'rule', 'match' => ['target' => 'DROP']]);

    expect($executor->calledWithCommand)->toBe("ubus -S call 'uci' 'get' '{\"config\":\"firewall\",\"type\":\"rule\",\"match\":{\"target\":\"DROP\"}}'");
});

test('it rejects invalid arguments', function (array $arguments) {
    expect(fn () => getUciConfig(fakeGetUciConfigExecutor('{}'), $arguments))
        ->toThrow(ValidationException::class);
})->with([
    'missing config' => [[]],
    'shell injection in config' => [['config' => "network'; reboot; '"]],
    'path traversal in config' => [['config' => '../shadow']],
    'malformed type' => [['config' => 'firewall', 'type' => 'rule; reboot']],
    'empty match' => [['config' => 'network', 'match' => []]],
]);
