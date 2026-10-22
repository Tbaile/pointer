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

#[Title('Audit Log')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the audit log of the NethServer 8 cluster API: who ran which action through the UI or the API, and when.
    Only works on `nethserver` machines, on the leader node.

    - One line per entry, newest first: `TIMESTAMP` (UTC), `USER` (an admin, or `module/<id>` for a module's own calls), `EVENT` (`create-task` or `login-ok`), `TASK` (the action run, e.g. `alter-repository`), `QUEUE` (`cluster/tasks`, `module/<id>/tasks` or `node/<id>/tasks`) and `DATA` (the action input as JSON, secrets masked).
    - `task` is a regex on the action, e.g. `(add|alter|remove)-repository|set-subscription`; `user` an exact user; `since` a date.
    - Actions run with `api-cli` from a shell, scripts that call other actions, and direct Redis edits leave no entry here; use `read-logs` for those.
    DESCRIPTION)]
class AuditLog extends Tool
{
    private const string SCRIPT = <<<'PYTHON'
        import json, re, sqlite3, sys
        task, user, since, limit = sys.argv[1], sys.argv[2], sys.argv[3], int(sys.argv[4])
        db = sqlite3.connect("file:/var/lib/nethserver/api-server/audit.db?mode=ro", uri=True)
        for timestamp, who, event, data in db.execute("SELECT timestamp, user, action, data FROM audit WHERE timestamp >= ? ORDER BY id DESC", (since,)):
            try:
                data = json.loads(data)
            except ValueError:
                data = None
            if not isinstance(data, dict):
                data = {}
            if (user and who != user) or (task and not re.search(task, data.get("action") or "")):
                continue
            print(json.dumps({"timestamp": timestamp, "user": who, "event": event, "task": data.get("action"), "queue": data.get("queue"), "data": data.get("data")}))
            limit -= 1
            if limit == 0:
                break
        PYTHON;

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
            'task' => ['string', 'max:200', 'regex:/^[A-Za-z0-9|()^$.*?_-]+$/'],
            'user' => ['string', 'max:100', 'regex:/^[A-Za-z0-9_.@\/-]+$/'],
            'since' => ['date_format:Y-m-d'],
            'limit' => ['integer', 'min:1', 'max:500'],
        ]);

        $command = 'python3 -c '.escapeshellarg(self::SCRIPT)
            .' '.escapeshellarg($validated['task'] ?? '')
            .' '.escapeshellarg($validated['user'] ?? '')
            .' '.escapeshellarg($validated['since'] ?? '')
            .' '.(int) ($validated['limit'] ?? 50)
            .' 2>&1';

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "python3 exited with code {$result->exitCode}");
        }

        $lines = [];

        foreach (explode("\n", trim($result->output)) as $line) {
            if ($line === '') {
                continue;
            }

            try {
                /** @var array{timestamp: string, user: string, event: string, task: ?string, queue: ?string, data: mixed} $entry */
                $entry = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return Response::error(trim($result->output));
            }

            $data = is_array($entry['data']) ? $this->masker->maskSecretKeys($entry['data']) : $entry['data'];

            $lines[] = implode("\t", [$entry['timestamp'], $entry['user'], $entry['event'], $entry['task'] ?? '-', $entry['queue'] ?? '-', json_encode($data, JSON_UNESCAPED_SLASHES)]);
        }

        if ($lines === []) {
            return Response::text('No audit entry matches.');
        }

        return Response::text("TIMESTAMP\tUSER\tEVENT\tTASK\tQUEUE\tDATA\n".implode("\n", $lines)."\n");
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
            'task' => $schema->string()
                ->description('Regex on the action run, e.g. `(add|alter|remove)-repository|set-subscription`.'),
            'user' => $schema->string()
                ->description('Only the entries of this user, e.g. `admin`.'),
            'since' => $schema->string()
                ->description('Only the entries from this date on, UTC, e.g. `2026-09-01`.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(500)
                ->description('How many entries to return at most, 50 by default.'),
        ];
    }
}
