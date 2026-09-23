<?php

namespace App\Mcp\Tools\NethSecurity;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Throwable;

#[Description('Get a baseline of a NethSecurity firewall: network devices by zone, firewall zones and zone forwardings.')]
class FirewallStatus extends NsApiTool
{
    /**
     * @var list<array{string, string}>
     */
    private const array CALLS = [
        ['ns.devices', 'list-devices'],
        ['ns.firewall', 'list_zones'],
        ['ns.firewall', 'list_forwardings'],
    ];

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $sosId = $request->string('sos_id');

        $status = [];

        foreach (self::CALLS as [$path, $method]) {
            try {
                $status["{$path} {$method}"] = $this->callApi($sosId, $path, $method);
            } catch (Throwable $exception) {
                $status["{$path} {$method}"] = ['error' => $exception->getMessage()];
            }
        }

        return Response::structured($status);
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
