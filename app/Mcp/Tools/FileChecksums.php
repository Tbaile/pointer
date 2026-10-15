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

#[Title('File Checksums')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Checksum files on the machine: every regular file under the given paths, with size and modification time. Use it to compare a machine's files against a reference, e.g. an HA backup node against a backup of the primary.
    Works on `nethsecurity` and `nethserver` machines.

    - `paths`: absolute files or directories, e.g. `/etc/config`, `/etc/passwd`. Directories are walked recursively, without crossing into other filesystems. `/`, `/proc`, `/sys` and `/dev` are refused.
    - Each line reads `<yyyy-mm-dd> <hh:mm:ss> <size> <hash>  <path>`, sorted by path, e.g. `2026-10-08 16:45:04 1509 ff693e38014ba8118042b81bdd60fab7  /etc/config/network`. The time is the machine's local time.
    - At most 2000 lines: when you get exactly 2000, narrow the paths or use `aggregate`.
    - A path that does not exist is listed at the end as `find: ‘<path>’: No such file or directory`.
    - `aggregate`: one hash over every file instead, the same as `find <paths> -type f -exec md5sum {} + | LC_ALL=C sort -k2 | md5sum`. Run the same command on the reference to compare whole trees at once. Size and time are not part of it.
    - `algorithm`: `md5` (default) or `sha256`.
    DESCRIPTION)]
class FileChecksums extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'paths' => ['required', 'array', 'min:1', 'max:50'],
            // refuses the root, the kernel's virtual filesystems and `.`/`..` segments that would reach them
            'paths.*' => ['string', 'max:255', 'regex:/^\/[A-Za-z0-9_.\/-]*$/', 'not_regex:#^/(proc|sys|dev)?(/|$)|/\.\.?(/|$)#'],
            'algorithm' => ['in:md5,sha256'],
            'aggregate' => ['boolean'],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], $this->buildCommand($validated));
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->errorOutput ?: $result->output) ?: "Checksumming the files exited with code {$result->exitCode}");
        }

        return Response::text($result->output.$result->errorOutput);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function buildCommand(array $input): string
    {
        $paths = implode(' ', array_map(escapeshellarg(...), $input['paths']));
        $hash = ($input['algorithm'] ?? 'md5').'sum';

        if ($input['aggregate'] ?? false) {
            return "find {$paths} -xdev -type f -exec {$hash} {} + | LC_ALL=C sort -k2 | {$hash}";
        }

        return "find {$paths} -xdev -type f -printf '%TY-%Tm-%Td %TH:%TM:%.2TS %s ' -exec {$hash} {} ';' | LC_ALL=C sort -k5 | head -n 2000";
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
            'paths' => $schema->array()
                ->items($schema->string())
                ->min(1)
                ->max(50)
                ->description('Absolute files or directories to checksum, e.g. `["/etc/config", "/etc/passwd"]`.')
                ->required(),
            'algorithm' => $schema->string()
                ->enum(['md5', 'sha256'])
                ->description('Hash algorithm, `md5` by default.'),
            'aggregate' => $schema->boolean()
                ->description('Return one hash over every file instead of one line per file, e.g. `true`.'),
        ];
    }
}
