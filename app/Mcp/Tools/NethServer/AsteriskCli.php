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

#[Title('Asterisk CLI')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Run a read-only Asterisk CLI command in the `freepbx` container of a NethVoice module (`asterisk -rx`) and return its output.
    Only works on `nethserver` machines, for a NethVoice instance on the node of the current session.

    - `core show channels count`: active channels, active calls and calls processed since start. Run it right before suggesting any restart of Asterisk, FreePBX, the proxy or CTI: a non-zero `active calls` means the restart drops calls.
    - `core show uptime`: `System uptime` and `Last reload`. An uptime shorter than the module containers' one means Asterisk restarted on its own.
    - `core show version`: the Asterisk version.
    - `pjsip show registrations`: outbound registrations of the trunks, with the registrar, the auth object and the status (`Registered`, `Rejected`, `Unregistered`). `No objects found.` means no trunk registers.
    - `pjsip list endpoints`: how many endpoints are in each state (`Unavailable`, `Not in use`, `In use`, ...), never their names. `Unavailable` means no phone is registered for it.
    - `pjsip list contacts`: how many registered contacts are in each status (`Avail`, `Unavail`, `NonQual`, `Unknown`), never their addresses.
    - `Cannot find module <id> in the local node`: the instance runs on another node, see `list-modules` and `start-node-support`.
    - Containers and restarts of the instance: `module-containers`. Its logs: `read-logs`.
    DESCRIPTION)]
class AsteriskCli extends Tool
{
    private const array COMMANDS = [
        'core show channels count',
        'core show uptime',
        'core show version',
        'pjsip show registrations',
        'pjsip list endpoints',
        'pjsip list contacts',
    ];

    /**
     * Pattern capturing the state or status of each line, for the commands returned as counts.
     */
    private const array COUNTED = [
        'pjsip list endpoints' => '/^\s*Endpoint:.*\s{2,}([A-Za-z][A-Za-z ]*?)\s{2,}\d+ of \S+\s*$/',
        'pjsip list contacts' => '/^\s*Contact:.*\s[0-9a-f]{10}\s+(\S+)\s+\S+\s*$/',
    ];

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
        ]);

        $command = 'runagent -m '.escapeshellarg($validated['module']).' podman exec freepbx asterisk -rx '.escapeshellarg($validated['command']).' 2>&1';

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "asterisk exited with code {$result->exitCode}");
        }

        if (! isset(self::COUNTED[$validated['command']])) {
            return Response::text($result->output);
        }

        $counts = [];

        foreach (explode("\n", $result->output) as $line) {
            if (preg_match(self::COUNTED[$validated['command']], $line, $matches) === 1) {
                $counts[$matches[1]] = ($counts[$matches[1]] ?? 0) + 1;
            }
        }

        if ($counts === [] && ! str_contains($result->output, 'No objects found.')) {
            return Response::error('Unexpected output from asterisk, nothing could be counted.');
        }

        arsort($counts);

        $rows = array_map(fn (string $state, int $count): string => "{$state}\t{$count}", array_keys($counts), $counts);

        return Response::text("STATE\tCOUNT\n".implode("\n", [...$rows, "Total\t".array_sum($counts)])."\n");
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
                ->description('NethVoice instance id from `list-modules`, e.g. `nethvoice1`.')
                ->required(),
            'command' => $schema->string()
                ->enum(self::COMMANDS)
                ->description('Asterisk CLI command to run, e.g. `core show channels count`.')
                ->required(),
        ];
    }
}
