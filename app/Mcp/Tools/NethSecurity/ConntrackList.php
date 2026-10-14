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

#[Title('Conntrack List')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    List the live connection tracking table of a NethSecurity firewall (`conntrack -L`), filtered on the machine.
    Only works on `nethsecurity` machines.

    - Each entry reads `udp 17 23 src=10.0.1.10 dst=8.8.8.8 sport=57584 dport=53 packets=1 bytes=71 src=8.8.8.8 dst=<wan-ip> sport=53 dport=57584 ... [ASSURED]`: protocol, seconds left, TCP state, then the original direction and the reply direction (after NAT).
    - `[UNREPLIED]` means no packet came back. `unreplied` keeps only those entries, e.g. a VPN whose UDP packets are never answered or SYNs dropped on the path.
    - Filters apply to the original direction: `src`/`dst` take an IP or a CIDR, `sport`/`dport` need a `protocol` with ports, `state` needs `tcp`.
    - Without `group_by`: the first `limit` entries, then `<n> entries matched`.
    - `group_by` `src`, `dst` or `dport`: `<count> <value>` for the top `limit` values, then `<n> entries matched, <m> distinct values`. E.g. `group_by` `src` finds the LAN host flooding the table.
    - This is the table right now. For history use `victoria-query` on `conntrack_ip_conntrack_count` and `conntrack_ip_conntrack_max`.
    DESCRIPTION)]
class ConntrackList extends Tool
{
    private const string ADDRESS_PATTERN = 'regex:/^[0-9A-Fa-f:.]+(\/[0-9]{1,3})?$/';

    private const string PORT_PROTOCOLS = 'tcp,udp,udplite,sctp,dccp';

    private const string LIST_PROGRAM = 'unreplied && !/\[UNREPLIED\]/ { next } { matched++ } matched <= limit { print } END { print matched + 0 " entries matched" }';

    private const string COUNT_PROGRAM = 'unreplied && !/\[UNREPLIED\]/ { next } { value = "none"; for (i = 1; i <= NF; i++) if (index($i, key "=") == 1) { value = substr($i, length(key) + 2); break } count[value]++ } END { for (value in count) print count[value], value }';

    private const string TOP_PROGRAM = '{ total += $1 } NR <= limit { print } END { print total + 0 " entries matched, " NR " distinct values" }';

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'family' => ['in:ipv4,ipv6'],
            'protocol' => ['in:tcp,udp,udplite,icmp,icmpv6,sctp,dccp,gre'],
            'src' => ['string', self::ADDRESS_PATTERN],
            'dst' => ['string', self::ADDRESS_PATTERN],
            'sport' => ['integer', 'min:1', 'max:65535', 'prohibited_unless:protocol,'.self::PORT_PROTOCOLS],
            'dport' => ['integer', 'min:1', 'max:65535', 'prohibited_unless:protocol,'.self::PORT_PROTOCOLS],
            'state' => ['in:NONE,SYN_SENT,SYN_RECV,ESTABLISHED,FIN_WAIT,CLOSE_WAIT,LAST_ACK,TIME_WAIT,CLOSE,LISTEN', 'prohibited_unless:protocol,tcp'],
            'unreplied' => ['boolean'],
            'group_by' => ['in:src,dst,dport'],
            'limit' => ['integer', 'min:1', 'max:1000'],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], $this->buildCommand($validated));
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->errorOutput ?: $result->output) ?: "conntrack exited with code {$result->exitCode}");
        }

        return Response::text($result->output);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function buildCommand(array $input): string
    {
        $command = 'set -o pipefail; conntrack -L';

        $options = [
            'family' => '-f',
            'protocol' => '-p',
            'src' => '-s',
            'dst' => '-d',
            'sport' => '--sport',
            'dport' => '--dport',
            'state' => '--state',
        ];

        foreach ($options as $field => $option) {
            if (isset($input[$field])) {
                $command .= " {$option} ".escapeshellarg((string) $input[$field]);
            }
        }

        $unreplied = escapeshellarg(($input['unreplied'] ?? false) ? '1' : '0');
        $limit = escapeshellarg((string) ($input['limit'] ?? 50));

        if (! isset($input['group_by'])) {
            return "{$command} | awk -v unreplied={$unreplied} -v limit={$limit} ".escapeshellarg(self::LIST_PROGRAM);
        }

        return "{$command} | awk -v unreplied={$unreplied} -v key=".escapeshellarg($input['group_by']).' '.escapeshellarg(self::COUNT_PROGRAM)
            ." | sort -rn | awk -v limit={$limit} ".escapeshellarg(self::TOP_PROGRAM);
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
            'family' => $schema->string()
                ->enum(['ipv4', 'ipv6'])
                ->description('Address family. Omit it for both.'),
            'protocol' => $schema->string()
                ->enum(['tcp', 'udp', 'udplite', 'icmp', 'icmpv6', 'sctp', 'dccp', 'gre'])
                ->description('Layer 4 protocol, e.g. `udp`.'),
            'src' => $schema->string()
                ->description('Source address or CIDR of the original direction, e.g. `10.0.1.10` or `10.0.1.0/24`.'),
            'dst' => $schema->string()
                ->description('Destination address or CIDR of the original direction, e.g. `203.0.113.5`.'),
            'sport' => $schema->integer()
                ->min(1)
                ->max(65535)
                ->description('Source port, e.g. `51820`. Needs `protocol` `tcp`, `udp`, `udplite`, `sctp` or `dccp`.'),
            'dport' => $schema->integer()
                ->min(1)
                ->max(65535)
                ->description('Destination port, e.g. `1194`. Needs `protocol` `tcp`, `udp`, `udplite`, `sctp` or `dccp`.'),
            'state' => $schema->string()
                ->enum(['NONE', 'SYN_SENT', 'SYN_RECV', 'ESTABLISHED', 'FIN_WAIT', 'CLOSE_WAIT', 'LAST_ACK', 'TIME_WAIT', 'CLOSE', 'LISTEN'])
                ->description('TCP state, e.g. `SYN_SENT`. Needs `protocol` `tcp`.'),
            'unreplied' => $schema->boolean()
                ->description('Keep only the `[UNREPLIED]` entries.'),
            'group_by' => $schema->string()
                ->enum(['src', 'dst', 'dport'])
                ->description('Count the entries per original source, destination or destination port, e.g. `src`, instead of listing them.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(1000)
                ->description('Maximum number of entries or values, 50 by default.'),
        ];
    }
}
