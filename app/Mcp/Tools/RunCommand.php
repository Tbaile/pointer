<?php

namespace App\Mcp\Tools;

use App\Contracts\RemoteExecutor;
use App\Events\CommandClassified;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Responses\Data\BooleanAnswer;
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

#[Title('Run Command')]
#[IsDestructive]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Run a shell command directly on the target machine, as a privileged user. This is a full-fledged command on a customer's production machine: there is no sandbox, and anything it changes is changed for real.
    Use it only as a last resort, when no other tool can answer: each tool's description names the commands it replaces.

    Keep the command as simple as possible, so a person can read it at a glance:
    - Fine: one command, optionally piped into a filter, e.g. `logread | grep openvpn`.
    - Not allowed: multi-line scripts, inline interpreter programs (`python -c`, `awk` programs, heredocs), chains of `;` or `&&` doing several things.
    - Bound the output: filter on a specific pattern and limit the lines, never grep a whole log or directory tree unfiltered.
    - NethSecurity runs BusyBox: many GNU options are missing.
    - Never write, restart or delete anything unless the user explicitly asked for it.
    - Never pass the machine's credentials on the command line.

    The output is returned as is: secrets in it are not masked. Avoid commands that print them, like dumping a container's environment or a VPN private key.

    Commands that only read system state run right away. Commands that change files, settings, services, packages or databases, or that print secrets or users' data, are refused: they go through `run-command-approved`, so the user can review them.
    DESCRIPTION)]
class RunCommand extends Tool
{
    /**
     * Probability of any risk at or above which the command needs the user's approval.
     */
    private const float RISK_THRESHOLD = 0.3;

    /**
     * What a refused command needs before it can go through `run-command-approved`.
     */
    private const string APPROVAL_STEPS = 'Call `search-tool-requests`, then `upvote-tool-request` on the request for the capability you are missing, or `request-tool` when there is none. Then call `run-command-approved` with the same `command` and that request\'s id as `tool_request_id`, so the user can review it.';

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'command' => ['required', 'string', 'max:1024'],
        ]);

        $questions = [
            'writes_files' => new Boolean('Does `command` create, overwrite, move or delete a file or directory?', [
                'true' => 'It writes to the filesystem, e.g. `rm`, `mv`, `cp`, `sed -i`, a `>` redirect into a file, `find -delete`',
                'false' => 'It only prints, e.g. `cat`, `grep`, `ls -l`, `find -exec ls -l {} +`',
            ]),
            'changes_settings' => new Boolean('Does `command` change a setting of the machine or of one of its modules?', [
                'true' => 'It sets or applies a value, e.g. `uci set`, `uci commit`, `sysctl -w`, `ethtool -s`, an `api-cli run` action that adds, alters or removes something',
                'false' => 'It only reads settings, e.g. `uci show`, `uci changes`, `sysctl <key>`, `ethtool <dev>`, an `api-cli run` action that lists or gets',
            ]),
            'affects_services' => new Boolean('Does `command` start, stop, restart, reload or kill a service, container or process?', [
                'true' => 'It acts on a running program, e.g. `systemctl restart`, `/etc/init.d/<service> reload`, `podman stop`, `kill`',
                'false' => 'It only reports their state, e.g. `systemctl status`, `systemctl --failed`, `podman ps`, `ps`',
            ]),
            'changes_packages' => new Boolean('Does `command` install, remove or upgrade software?', [
                'true' => 'e.g. `apk add`, `apk del`, `apk upgrade`, `dnf install`, `podman pull`',
                'false' => 'It only lists or inspects installed software, e.g. `apk info`, `apk list --installed`, `<binary> --version`',
            ]),
            'writes_database' => new Boolean('Does `command` modify data in a database?', [
                'true' => 'It runs a write statement such as `INSERT`, `UPDATE`, `DELETE`, `DROP` or `ALTER`, or writes or deletes a Redis key',
                'false' => 'It only runs `SELECT` queries or reads Redis keys',
            ]),
            'prints_secrets' => new Boolean('Can the output of `command` contain a password, private key, token or other credential?', [
                'true' => 'It prints a process or container environment (`env`), a key or credentials file, a Redis hash of a service registration, or a bind or API configuration without filtering it',
                'false' => 'Its source holds no credentials, like logs, status or sizes, or the output is filtered down to fields that hold none, e.g. `jq \'keys\'`',
            ]),
            'prints_user_data' => new Boolean('Does `command` print content that belongs to the customer\'s users?', [
                'true' => 'It prints mail headers or bodies (`doveadm fetch`), rows of an application database, a user\'s files or call records',
                'false' => 'It prints system data: logs, status, configuration, sizes or counts, e.g. table sizes from `information_schema`',
            ]),
        ];

        try {
            $classification = Classification::of([
                'machine' => 'A customer\'s production NethServer 8 node or NethSecurity firewall',
                'user' => 'root',
                'command' => $validated['command'],
            ])
                ->questions($questions)
                ->classify();

            $probabilities = collect($questions)
                ->map(fn (Boolean $question, string $risk) => $classification->answer($risk))
                ->ensure(BooleanAnswer::class)
                ->map->probability
                ->all();
        } catch (Throwable $exception) {
            CommandClassified::dispatch($validated['sos_id'], $request->user(), $validated['command'], error: $exception->getMessage());

            return Response::text('Not run: the command could not be classified. '.self::APPROVAL_STEPS);
        }

        $flagged = array_keys(array_filter($probabilities, fn (float $probability): bool => $probability >= self::RISK_THRESHOLD));

        CommandClassified::dispatch($validated['sos_id'], $request->user(), $validated['command'], $classification, $probabilities, $flagged);

        if ($flagged !== []) {
            return Response::text('Not run: flagged as '.implode(', ', $flagged).'. '.self::APPROVAL_STEPS);
        }

        try {
            $result = $this->executor->run($validated['sos_id'], $validated['command']);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'exit_code' => $result->exitCode,
            'output' => $result->output,
            'error_output' => $result->errorOutput,
        ]);
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
                ->description('One readable command line; a single pipe is fine, scripts are not.')
                ->required(),
        ];
    }
}
