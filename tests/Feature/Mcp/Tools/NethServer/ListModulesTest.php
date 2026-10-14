<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\ListModules;
use App\Services\Remote\CommandResult;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeListModulesExecutor(CommandResult|Throwable $result): RemoteExecutor
{
    return new class($result) implements RemoteExecutor
    {
        public ?string $calledWithMachineUuid = null;

        public ?string $calledWithCommand = null;

        public function __construct(private CommandResult|Throwable $result) {}

        public function run(string $machineUuid, string $command): CommandResult
        {
            $this->calledWithMachineUuid = $machineUuid;
            $this->calledWithCommand = $command;

            if ($this->result instanceof Throwable) {
                throw $this->result;
            }

            return $this->result;
        }
    };
}

function listModules(RemoteExecutor $executor): Response
{
    return (new ListModules($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000']));
}

test('it lists every instance as one row sorted by id', function () {
    $executor = fakeListModulesExecutor(new CommandResult(json_encode([
        'ghcr.io/nethserver/webtop' => [
            ['id' => 'webtop10', 'source' => 'ghcr.io/nethserver/webtop', 'version' => '1.4.2', 'module' => 'webtop', 'node' => '2', 'digest' => 'sha256:aa', 'logo' => '', 'flags' => ['rootless']],
            ['id' => 'webtop3', 'source' => 'ghcr.io/nethserver/webtop', 'version' => '1.4.1', 'module' => 'webtop', 'node' => '1', 'digest' => 'sha256:bb', 'logo' => '', 'flags' => ['rootless']],
        ],
        'ghcr.io/nethserver/crowdsec' => [
            ['id' => 'crowdsec1', 'source' => 'ghcr.io/nethserver/crowdsec', 'version' => '1.4.0', 'module' => 'crowdsec', 'node' => '1', 'digest' => 'sha256:cc', 'logo' => '', 'flags' => []],
        ],
    ])));

    $result = listModules($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe(
        "ID\tMODULE\tVERSION\tNODE\tFLAGS\n"
        ."crowdsec1\tcrowdsec\t1.4.0\t1\t\n"
        ."webtop3\twebtop\t1.4.1\t1\trootless\n"
        ."webtop10\twebtop\t1.4.2\t2\trootless\n"
    );
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('api-cli run cluster/list-installed-modules 2>&1');
});

test('it reports a failed api-cli run as an error', function () {
    $result = listModules(fakeListModulesExecutor(new CommandResult("TaskSubmissionCheckFailed: Client \"cluster\" was not found\n", '', 1)));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('TaskSubmissionCheckFailed: Client "cluster" was not found');
});

test('it reports output that is not JSON as an error', function () {
    $result = listModules(fakeListModulesExecutor(new CommandResult("bash: api-cli: command not found\n")));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('bash: api-cli: command not found');
});

test('it reports a failed call as an error', function () {
    $result = listModules(fakeListModulesExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
