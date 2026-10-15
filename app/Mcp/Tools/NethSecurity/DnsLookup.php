<?php

namespace App\Mcp\Tools\NethSecurity;

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

#[Name('nsec-dns-lookup')]
#[Title('NethSecurity DNS Lookup')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Resolve a hostname on a NethSecurity firewall with `nslookup`, through its own DNS server (dnsmasq on `127.0.0.1`), as the LAN clients using the firewall as DNS see it.
    Only works on `nethsecurity` machines.

    - `resolved` is true when the name has at least one address; exit code 1 with `server can't find <name>: NXDOMAIN` means it does not exist.
    - `output` lists the IPv4 and IPv6 addresses after `Non-authoritative answer:`.
    - Local records come from the `dhcp` config (`get-uci-config {"config": "dhcp"}`, `domain` and `host` sections). Upstream servers are the `server` list of the `dnsmasq` section, or the ones learned from the WAN when it is empty.
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
            $result = $this->executor->run($validated['sos_id'], 'nslookup '.escapeshellarg($validated['name']).' 2>&1');
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
                ->description('Hostname to resolve, e.g. `bl.nethesis.it`.')
                ->required(),
        ];
    }
}
