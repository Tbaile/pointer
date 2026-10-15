<?php

namespace App\Mcp\Tools\NethServer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ns8-tcp-probe')]
#[Title('NS8 TCP Probe')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Open a TCP connection from a NethServer 8 node to a port of a target and close it at once, without sending data.
    Only works on `nethserver` machines.

    - `reachable` is true when the connection was accepted (exit code 0, empty `output`).
    - Exit code 1 with `Connection refused`: the host answered, nothing listens or a firewall rejects. With `Name or service not known`: the name does not resolve.
    - Exit code 124 with empty `output`: no answer within 3 seconds, packets dropped along the way or by the host.
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
        return 'timeout 3 bash -c \'</dev/tcp/$0/$1\' '.escapeshellarg($validated['target']).' '.(int) $validated['port'];
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
                ->description('TCP port to connect to, e.g. `443`.')
                ->required(),
        ];
    }
}
