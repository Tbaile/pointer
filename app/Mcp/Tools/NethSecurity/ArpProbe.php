<?php

namespace App\Mcp\Tools\NethSecurity;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Title('ARP Probe')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Run ARP duplicate address detection from a NethSecurity firewall (`arping -D`): ask on one interface whether another host answers for an IPv4 address, usually the firewall's own.
    Only works on `nethsecurity` machines.

    - `exit_code` `0`: nobody else answers (`Received 0 response(s)`). `1`: another host answers; `output` has its MAC in `Unicast reply from <ip> [<mac>]`, e.g. the HA peer or a device configured with the same WAN address.
    - Probing an address that belongs to another host on the link also returns `1` with that host's MAC.
    - An unknown `device` returns `Device <name> not available`.
    - For the neighbour table use `network-state` with `object` `neighbours`.
    DESCRIPTION)]
class ArpProbe extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'device' => ['required', 'string', 'regex:/^[A-Za-z0-9_.@-]+$/'],
            'ip' => ['required', 'ipv4'],
            'count' => ['integer', 'min:1', 'max:5'],
        ]);

        $command = sprintf(
            'arping -D -c %d -I %s %s 2>&1',
            $validated['count'] ?? 3,
            escapeshellarg($validated['device']),
            escapeshellarg($validated['ip']),
        );

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if ($result->exitCode > 1) {
            return Response::error(trim($result->output) ?: "arping exited with code {$result->exitCode}");
        }

        return Response::structured([
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
            'device' => $schema->string()
                ->description('Network device to probe on, e.g. `eth1`.')
                ->required(),
            'ip' => $schema->string()
                ->description('IPv4 address to check, e.g. the WAN address `192.168.5.19`.')
                ->required(),
            'count' => $schema->integer()
                ->min(1)
                ->max(5)
                ->description('Number of probes, 3 by default.'),
        ];
    }
}
