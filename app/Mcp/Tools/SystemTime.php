<?php

namespace App\Mcp\Tools;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Title('System Time')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Show the current date and time of the machine, with its UTC offset and time zone, and how far its clock is from Pointer's.
    Works on `nethsecurity` and `nethserver` machines.

    - Skew is the machine's clock minus Pointer's, in seconds: positive means the machine is ahead. A skew within the round trip is just the call's latency.
    - A skew of minutes or more breaks TLS certificate checks, TOTP codes, Kerberos/Samba logins and log correlation.
    Use it instead of `date`.
    DESCRIPTION)]
class SystemTime extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
        ]);

        $before = Carbon::now()->getTimestamp();

        try {
            $result = $this->executor->run($validated['sos_id'], "date +'%Y-%m-%dT%H:%M:%S%z %Z %s'");
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        $after = Carbon::now()->getTimestamp();

        if (! $result->successful()) {
            return Response::error(trim($result->errorOutput ?: $result->output) ?: "date exited with code {$result->exitCode}");
        }

        if (preg_match('/^(\S+) (\S+) (\d+)$/', trim($result->output), $matches) !== 1) {
            return Response::error('Unexpected date output: '.trim($result->output));
        }

        $skew = (int) round((int) $matches[3] - ($before + $after) / 2);
        $roundTrip = $after - $before;

        return Response::text(implode("\n", [
            "Machine: {$matches[1]} {$matches[2]}",
            'Pointer: '.Carbon::createFromTimestamp($after)->toIso8601String(),
            sprintf('Skew: %+ds (round trip %ds)', $skew, $roundTrip),
        ]));
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
        ];
    }
}
