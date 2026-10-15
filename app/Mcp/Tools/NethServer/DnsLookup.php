<?php

namespace App\Mcp\Tools\NethServer;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Name('ns8-dns-lookup')]
#[Title('NS8 DNS Lookup')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Resolve a hostname on a NethServer 8 node with the system resolver (`getent ahosts`), as the node's own services do: `/etc/hosts` first, then the nameservers in `/etc/resolv.conf`.
    Only works on `nethserver` machines.

    - `resolved` is true when the name has at least one address; exit code 2 means it does not resolve.
    - `output` lists every IPv4 and IPv6 address, each one three times (`STREAM`, `DGRAM`, `RAW`); the name is on the first line.
    - Compare the addresses with the node's public address in `network-state` to check a hostname points to this node.
    DESCRIPTION)]
class DnsLookup extends Tool
{
    private const string HOSTNAME_PATTERN = '/^(?=.{1,253}$)[A-Za-z0-9_]([A-Za-z0-9_-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9_]([A-Za-z0-9_-]{0,61}[A-Za-z0-9])?)*\.?$/';

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'name' => ['required', 'string', 'regex:'.self::HOSTNAME_PATTERN],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], 'getent ahosts '.escapeshellarg($validated['name']).' 2>&1');
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'resolved' => $result->exitCode === 0,
            'exit_code' => $result->exitCode,
            'output' => $result->output,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sos_id' => $schema->string()
                ->description('The SOS id of the target machine.')
                ->required(),
            'name' => $schema->string()
                ->description('Hostname to resolve, e.g. `cti.example.com`.')
                ->required(),
        ];
    }
}
