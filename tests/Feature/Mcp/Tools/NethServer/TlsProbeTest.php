<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\TlsProbe;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function ns8TlsProbe(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new TlsProbe($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it reads the certificate served for the hostname on port 443', function () {
    $output = "subject=CN=app.example.org\nissuer=C=US, O=Let's Encrypt, CN=R11\nnotBefore=Sep 28 18:40:02 2026 GMT\nnotAfter=Dec 27 18:40:01 2026 GMT\nX509v3 Subject Alternative Name: \n    DNS:app.example.org\nHostname app.example.org does match certificate\n";
    $executor = new FakeRemoteExecutor(new CommandResult($output));

    $result = ns8TlsProbe($executor, ['target' => 'app.example.org']);

    expect($result->getStructuredContent())->toBe(['reachable' => true, 'exit_code' => 0, 'output' => $output]);
    expect($executor->calledWithCommand)->toBe("timeout 8 openssl s_client -connect 'app.example.org:443' -servername 'app.example.org' </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer -dates -ext subjectAltName -checkhost 'app.example.org' 2>&1");
});

test('it sends the given name to an IP target and upgrades with STARTTLS', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    ns8TlsProbe($executor, ['target' => '2001:db8::1', 'port' => 587, 'name' => 'mail.example.org', 'starttls' => 'smtp']);

    expect($executor->calledWithCommand)->toBe("timeout 8 openssl s_client -connect '[2001:db8::1]:587' -servername 'mail.example.org' -starttls 'smtp' </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer -dates -ext subjectAltName -checkhost 'mail.example.org' 2>&1");
});

test('it skips SNI and the name check for an IP target without a name', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    ns8TlsProbe($executor, ['target' => '192.0.2.10', 'port' => 5061]);

    expect($executor->calledWithCommand)->toBe("timeout 8 openssl s_client -connect '192.0.2.10:5061' </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer -dates -ext subjectAltName 2>&1");
});

test('it reports a certificate for another name as reachable', function () {
    $output = "subject=CN=host-1.ns8.test\nissuer=CN=host-1.ns8.test\nHostname app.example.org does NOT match certificate\n";

    $result = ns8TlsProbe(new FakeRemoteExecutor(new CommandResult($output, exitCode: 1)), ['target' => 'app.example.org']);

    expect($result->getStructuredContent())->toBe(['reachable' => true, 'exit_code' => 1, 'output' => $output]);
});

test('it reports no certificate as unreachable', function () {
    $output = "Could not find certificate from <stdin>\n";

    $result = ns8TlsProbe(new FakeRemoteExecutor(new CommandResult($output, exitCode: 1)), ['target' => 'app.example.org']);

    expect($result->getStructuredContent())->toBe(['reachable' => false, 'exit_code' => 1, 'output' => $output]);
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => ns8TlsProbe($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing target' => [[]],
    'target with a shell character' => [['target' => 'app.example.org;reboot']],
    'port above the range' => [['target' => 'app.example.org', 'port' => 65536]],
    'name with a space' => [['target' => '192.0.2.10', 'name' => 'app.example.org -proxy x']],
    'unknown STARTTLS protocol' => [['target' => 'app.example.org', 'starttls' => 'mysql']],
]);

test('it returns an error when the machine cannot be reached', function () {
    $result = ns8TlsProbe(new FakeRemoteExecutor(new RuntimeException('Connection timed out')), ['target' => 'app.example.org']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});
