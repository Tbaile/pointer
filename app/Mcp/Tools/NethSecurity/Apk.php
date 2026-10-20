<?php

namespace App\Mcp\Tools\NethSecurity;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Throwable;

#[Title('Apk')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(true)]
#[Description(<<<'DESCRIPTION'
    Query the packages of a NethSecurity firewall with `apk query`, or refresh the repository index with `apk update`. Never installs or upgrades anything.
    Only works on `nethsecurity` machines.

    - `query` (default) returns a JSON list with one object per matching package, e.g. `[{"name": "ns-plug", "version": "1.4.1-r1"}]`. No match returns `[]`.
    - `packages`: names or globs, e.g. `["ns-plug"]` or `["ns-*"]`. Omit it for every package.
    - `filter`: `installed`, `upgradable` (a newer version is in the repository), `available` (in the repository) or `orphaned` (installed but no longer in the repository). Without it, both installed and repository packages match.
    - `all_matches`: every version of a package, not only the best one, e.g. the installed one and the one the repository offers.
    - `match`: what `packages` is matched against, `name` by default. `owner` takes absolute file paths and returns the package owning each file, e.g. `["/usr/libexec/migrate-to-my"]`. `depends` returns the packages depending on the given ones, `provides` the ones providing them.
    - `fields`: `name` and `version` by default. `scripts` lists the package's install/upgrade scripts by name, `contents` its files (large on many packages), `status` whether it is installed.
    - `upgradable` and `available` read the cached index: run `update` first to compare with what the repository offers right now.
    - `update` downloads the repository index. It prints nothing and returns `exit_code` `0` on success; on failure the output names the unreachable repository.
    DESCRIPTION)]
class Apk extends Tool
{
    private const array FIELDS = [
        'name', 'version', 'description', 'arch', 'license', 'origin', 'maintainer', 'url',
        'installed-size', 'file-size', 'depends', 'provides', 'replaces', 'install-if',
        'triggers', 'scripts', 'contents', 'status',
    ];

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'command' => ['in:query,update'],
            'packages' => ['array', 'min:1', 'max:50'],
            // a leading dash would be read as an apk option
            'packages.*' => ['string', 'max:255', 'regex:/^[A-Za-z0-9_.*+\/][A-Za-z0-9_.*+\/-]*$/'],
            'filter' => ['in:installed,upgradable,available,orphaned'],
            'all_matches' => ['boolean'],
            'match' => ['in:name,owner,provides,depends,origin'],
            'fields' => ['array', 'min:1'],
            'fields.*' => ['in:'.implode(',', self::FIELDS)],
        ]);

        $command = ($validated['command'] ?? 'query') === 'update'
            ? 'apk update --quiet 2>&1'
            : $this->queryCommand($validated);

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (($validated['command'] ?? 'query') === 'update') {
            return Response::structured([
                'exit_code' => $result->exitCode,
                'output' => $result->output,
            ]);
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "apk exited with code {$result->exitCode}");
        }

        return Response::text($result->output);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function queryCommand(array $validated): string
    {
        $command = 'apk query';

        if (isset($validated['filter'])) {
            $command .= ' --'.$validated['filter'];
        }

        if ($validated['all_matches'] ?? false) {
            $command .= ' --all-matches';
        }

        if (isset($validated['match'])) {
            $command .= ' --match '.$validated['match'];
        }

        $fields = implode(',', $validated['fields'] ?? ['name', 'version']);
        $packages = implode(' ', array_map(escapeshellarg(...), $validated['packages'] ?? ['*']));

        return $command.' --fields '.escapeshellarg($fields).' --format json '.$packages.' 2>&1';
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
            'command' => $schema->string()
                ->enum(['query', 'update'])
                ->description('`query` (default) to read packages, `update` to refresh the repository index. `update` ignores every other field.'),
            'packages' => $schema->array()
                ->items($schema->string())
                ->min(1)
                ->max(50)
                ->description('Package names or globs, e.g. `["ns-plug"]` or `["ns-*"]`; absolute file paths with `match` `owner`. Every package when omitted.'),
            'filter' => $schema->string()
                ->enum(['installed', 'upgradable', 'available', 'orphaned'])
                ->description('Restrict the packages, e.g. `upgradable`.'),
            'all_matches' => $schema->boolean()
                ->description('Return every version of a package instead of the best one, e.g. `true`.'),
            'match' => $schema->string()
                ->enum(['name', 'owner', 'provides', 'depends', 'origin'])
                ->description('What `packages` is matched against, `name` by default, e.g. `owner`.'),
            'fields' => $schema->array()
                ->items($schema->string()->enum(self::FIELDS))
                ->min(1)
                ->description('Fields of each package, `["name", "version"]` by default, e.g. `["name", "version", "scripts"]`.'),
        ];
    }
}
