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

#[Title('HA Status')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the High Availability cluster state of a NethSecurity firewall (`ns-ha-config`).
    Only works on `nethsecurity` machines.

    - `status` (default): `Status` (`disabled` means no HA), `Role` (`primary`/`backup`, from the config), `Current State` (keepalived's live state: `master`, `backup`, `fault`, `unknown`), `Locking`, the virtual IPs with their device, and the keepalived statistics.
    - `Role: backup` with `Current State: master` means a failover happened. `unknown` means keepalived is not running.
    - `Last Sync Status` and `Last Sync Time` are printed only on the primary while it is master.
    - Statistics: `pri_zero_rcvd` above `0` means the peer shut down cleanly; `advert_rcvd` that stays the same between two runs means the peer sends no VRRP adverts.
    - `show-config`: the HA-configured interfaces with device and virtual IP, and the extra virtual IPs.
    - For the `keepalived` and `conntrackd` services use `service-status`, for their history `victoria-query` on `procd_service_running`, for the full config `get-uci-config` on `keepalived`.
    DESCRIPTION)]
class HaStatus extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'action' => ['in:status,show-config'],
        ]);

        $command = 'ns-ha-config '.($validated['action'] ?? 'status').' 2>&1';

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "ns-ha-config exited with code {$result->exitCode}");
        }

        return Response::text((string) preg_replace('/\e\[[0-9;]*m/', '', $result->output));
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
            'action' => $schema->string()
                ->enum(['status', 'show-config'])
                ->description('What to read, `status` by default, or `show-config` for the HA interfaces and virtual IPs.'),
        ];
    }
}
