<?php

namespace App\Mcp\Tools;

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

#[Title('Disk Usage')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Show the size and usage of the mounted filesystems of the machine (`df`), in KiB.
    Works on `nethsecurity` and `nethserver` machines.

    - Columns: filesystem, type, size, used, available, use percentage, mount point.
    - `path`: only the filesystem holding that path, e.g. `/var/log`. `type`: only filesystems of that type, e.g. `tmpfs`.
    - `tmpfs` lives in RAM: on `nethsecurity`, `/tmp` (also `/var`, with the logs and, without data storage, the VictoriaMetrics data) fills memory as it grows. `/mnt/data` is the data storage.
    - On `nethserver`, the per-container mounts under `/var/lib/containers` are left out.
    - For usage over time use `victoria-query` on `disk_used` and `disk_used_percent`, labeled by `path` (on `nethsecurity` only `/`, `/boot` and `/mnt/data`).
    DESCRIPTION)]
class DiskUsage extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'path' => ['string', 'max:255', 'regex:/^\/[A-Za-z0-9_.\/-]*$/'],
            'type' => ['string', 'regex:/^[a-z0-9]+$/'],
        ]);

        $command = 'set -o pipefail; df -P -T -k';

        if (isset($validated['type'])) {
            $command .= ' -t '.escapeshellarg($validated['type']);
        }

        if (isset($validated['path'])) {
            $command .= ' '.escapeshellarg($validated['path']);
        }

        try {
            $result = $this->executor->run($validated['sos_id'], $command." | grep -v ' /var/lib/containers/'");
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->errorOutput ?: $result->output) ?: "df exited with code {$result->exitCode}");
        }

        return Response::text($result->output);
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
            'path' => $schema->string()
                ->description('Only the filesystem holding this absolute path, e.g. `/tmp`.'),
            'type' => $schema->string()
                ->description('Only filesystems of this type, e.g. `tmpfs`.'),
        ];
    }
}
