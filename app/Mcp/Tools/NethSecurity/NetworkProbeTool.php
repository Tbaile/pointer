<?php

namespace App\Mcp\Tools\NethSecurity;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Tool probing a target from the firewall, optionally bound to a WAN through `mwan3 use`.
 */
abstract class NetworkProbeTool extends Tool
{
    private const string HOSTNAME_PATTERN = '/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*\.?$/';

    public function __construct(protected readonly RemoteExecutor $executor) {}

    /**
     * Validation rules of the arguments specific to the probe.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Command line of the probe, with every argument escaped.
     *
     * @param  array<string, mixed>  $validated
     */
    abstract protected function command(array $validated): string;

    /**
     * Whether the probe output shows the target was reached.
     */
    protected function reachable(string $output, int $exitCode): bool
    {
        return $exitCode === 0;
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'target' => ['required', 'string', Rule::anyOf([['ip'], ['regex:'.self::HOSTNAME_PATTERN]])],
            'interface' => ['string', 'regex:/^[A-Za-z0-9_]+$/'],
            'source_ip' => ['ip', 'prohibits:interface'],
            ...$this->rules(),
        ]);

        $command = $this->command($validated);

        if (isset($validated['interface'])) {
            $command = 'mwan3 use '.escapeshellarg($validated['interface']).' '.$command;
        }

        try {
            $result = $this->executor->run($validated['sos_id'], $command.' 2>&1');
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        $output = $result->output;

        if (isset($validated['interface'])) {
            $output = $this->stripMwan3Preamble($output, $validated['interface']);

            if ($output instanceof Response) {
                return $output;
            }
        }

        return Response::structured([
            'reachable' => $this->reachable($output, $result->exitCode),
            'exit_code' => $result->exitCode,
            'output' => $output,
        ]);
    }

    /**
     * Drop the lines `mwan3 use` prints before the probe runs, or report why it refused to run it.
     *
     * mwan3 prints its failures on stdout and exits 0, so they can only be told apart by their text.
     */
    private function stripMwan3Preamble(string $output, string $interface): string|Response
    {
        [$firstLine, $rest] = array_pad(explode("\n", $output, 2), 2, '');

        if (str_starts_with($firstLine, "Running '")) {
            return $rest;
        }

        if (str_starts_with($firstLine, 'could not find family for ')) {
            return Response::error("{$interface} is not an mwan3-managed interface: use source_ip to send from its address instead.");
        }

        return Response::error(trim($output) ?: "mwan3 could not bind to {$interface}.");
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
            'target' => $schema->string()
                ->description('IP address or hostname to probe.')
                ->required(),
            'interface' => $schema->string()
                ->description('mwan3-managed WAN logical interface to send through, e.g. `RED` or `wan2`, bypassing the MultiWAN policies. Omit it to follow the normal routing.'),
            'source_ip' => $schema->string()
                ->description('Local address to send from, e.g. the LAN address to test traffic that a VPN tunnel only matches from that subnet. Cannot be combined with `interface`.'),
        ];
    }
}
