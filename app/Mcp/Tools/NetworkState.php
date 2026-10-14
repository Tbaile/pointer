<?php

namespace App\Mcp\Tools;

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
    Read the live network state of the machine: addresses, routes, ARP/NDP neighbours or listening sockets.
    Works on `nethsecurity` and `nethserver` machines.

    - `addresses` (default, `ip -br addr`): one line per interface with its state and every IPv4 and IPv6 address.
    - `routes` (`ip route`): the main IPv4 routing table; `ipv6` for the IPv6 one.
    - `neighbours` (`ip neigh`): the IPv4 and IPv6 neighbour table, `device` for one interface. `REACHABLE`/`STALE` entries have the MAC (`lladdr`) that answered; `FAILED` means no answer to ARP. Two IPs with the same MAC on a WAN usually mean the gateway, two MACs for the firewall's own address need `arp-probe`.
    - `sockets`: every listening TCP and UDP socket with its address, port and owning process (`ss -tulpn` on `nethserver`, `netstat -tulpn` on `nethsecurity`). A socket with no process is held by the kernel (e.g. WireGuard). For the process behind a PID use `process-list`.
    - An unknown `device` returns `Cannot find device`.
    - On `nethsecurity`, `list-devices` has the interface configuration and zones; on `nethserver`, whether a port is allowed in is up to `firewall-status`.
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
            'object' => ['in:addresses,routes,neighbours,sockets'],
            'ipv6' => ['boolean'],
            'device' => ['string', 'regex:/^[A-Za-z0-9_.@-]+$/'],
        ]);

        $command = match ($validated['object'] ?? 'addresses') {
            'routes' => ($validated['ipv6'] ?? false) ? 'ip -6 route 2>&1' : 'ip route 2>&1',
            'neighbours' => isset($validated['device']) ? 'ip neigh show dev '.escapeshellarg($validated['device']).' 2>&1' : 'ip neigh 2>&1',
            'sockets' => 'if command -v ss >/dev/null; then ss -tulpn; else netstat -tulpn; fi 2>&1',
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
                ->enum(['addresses', 'routes', 'neighbours', 'sockets'])
                ->description('What to read, `addresses` by default.'),
            'ipv6' => $schema->boolean()
                ->description('With `routes`, read the IPv6 routing table.'),
            'device' => $schema->string()
                ->description('With `neighbours`, only this interface, e.g. `eth1`.'),
        ];
    }
}
