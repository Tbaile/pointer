<?php

namespace App\Mcp\Tools\NethServer;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Name('ns8-http-probe')]
#[Title('NS8 HTTP Probe')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Send a GET request from a NethServer 8 node to an HTTP or HTTPS URL and discard the body (`curl`), to check outbound web access or a site's answer from the node.
    Only works on `nethserver` machines.

    - `reachable` is true when an HTTP answer came back, whatever its status; `output` is the status code and the address connected to, e.g. `301 35.214.216.68`.
    - Otherwise `output` has the curl error and `000`, e.g. `curl: (6) Could not resolve host`, `(7) Failed to connect`, `(28)` timeout after 10 seconds, `(60)` untrusted certificate.
    - Redirects are not followed: a `301`/`302` is an answer.
    DESCRIPTION)]
class HttpProbe extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
        ]);

        $command = 'curl -sS --proto =http,https -o /dev/null -m 10 -w \'%{http_code} %{remote_ip}\n\' '.escapeshellarg($validated['url']).' 2>&1';

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'reachable' => $result->exitCode === 0,
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
            'url' => $schema->string()
                ->description('HTTP or HTTPS URL to request, e.g. `https://www.repubblica.it`.')
                ->required(),
        ];
    }
}
