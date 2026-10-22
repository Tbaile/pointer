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

#[Title('Firewall Status')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the firewalld configuration of a NethServer 8 node (`firewall-cmd`).
    Only works on `nethserver` machines.

    - Default: every zone (`--list-all-zones`) with its `target`, `interfaces`, `sources`, `services`, `ports`, `forward-ports` and `rich rules`. `(active)` marks the zones in use: usually `public` for the node's interfaces, `trusted` for the cluster VPN network, plus one zone per module tunnel.
    - `zone`: only that zone, e.g. `public`.
    - `service`: the ports behind a service name listed in a zone, e.g. `nethsecurity-controller1` is `20001/udp`. Modules open their ports as a service named after the instance.
    - An unknown zone or service returns `INVALID_ZONE` or `INVALID_SERVICE`.
    - CrowdSec bans are not firewalld rules: check an address with `crowdsec` `decisions`. For the addresses, routes and listening sockets use `network-state`.
    DESCRIPTION)]
class FirewallStatus extends Tool
{
    private const string NAME_PATTERN = 'regex:/^[A-Za-z0-9_.-]+$/';

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'zone' => ['string', self::NAME_PATTERN, 'prohibits:service'],
            'service' => ['string', self::NAME_PATTERN],
        ]);

        $command = match (true) {
            isset($validated['zone']) => 'firewall-cmd --zone='.escapeshellarg($validated['zone']).' --list-all 2>&1',
            isset($validated['service']) => 'firewall-cmd --info-service='.escapeshellarg($validated['service']).' 2>&1',
            default => 'firewall-cmd --list-all-zones 2>&1',
        };

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "firewall-cmd exited with code {$result->exitCode}");
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
            'zone' => $schema->string()
                ->description('Only this zone, e.g. `public`.'),
            'service' => $schema->string()
                ->description('Show the ports of this service instead, e.g. `nethsecurity-controller1`.'),
        ];
    }
}
