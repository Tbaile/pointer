<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\NftList;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeNftListExecutor(CommandResult|Throwable $result): RemoteExecutor
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

/**
 * @param  array<string, mixed>  $arguments
 */
function nftList(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new NftList($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists the ruleset without set elements by default and returns it as text', function () {
    $executor = fakeNftListExecutor(new CommandResult("table inet banIP {\n}\n"));

    $result = nftList($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("table inet banIP {\n}\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('nft -t list ruleset 2>&1');
});

test('it lists one chain by family, table and name', function () {
    $executor = fakeNftListExecutor(new CommandResult(''));

    nftList($executor, ['object' => 'chain', 'family' => 'inet', 'table' => 'banIP', 'name' => '_inbound']);

    expect($executor->calledWithCommand)->toBe("nft -t list chain inet 'banIP' '_inbound' 2>&1");
});

test('it lists the elements of a set only when asked', function () {
    $executor = fakeNftListExecutor(new CommandResult(''));

    nftList($executor, ['object' => 'set', 'family' => 'inet', 'table' => 'banIP', 'name' => 'blocklist.v4', 'elements' => true]);

    expect($executor->calledWithCommand)->toBe("nft list set inet 'banIP' 'blocklist.v4' 2>&1");
});

test('it rejects incomplete or unsafe arguments', function (array $arguments) {
    $executor = fakeNftListExecutor(new CommandResult(''));

    expect(fn () => nftList($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'chain without table' => [['object' => 'chain', 'family' => 'inet', 'name' => '_inbound']],
    'set without name' => [['object' => 'set', 'family' => 'inet', 'table' => 'banIP']],
    'unknown family' => [['object' => 'table', 'family' => 'ip4', 'table' => 'fw4']],
    'name breaking out of the shell' => [['object' => 'table', 'family' => 'inet', 'table' => 'fw4; reboot']],
    'unknown object' => [['object' => 'flowtables']],
]);

test('it reports a failed nft as an error', function () {
    $result = nftList(
        fakeNftListExecutor(new CommandResult("Error: No such file or directory\nlist table inet missing\n", '', 1)),
        ['object' => 'table', 'family' => 'inet', 'table' => 'missing'],
    );

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe("Error: No such file or directory\nlist table inet missing");
});

test('it reports a failed call as an error', function () {
    $result = nftList(fakeNftListExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
