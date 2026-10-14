<?php

namespace App\Mcp\Tools\NethServer;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Title('Network State')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the live network state of a NethServer 8 node: addresses, routes or listening sockets.
    Only works on `nethserver` machines.

    - `addresses` (default, `ip -br addr`): one line per interface with its state and every IPv4 and IPv6 address. `wg0` is the cluster VPN, `tun*` interfaces belong to modules.
    - `routes` (`ip route`): the IPv4 routing table; `ipv6` for the IPv6 one.
    - `sockets` (`ss -tulpn`): every listening TCP and UDP socket with its address, port and owning process, e.g. `users:(("openvpn",pid=28470,fd=5))`. A socket with no process is held by the kernel (e.g. WireGuard).
    - Whether a port is allowed in is up to firewalld: check it with `firewall-status`.
    DESCRIPTION)]
class NetworkState extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'object' => ['in:addresses,routes,sockets'],
            'ipv6' => ['boolean'],
        ]);

        $command = match ($validated['object'] ?? 'addresses') {
            'routes' => ($validated['ipv6'] ?? false) ? 'ip -6 route 2>&1' : 'ip route 2>&1',
            'sockets' => 'ss -tulpn 2>&1',
            default => 'ip -br addr 2>&1',
        };

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "Command exited with code {$result->exitCode}");
        }

        return Response::text($result->output);
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
            'object' => $schema->string()
                ->enum(['addresses', 'routes', 'sockets'])
                ->description('What to read, `addresses` by default.'),
            'ipv6' => $schema->boolean()
                ->description('With `routes`, read the IPv6 routing table.'),
        ];
    }
}
