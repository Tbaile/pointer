<?php

namespace App\Mcp\Tools\NethServer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ns8-ping')]
#[Title('NS8 Ping')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Send ICMP echo requests from a NethServer 8 node to a target, to confirm live reachability rather than infer it from configuration.
    Only works on `nethserver` machines.

    - `reachable` is true when at least one reply came back; `output` has the per-packet replies and the loss/rtt summary, or the reason no packet was sent (e.g. `Name or service not known`, exit code 2).
    - A host may drop ICMP while serving TCP: confirm a negative with `ns8-tcp-probe` on a port it should serve, and locate where packets stop with `ns8-tracepath`.
    - To find the path MTU, send `size` with `dont_fragment` and lower it until replies come back: `size` 1472 fills a 1500-byte IPv4 packet.
    DESCRIPTION)]
class Ping extends NetworkProbeTool
{
    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'source_ip' => ['ip'],
            'count' => ['integer', 'min:1', 'max:5'],
            'size' => ['integer', 'min:0', 'max:65507'],
            'dont_fragment' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function command(array $validated): string
    {
        $command = sprintf('ping -n -c %d -W 2', $validated['count'] ?? 3);

        if (isset($validated['source_ip'])) {
            $command .= ' -I '.escapeshellarg($validated['source_ip']);
        }

        if (isset($validated['size'])) {
            $command .= ' -s '.(int) $validated['size'];
        }

        if ($validated['dont_fragment'] ?? false) {
            $command .= ' -M do';
        }

        return $command.' '.escapeshellarg($validated['target']);
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
            'source_ip' => $schema->string()
                ->description('Local address to send from, e.g. the node\'s VPN address `10.5.4.1`.'),
            'count' => $schema->integer()
                ->min(1)
                ->max(5)
                ->description('Number of echo requests, 3 by default.'),
            'size' => $schema->integer()
                ->min(0)
                ->max(65507)
                ->description('ICMP payload in bytes, 56 by default, e.g. `1472`.'),
            'dont_fragment' => $schema->boolean()
                ->description('Set the Don\'t Fragment bit, to probe the path MTU with `size`.'),
        ];
    }
}
