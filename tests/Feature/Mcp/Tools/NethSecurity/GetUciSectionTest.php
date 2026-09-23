<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\GetUciSection;
use App\Services\Privacy\SecretMasker;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeGetUciSectionExecutor(string $result): RemoteExecutor
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
function getUciSection(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new GetUciSection($executor, new SecretMasker('test-key')))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it returns the section with secrets masked', function () {
    $executor = fakeGetUciSectionExecutor('{"values":{".type":"user","name":"admin","password":"$6$hash","openvpn_ipaddr":["10.0.0.2"]}}');

    $result = getUciSection($executor, ['config' => 'users', 'section' => '@user[0]']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['values' => [
        '.type' => 'user',
        'name' => 'admin',
        'password' => '[masked:hmac-sha256:'.substr(hash_hmac('sha256', '$6$hash', 'test-key'), 0, 12).']',
        'openvpn_ipaddr' => ['10.0.0.2'],
    ]]);
    expect($executor->calledWithCommand)->toBe("ubus -S call 'uci' 'get' '{\"config\":\"users\",\"section\":\"@user[0]\"}'");
});

test('it rejects invalid arguments', function (array $arguments) {
    expect(fn () => getUciSection(fakeGetUciSectionExecutor('{}'), $arguments))
        ->toThrow(ValidationException::class);
})->with([
    'missing section' => [['config' => 'network']],
    'malformed positional section' => [['config' => 'firewall', 'section' => '@zone[x]']],
    'shell injection in section' => [['config' => 'network', 'section' => "RED'; reboot; '"]],
]);
