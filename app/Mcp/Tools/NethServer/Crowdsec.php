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
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

#[Title('CrowdSec')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Run a read-only `cscli` command in the container of a CrowdSec module of a NethServer 8 node and return its output.
    Only works on `nethserver` machines, for a CrowdSec instance on the node of the current session.

    - `decisions` with `ip`: the bans that apply to that address, including the community blocklist (`CAPI`) and the lists, and ranges containing it. CSV with `id`, `source`, `ip` (`Ip:<address>` or `Range:<cidr>`), `reason`, `action` (`ban`), `country`, `as`, `events_count`, `expiration`. `No decision for <ip>` means CrowdSec does not block it.
    - `decisions` without `ip`: the local decisions of the last 100 alerts, without the community blocklist.
    - `alerts`: the last 50 alerts (`ip` for one address), with `scope`, `value`, `reason` (the scenario, e.g. `crowdsecurity/ssh-slow-bf`), `decisions` and `created_at`.
    - `allowlists-check` (`ip` required): whether the address is in an allowlist, e.g. the Nethesis Threat Shield one. An allowlisted address is never banned.
    - `bouncers`: the bouncers pulling decisions, with `last_pull`. An old `last_pull` means bans are no longer applied to the firewall.
    - `metrics`: lines read and parsed per log source, alerts and decisions per scenario, as tables.
    - The CrowdSec settings (e.g. ban time, whitelisted networks): `module-environment`. Its logs: `read-logs`.
    DESCRIPTION)]
class Crowdsec extends Tool
{
    private const array COMMANDS = ['decisions', 'alerts', 'allowlists-check', 'bouncers', 'metrics'];

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'module' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'command' => ['required', 'in:'.implode(',', self::COMMANDS)],
            'ip' => ['required_if:command,allowlists-check', 'ip'],
        ]);

        $ip = $validated['ip'] ?? null;
        $cscli = 'podman exec '.escapeshellarg($validated['module']).' cscli --error ';

        $command = match ($validated['command']) {
            // With --all, -i filters by alert and returns the whole community blocklist: filter on the node instead
            'decisions' => $ip === null
                ? $cscli.'decisions list -o raw'
                : $cscli.'decisions list --all -l 0 -o raw 2>&1 | grep -F -e '.escapeshellarg('id,source,').' -e '.escapeshellarg(",Ip:{$ip},").' -e '.escapeshellarg(',Range:').' -e Error',
            'alerts' => $cscli.'alerts list -o raw --limit 50'.($ip === null ? '' : ' -i '.escapeshellarg($ip)),
            'allowlists-check' => $cscli.'allowlists check '.escapeshellarg((string) $ip),
            'bouncers' => $cscli.'bouncers list -o raw',
            default => $cscli.'metrics',
        };

        try {
            $result = $this->executor->run($validated['sos_id'], $command.' 2>&1');
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if ($validated['command'] === 'decisions' && $ip !== null) {
            return $this->decisionsFor($ip, $result->output);
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "cscli exited with code {$result->exitCode}");
        }

        return Response::text($result->output);
    }

    /**
     * Keep the header and the decisions on the address or on a range containing it.
     */
    private function decisionsFor(string $ip, string $output): Response
    {
        $header = null;
        $rows = [];

        foreach (explode("\n", trim($output)) as $line) {
            if (str_starts_with($line, 'Error')) {
                return Response::error($line);
            }

            if (str_starts_with($line, 'id,source,')) {
                $header = $line;

                continue;
            }

            $value = explode(',', $line, 4)[2] ?? '';

            if ($value === "Ip:{$ip}" || (str_starts_with($value, 'Range:') && IpUtils::checkIp($ip, substr($value, 6)))) {
                $rows[] = $line;
            }
        }

        if ($rows === []) {
            return Response::text("No decision for {$ip}.\n");
        }

        return Response::text($header."\n".implode("\n", $rows)."\n");
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
            'module' => $schema->string()
                ->description('CrowdSec instance id from `list-modules`, e.g. `crowdsec1`.')
                ->required(),
            'command' => $schema->string()
                ->enum(self::COMMANDS)
                ->description('What to read, e.g. `decisions`.')
                ->required(),
            'ip' => $schema->string()
                ->description('IPv4 or IPv6 address to check, e.g. `203.0.113.10`. Required for `allowlists-check`.'),
        ];
    }
}
