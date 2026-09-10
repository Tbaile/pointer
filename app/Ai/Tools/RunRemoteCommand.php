<?php

namespace App\Ai\Tools;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class RunRemoteCommand implements Tool
{
    public function __construct(
        private readonly RemoteExecutor $executor,
        private readonly string $sosId,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return "Run a shell command on the target machine and return its exit code, stdout, and stderr. Use this to inspect the system, gather logs, or check configuration in order to answer the user's request.";
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $command = $request->string('command');

        try {
            $result = $this->executor->run($this->sosId, $command);
        } catch (Throwable $exception) {
            return "Command execution failed: {$exception->getMessage()}";
        }

        return sprintf(
            "<result exit_code=\"%d\" truncated=\"%s\">\n<stdout>\n%s\n</stdout>\n<stderr>\n%s\n</stderr>\n</result>",
            $result['exit_code'],
            $result['truncated'] ? 'true' : 'false',
            $result['stdout'],
            $result['stderr'],
        );
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()
                ->description('The shell command to run on the target machine.')
                ->required(),
        ];
    }
}
