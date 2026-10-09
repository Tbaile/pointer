<?php

namespace App\Mcp\Tools\NethSecurity;

use App\Contracts\RemoteExecutor;
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

#[Title('Nft List')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    List the nftables ruleset of a NethSecurity firewall (`nft list`), with the rule and named counters.
    Only works on `nethsecurity` machines.

    - `object` `ruleset` (default): every table. `tables`: only the table names, e.g. `table inet fw4`, `table inet banIP`. `counters`: the named counters of every table.
    - `table`, `chain`, `set`: one object, by `family`, `table` and, for `chain` and `set`, `name`. E.g. `inet` `banIP` `_inbound` for Threat Shield IP's inbound drops.
    - Rules read like `ip saddr @blocklist.v4 counter packets 12 bytes 720 drop`: the counter tells how many packets the rule matched since it was loaded.
    - Set elements are left out: sets can hold hundreds of thousands of them. Pass `elements` with `object` `set` to read them.
    DESCRIPTION)]
class NftList extends Tool
{
    private const string NAME_PATTERN = 'regex:/^[A-Za-z0-9_.-]+$/';

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'object' => ['in:ruleset,tables,counters,table,chain,set'],
            'family' => ['required_if:object,table,chain,set', 'in:ip,ip6,inet,arp,bridge,netdev'],
            'table' => ['required_if:object,table,chain,set', 'string', self::NAME_PATTERN],
            'name' => ['required_if:object,chain,set', 'string', self::NAME_PATTERN],
            'elements' => ['boolean'],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], $this->buildCommand($validated));
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "nft exited with code {$result->exitCode}");
        }

        return Response::text($result->output);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function buildCommand(array $input): string
    {
        $object = $input['object'] ?? 'ruleset';
        $terse = $object === 'set' && ($input['elements'] ?? false) ? '' : ' -t';
        $command = "nft{$terse} list {$object}";

        if (in_array($object, ['table', 'chain', 'set'], true)) {
            $command .= ' '.$input['family'].' '.escapeshellarg($input['table']);
        }

        if (in_array($object, ['chain', 'set'], true)) {
            $command .= ' '.escapeshellarg($input['name']);
        }

        return $command.' 2>&1';
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
            'object' => $schema->string()
                ->enum(['ruleset', 'tables', 'counters', 'table', 'chain', 'set'])
                ->description('What to list, `ruleset` by default.'),
            'family' => $schema->string()
                ->enum(['ip', 'ip6', 'inet', 'arp', 'bridge', 'netdev'])
                ->description('Table family, required for `table`, `chain` and `set`.'),
            'table' => $schema->string()
                ->description('Table name, e.g. `fw4` or `banIP`. Required for `table`, `chain` and `set`.'),
            'name' => $schema->string()
                ->description('Chain or set name, e.g. `_inbound` or `blocklist.v4`. Required for `chain` and `set`.'),
            'elements' => $schema->boolean()
                ->description('With `object` `set`, list its elements too.'),
        ];
    }
}
