<?php

namespace App\Mcp\Tools\NethSecurity;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('MultiWAN Status')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the live MultiWAN (mwan3) state of a NethSecurity firewall (`ubus call mwan3 status`).
    Only works on `nethsecurity` machines.

    - `interfaces` (default): per WAN logical interface, `status` (`online`, `offline`, `connecting`, `disconnecting`, `disabled`, or `unknown` when mwan3track never ran), `up` (link up), `enabled`, `running`, `tracking` (`active`, `paused`, `down` when mwan3track is not running, `disabled` without `track_ip`), `uptime`/`online`/`offline` in seconds, `age` since the last tracking change, `score`, `lost`, `turn`, and `track_ip`: each tracked IP with `status` (`up`/`down`/`skipped`), `latency` (ms) and `packetloss` (%).
    - An interface with `up` true but `status` `offline` has its link working while every `track_ip` fails: check those targets with `ping` and `interface`.
    - `policies`: each policy with its members and their traffic `percent`.
    - An empty `interfaces` means MultiWAN is not configured. For the configuration (`track_ip`, `reliability`, thresholds) use `get-uci-config` on `mwan3`, for the events `read-system-log` with `pattern` `mwan3`.
    DESCRIPTION)]
class Mwan3Status extends NsApiTool
{
    protected string $path = 'mwan3';

    protected string $method = 'status';

    /**
     * @return array<string, mixed>
     */
    protected function parameters(Request $request): array
    {
        $validated = $request->validate([
            'section' => ['in:interfaces,policies'],
        ]);

        return ['section' => $validated['section'] ?? 'interfaces'];
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...parent::schema($schema),
            'section' => $schema->string()
                ->enum(['interfaces', 'policies'])
                ->description('What to read, `interfaces` by default.'),
        ];
    }
}
