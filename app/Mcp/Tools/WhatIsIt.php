<?php

namespace App\Mcp\Tools;

use App\Contracts\RemoteExecutor;
use App\Enums\SystemType;
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

#[Title('Identify Product')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('Identify which product the target machine runs.')]
class WhatIsIt extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $sosId = $request->string('sos_id');

        try {
            $osRelease = $this->executor->run($sosId, 'cat /etc/os-release')->throw()->output;
        } catch (Throwable $exception) {
            return Response::error("Could not read /etc/os-release: {$exception->getMessage()}");
        }

        preg_match('/^ID=(.*)$/m', $osRelease, $matches);

        $id = trim($matches[1] ?? '', " \t\n\r\0\x0B\"'");

        if ($id === '') {
            return Response::error('/etc/os-release has no ID field.');
        }

        if ($id === 'nethsecurity') {
            return Response::text(SystemType::NethSecurity->value);
        }

        try {
            $hasCoreEnv = $this->executor->run($sosId, 'cat /etc/nethserver/core.env')->successful();
        } catch (Throwable $exception) {
            return Response::error("Could not read /etc/nethserver/core.env: {$exception->getMessage()}");
        }

        if (! $hasCoreEnv) {
            return Response::error("Unsupported system: {$id}");
        }

        return Response::text(SystemType::NethServer->value);
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
