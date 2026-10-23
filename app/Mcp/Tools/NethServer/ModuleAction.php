<?php

namespace App\Mcp\Tools\NethServer;

use App\Contracts\RemoteExecutor;
use App\Services\Privacy\SecretMasker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use JsonException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Title('Module Action')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Run a read-only action of one NethServer 8 module instance (`api-cli run module/<module>/<action>`) and return its JSON output, with the values of keys that look like secrets (`password`, `secret`, `token`, `key`, ...) masked.
    Only works on `nethserver` machines, on the leader node.

    - `module` is the instance id from `list-modules`, e.g. `traefik1` or `mail1`. `data` is the action input as an object, only for the actions that take one.
    - `list-actions`: the actions the module has. The others below exist only on some modules: `Action <action> is not defined` means this module doesn't have it.
    - `get-configuration`: the module settings, the same fields its `configure-module` takes.
    - `get-status`: `services` with `active`, `enabled` and `failed`, `images` and `volumes`. Reliable only for rootless modules: for rootfull ones read the system units.
    - `list-service-providers`: the providers of a service seen by this module, `data` e.g. `{"service": "ldap", "transport": "tcp"}`.
    - Traefik module: `list-routes` (route names; `data` `{"expand_list": true}` adds `host`, `path`, `url`, `lets_encrypt`, `http2https` of each), `get-route` (`data` `{"instance": "<route>"}`, `{}` when there is no such route), `list-certificates` and `get-certificate` (`data` `{"fqdn": "<name>"}`: `type`, e.g. `selfsigned`, `obtained`, and `certificates` with the PEM certificate in base64; for its expiry use `ns8-tls-probe`).
    - NethVoice module: `get-ports-list`, the ports allocated to the instance. Mail module: `list-domains`.
    - `Validation errors` lists the `data` fields the action expects. `Client "module/<id>" was not found` means no such instance.
    - Logs of the module: `read-logs`. Its containers: `module-containers`. Who ran which action: `audit-log`.
    DESCRIPTION)]
class ModuleAction extends Tool
{
    private const array ACTIONS = [
        'list-actions',
        'get-configuration',
        'get-status',
        'list-service-providers',
        'list-routes',
        'get-route',
        'list-certificates',
        'get-certificate',
        'get-ports-list',
        'list-domains',
    ];

    public function __construct(
        private readonly RemoteExecutor $executor,
        private readonly SecretMasker $masker,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'module' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'action' => ['required', 'in:'.implode(',', self::ACTIONS)],
            'data' => ['array'],
        ]);

        $command = 'api-cli run '.escapeshellarg("module/{$validated['module']}/{$validated['action']}");

        if (isset($validated['data'])) {
            $command .= ' --data '.escapeshellarg((string) json_encode((object) $validated['data'], JSON_UNESCAPED_SLASHES));
        }

        try {
            $result = $this->executor->run($validated['sos_id'], $command.' 2>&1');
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "api-cli exited with code {$result->exitCode}");
        }

        try {
            $output = json_decode($result->output, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return Response::error(trim($result->output));
        }

        if (is_array($output)) {
            $output = $this->masker->maskSecretKeys($output);
        }

        return Response::text(json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
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
            'module' => $schema->string()
                ->description('Module instance id from `list-modules`, e.g. `traefik1`.')
                ->required(),
            'action' => $schema->string()
                ->enum(self::ACTIONS)
                ->description('Read-only action to run, e.g. `get-configuration`.')
                ->required(),
            'data' => $schema->object()
                ->description('Input of the action, e.g. `{"fqdn": "app.example.org"}` for `get-certificate`. Leave it out for actions without input.'),
        ];
    }
}
