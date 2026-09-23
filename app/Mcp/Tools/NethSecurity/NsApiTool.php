<?php

namespace App\Mcp\Tools\NethSecurity;

use App\Contracts\RemoteExecutor;
use App\Services\Privacy\SecretMasker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Tool backed by a single ns-api call, returned as structured content with its secrets masked.
 */
abstract class NsApiTool extends Tool
{
    protected string $path;

    protected string $method;

    /**
     * Arguments of the ubus call, validated from the request.
     *
     * @return array<string, mixed>
     */
    protected function parameters(Request $request): array
    {
        return [];
    }

    /**
     * Dotted paths into the response whose values are secrets, with `*` matching every item of a list.
     *
     * @var list<string>
     */
    protected array $maskedFields = [];

    public function __construct(
        protected readonly RemoteExecutor $executor,
        protected readonly SecretMasker $masker,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $command = sprintf(
            'ubus -S call %s %s %s',
            escapeshellarg($this->path),
            escapeshellarg($this->method),
            escapeshellarg(json_encode((object) $this->parameters($request), JSON_THROW_ON_ERROR)),
        );

        try {
            $output = $this->executor->run($request->string('sos_id'), $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return Response::error('ubus did not return a JSON object.');
        }

        return Response::structured($this->masker->mask($decoded, $this->maskedFields));
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
