<?php

namespace App\Mcp\Tools\NethSecurity;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('TCP Probe')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Open a TCP connection from a NethSecurity firewall to a port of a target and close it at once, without sending data.
    `reachable` is true when the connection was accepted; otherwise `output` tells `Connection refused` (the host answered, nothing listens or a firewall rejects) from `Operation timed out` (packets dropped along the way or by the host).
    DESCRIPTION)]
class TcpProbe extends NetworkProbeTool
{
    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function command(array $validated): string
    {
        $command = 'netcat -z -v -w 3';

        if (isset($validated['source_ip'])) {
            $command .= ' -s '.escapeshellarg($validated['source_ip']);
        }

        return $command.' '.escapeshellarg($validated['target']).' '.(int) $validated['port'];
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...parent::schema($schema),
            'port' => $schema->integer()
                ->min(1)
                ->max(65535)
                ->description('TCP port to connect to.')
                ->required(),
        ];
    }
}
