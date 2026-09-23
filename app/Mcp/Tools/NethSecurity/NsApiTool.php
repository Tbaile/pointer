<?php

namespace App\Mcp\Tools\NethSecurity;

use App\Contracts\RemoteExecutor;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

abstract class NsApiTool extends Tool
{
    public function __construct(protected readonly RemoteExecutor $executor) {}

    /**
     * Call an ns-api method on the target machine through ubus and return its decoded result.
     *
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     *
     * @throws RuntimeException when the call fails or does not return JSON
     */
    protected function callApi(string $sosId, string $path, string $method, array $data = []): array
    {
        $command = sprintf(
            'ubus -S call %s %s %s',
            escapeshellarg($path),
            escapeshellarg($method),
            escapeshellarg(json_encode((object) $data, JSON_THROW_ON_ERROR)),
        );

        $decoded = json_decode($this->executor->run($sosId, $command), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('ubus did not return a JSON object.');
        }

        return $decoded;
    }
}
