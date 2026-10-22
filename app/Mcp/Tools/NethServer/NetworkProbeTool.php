<?php

namespace App\Mcp\Tools\NethServer;

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
 * Tool probing a target from a NethServer 8 node.
 */
abstract class NetworkProbeTool extends Tool
{
    protected const string HOSTNAME_PATTERN = '/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*\.?$/';

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
            ...$this->rules(),
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], $this->command($validated).' 2>&1');
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'reachable' => $this->reachable($result->output, $result->exitCode),
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
            'target' => $schema->string()
                ->description('IP address or hostname to probe.')
                ->required(),
        ];
    }
}
