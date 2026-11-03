<?php

namespace App\Mcp\Tools;

use App\Contracts\RemoteExecutor;
use App\Models\ToolRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Choice;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
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
    Use it only as a last resort, when no other tool can answer. Call `search-tool-requests` first, then `upvote-tool-request` on the request for the capability you are missing, or `request-tool` when there is none, and pass that request's id as `tool_request_id`. Each request covers one capability: a command for a different need gets its own request.

    Keep the command as simple as possible, so a person can read it at a glance:
    - Fine: one command, optionally piped into a filter, e.g. `logread | grep openvpn`.
    - Not allowed: multi-line scripts, inline interpreter programs (`python -c`, `awk` programs, heredocs), chains of `;` or `&&` doing several things.
    - Bound the output: filter on a specific pattern and limit the lines, never grep a whole log or directory tree unfiltered.
    - NethSecurity runs BusyBox: many GNU options are missing.
    - Never write, restart or delete anything unless the user explicitly asked for it.
    - Never call external services from the machine, and never pass the machine's credentials on the command line.

    The output is returned as is: secrets in it are not masked. Avoid commands that print them, like dumping a container's environment or a VPN private key.

    Commands that only read system state run right away. Commands that read sensitive data or change something are refused: call `run-command-approved` with the same arguments, so the user can review them.
    DESCRIPTION)]
class RunCommand extends Tool
{
    /**
     * Minimum probability of the `safe` verdict to run a command without asking the user.
     */
    private const float SAFE_THRESHOLD = 0.8;

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'tool_request_id' => ['required', 'integer', Rule::exists(ToolRequest::class, 'id')->withoutTrashed()],
            'command' => ['required', 'string', 'max:1024'],
        ]);

        try {
            $classification = Classification::of($validated['command'])
                ->question('risk', new Choice('This shell command runs as root on a customer\'s production NethServer 8 or NethSecurity machine. What does it do?', [
                    'safe' => 'Only reads system state: disk, memory, uptime, processes, service status, filtered logs',
                    'sensitive' => 'Reads secrets, credentials, keys, environment variables, databases, mailboxes or user data',
                    'destructive' => 'Writes, deletes, restarts, installs or changes configuration',
                ]))
                ->classify();
        } catch (Throwable) {
            return Response::text('Not run: the command could not be classified. Call `run-command-approved` with the same arguments, so the user can review it.');
        }

        /** @var ChoiceAnswer $risk */
        $risk = $classification->answer('risk');

        Log::channel('mcp')->info('run-command/classification', [
            'sos_id' => $validated['sos_id'],
            'command' => $validated['command'],
            'verdict' => $risk->choice,
            'probabilities' => $risk->probabilities,
            'model' => $classification->meta->model,
            'input_tokens' => $classification->usage->inputTokens,
            'output_tokens' => $classification->usage->outputTokens,
        ]);

        if ($risk->choice !== 'safe' || $risk->probabilityOf('safe') < self::SAFE_THRESHOLD) {
            return Response::text("Not run: the command wasn't confidently classified as safe (verdict: {$risk->choice}). Call `run-command-approved` with the same arguments, so the user can review it.");
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
            'tool_request_id' => $schema->integer()
                ->description('The id of the tool request for the missing capability, from `search-tool-requests` or `request-tool`.')
                ->required(),
            'command' => $schema->string()
                ->description('One readable command line; a single pipe is fine, scripts are not.')
                ->required(),
        ];
    }
}
