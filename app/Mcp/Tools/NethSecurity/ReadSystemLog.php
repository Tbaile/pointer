<?php

namespace App\Mcp\Tools\NethSecurity;

use App\Contracts\RemoteExecutor;
use App\Services\Privacy\SecretMasker;
use Closure;
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

#[Title('Read System Log')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the system log of a NethSecurity firewall, the messages rsyslog writes for every program (there is no `logread`).
    Only works on `nethsecurity` machines.

    - Each line reads `<Mon> <d> <hh:mm:ss> <host> <program>[<pid>]: <message>`, e.g. `Oct  9 10:36:22 fw dropbear[13023]: Child connection from 10.0.1.10`.
    - `pattern` is a Perl-compatible regex (`grep -P`), matched ignoring case, e.g. `(nginx|procd)\[\d+\]: .*bind\(\)`. Drop lines with a lookahead: `^(?!.*victoria-metrics).*ns-plug`.
    - Match a time window with the timestamp prefix, e.g. `^Oct +9 10:`. Days 1-9 are padded with two spaces.
    - Returns the last `lines` matching lines, 100 by default. Narrow the `pattern` rather than raising `lines`.
    - `source` `memory` (default): the log kept in RAM, lost on reboot. `storage`: the log kept on the data storage for a year, empty when no data storage is configured. Reading it is slower.
    - Passwords inside URLs (`https://<user>:<password>@...`) are masked.
    DESCRIPTION)]
class ReadSystemLog extends Tool
{
    public function __construct(
        private readonly RemoteExecutor $executor,
        private readonly SecretMasker $masker,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'source' => ['in:memory,storage'],
            'pattern' => ['string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                // grep reports a bad regex on stderr and the pipeline still exits 0, so check it here with the same PCRE engine
                if (is_string($value) && @preg_match("\x01{$value}\x01", '') === false) {
                    $fail('The pattern is not a valid Perl-compatible regex.');
                }
            }],
            'lines' => ['integer', 'min:1', 'max:500'],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], $this->buildCommand($validated));
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->errorOutput ?: $result->output) ?: "Reading the system log exited with code {$result->exitCode}");
        }

        return Response::text($this->masker->maskUrlCredentials($result->output));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function buildCommand(array $input): string
    {
        // busybox zcat stops at the first missing file and cannot read plain ones, so rotated files are split by compression
        $command = match ($input['source'] ?? 'memory') {
            'storage' => '{ zcat /mnt/data/log/messages-*.gz; cat /mnt/data/log/messages-???????? /mnt/data/log/messages; } 2>/dev/null',
            default => '{ zcat /var/log/messages.1.gz; cat /var/log/messages; } 2>/dev/null',
        };

        if (isset($input['pattern'])) {
            $command .= ' | grep -a -i -P -e '.escapeshellarg($input['pattern']);
        }

        return $command.' | tail -n '.escapeshellarg((string) ($input['lines'] ?? 100));
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
            'source' => $schema->string()
                ->enum(['memory', 'storage'])
                ->description('Which log to read, `memory` by default.'),
            'pattern' => $schema->string()
                ->max(500)
                ->description('Perl-compatible regex the lines must match, ignoring case. Omit it to read every line.'),
            'lines' => $schema->integer()
                ->min(1)
                ->max(500)
                ->description('Maximum number of lines, 100 by default.'),
        ];
    }
}
