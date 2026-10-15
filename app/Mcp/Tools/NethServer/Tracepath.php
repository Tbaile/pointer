<?php

namespace App\Mcp\Tools\NethServer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ns8-tracepath')]
#[Title('NS8 Tracepath')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Trace the hops from a NethServer 8 node to a target with UDP probes (`tracepath`), to find where packets stop.
    Only works on `nethserver` machines.

    - `reachable` is true when the target answered (its line ends with `reached`); `output` lists one line per hop with its address and rtt, `no reply` when that hop did not answer, and the path MTU.
    - Many routers and hosts filter UDP probes, so a path can end in `no reply` lines while the target serves TCP: confirm with `ns8-tcp-probe` or `ns8-http-probe` before calling the path broken.
    - Each unanswered hop takes about 1.5 seconds, so 15 hops of `no reply` take about 25 seconds.
    DESCRIPTION)]
class Tracepath extends NetworkProbeTool
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
        return sprintf('tracepath -n -m %d ', $validated['max_hops'] ?? 15).escapeshellarg($validated['target']);
    }

    protected function reachable(string $output, int $exitCode): bool
    {
        return $exitCode === 0 && preg_match('/ reached\s*$/m', $output) === 1;
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
