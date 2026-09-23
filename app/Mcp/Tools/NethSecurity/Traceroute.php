<?php

namespace App\Mcp\Tools\NethSecurity;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Traceroute')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Trace the hops from a NethSecurity firewall to a target with UDP probes, one per hop, to find where packets stop.
    `reachable` is true when the last hop answering is the target itself; `output` lists one line per hop with its address and rtt, `*` when that hop did not answer.
    Intermediate routers often ignore probes, so a few `*` lines do not mean the path is broken; the hop after which every line is `*` is where to look.
    DESCRIPTION)]
class Traceroute extends NetworkProbeTool
{
    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'max_hops' => ['integer', 'min:1', 'max:15'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function command(array $validated): string
    {
        $command = sprintf('traceroute -n -q 1 -w 1 -m %d', $validated['max_hops'] ?? 15);

        if (isset($validated['source_ip'])) {
            $command .= ' -s '.escapeshellarg($validated['source_ip']);
        }

        return $command.' '.escapeshellarg($validated['target']);
    }

    protected function reachable(string $output, int $exitCode): bool
    {
        if ($exitCode !== 0 || preg_match('/^traceroute to \S+ \(([^)]+)\)/m', $output, $header) !== 1) {
            return false;
        }

        preg_match_all('/^\s*\d+\s+(\S+)/m', $output, $hops);

        return end($hops[1]) === $header[1];
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
            'max_hops' => $schema->integer()
                ->min(1)
                ->max(15)
                ->description('Maximum number of hops to probe, 15 by default.'),
        ];
    }
}
