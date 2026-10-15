<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\DnsLookup;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function nsecDnsLookup(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new DnsLookup($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it resolves the name through the firewall DNS server', function () {
    $output = "Server:\t\t127.0.0.1\nAddress:\t127.0.0.1:53\n\nNon-authoritative answer:\nName:\tnethserver.org\nAddress: 35.214.216.68\n";
    $executor = new FakeRemoteExecutor(new CommandResult($output));

    $result = nsecDnsLookup($executor, ['name' => 'nethserver.org']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['resolved' => true, 'exit_code' => 0, 'output' => $output]);
    expect($executor->calledWithCommand)->toBe("nslookup 'nethserver.org' 2>&1");
});

test('it reports a missing name rather than an error', function () {
    $output = "** server can't find nonexist.invalid: NXDOMAIN\n";

    $result = nsecDnsLookup(new FakeRemoteExecutor(new CommandResult($output, exitCode: 1)), ['name' => 'nonexist.invalid']);

    expect($result->getStructuredContent())->toBe(['resolved' => false, 'exit_code' => 1, 'output' => $output]);
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => nsecDnsLookup($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing name' => [[]],
    'name with a shell command' => [['name' => '$(reboot)']],
    'name starting with a dash' => [['name' => '-type=any']],
]);

test('it returns an error when the machine cannot be reached', function () {
    $result = nsecDnsLookup(new FakeRemoteExecutor(new RuntimeException('Connection timed out')), ['name' => 'nethserver.org']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});
