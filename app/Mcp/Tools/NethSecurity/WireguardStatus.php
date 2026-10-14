<?php

namespace App\Mcp\Tools\NethSecurity;

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

#[Title('WireGuard Status')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the live state of the WireGuard interfaces of a NethSecurity firewall (`wg show`). Private and preshared keys are printed as `(hidden)`.
    Only works on `nethsecurity` machines.

    - Per interface: `public key` and `listening port`. Per peer: `endpoint` (the address and port packets are actually exchanged with, learned from the last packet received), `allowed ips`, `latest handshake`, `transfer` and `persistent keepalive`.
    - A peer with no `latest handshake` never completed one; a handshake older than about 2 minutes on a busy tunnel means it is down.
    - `interface`: only that interface, e.g. `wg1`. An unknown one returns `Unable to access interface: No such device`.
    - For the handshake and transfer history use `victoria-query` on `wireguard_peer_last_handshake_time_ns`, `wireguard_peer_rx_bytes` and `wireguard_peer_tx_bytes`, labeled by `device` and `public_key`. For the configuration use `get-uci-config` on `network`.
    DESCRIPTION)]
class WireguardStatus extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'interface' => ['string', 'regex:/^[A-Za-z0-9_.-]+$/'],
        ]);

        $command = isset($validated['interface'])
            ? 'wg show '.escapeshellarg($validated['interface']).' 2>&1'
            : 'wg show 2>&1';

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "wg exited with code {$result->exitCode}");
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
            'interface' => $schema->string()
                ->description('Only this WireGuard interface, e.g. `wg1`.'),
        ];
    }
}
