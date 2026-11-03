<?php

namespace App\Mcp\Tools;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Title('Packet Capture')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Capture packets on the machine with tcpdump, for up to 10 minutes, and read them back.
    Works on `nethsecurity` and `nethserver` machines.

    - A tool call can't wait for a capture: `start` runs it in the background and returns its `capture_id` at once. Call `read` with it as often as needed, during the capture and after it ends.
    - `start` takes the `interface` (`any` by default), a tcpdump `filter`, the `duration` in seconds and `max_packets`: the capture ends at whichever comes first. One capture at a time per machine: `start` fails while another one runs.
    - For rare events (DHCP renewals, a client reconnecting), start a long capture, ask the user to reproduce the problem, then `read`.
    - `read` returns `running`, `capture_log` (tcpdump's own messages, ending with `<n> packets captured` and `<n> packets dropped by kernel` once it ends) and `packets`: the first `count` packets, one header line each (`-nn -q -e`), e.g. `09:49:04.295390 eth1  Out ifindex 3 92:13:4e:79:f2:1f 10.110.80.3.53588 > 10.110.95.254.53: UDP, length 29`, i.e. interface, direction, source MAC, addresses, ports, protocol and length. Narrow it with a `filter` on `read` too, the capture stays the same.
    - `decode` adds the decoded protocol fields (`-vv`), e.g. DHCP options, DNS answers or IKE exchanges. It also prints application content such as SIP or HTTP headers, credentials included: decode only when the header lines can't answer, and narrow the `filter` to the packets you need.
    - `stop` ends the capture and removes it, so `read` what you need first. Captures are removed an hour after their last packet anyway.
    - tcpdump sees packets before nftables drops them on the way in and after NAT on the way out.
    DESCRIPTION)]
class PacketCapture extends Tool
{
    private const array ACTIONS = ['start', 'read', 'stop'];

    private const string PACKETS_SEPARATOR = '--- pointer packets ---';

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'action' => ['required', 'in:'.implode(',', self::ACTIONS)],
            'capture_id' => ['required_unless:action,start', 'prohibited_if:action,start', 'string', 'regex:/^[A-Za-z0-9]{16}$/'],
            'interface' => ['prohibited_unless:action,start', 'string', 'max:15', 'regex:/^[A-Za-z0-9_.@-]+$/'],
            'filter' => ['prohibited_if:action,stop', 'string', 'max:500'],
            'duration' => ['prohibited_unless:action,start', 'integer', 'min:1', 'max:600'],
            'max_packets' => ['prohibited_unless:action,start', 'integer', 'min:1', 'max:10000'],
            'count' => ['prohibited_unless:action,read', 'integer', 'min:1', 'max:500'],
            'decode' => ['prohibited_unless:action,read', 'boolean'],
        ]);

        try {
            return match ($validated['action']) {
                'start' => $this->start($validated),
                'read' => $this->read($validated),
                'stop' => $this->stop($validated),
            };
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function start(array $validated): Response|ResponseFactory
    {
        $captureId = Str::random(16);
        $files = $this->files($captureId);

        $tcpdump = sprintf(
            'setsid timeout %d tcpdump -i %s -n -s 1500 -c %d -U -w %s',
            $validated['duration'] ?? 60,
            escapeshellarg($validated['interface'] ?? 'any'),
            $validated['max_packets'] ?? 1000,
            $files['pcap'],
        );

        if (isset($validated['filter'])) {
            $tcpdump .= ' -- '.escapeshellarg($validated['filter']);
        }

        $command = "find /tmp -maxdepth 1 -name 'pointer-capture-*' -mmin +60 -delete; "
            ."running=$(pgrep -af 'tcpdump .*-w /tmp/pointer-capture-' | grep -o 'pointer-capture-[A-Za-z0-9]\\{16\\}' | head -n 1); "
            .'if [ -n "$running" ]; then echo "busy ${running#pointer-capture-}"; '
            ."else {$tcpdump} </dev/null >{$files['log']} 2>&1 & sleep 1; cat {$files['log']}; "
            ."grep -q 'listening on' {$files['log']} || rm -f {$files['pcap']} {$files['log']}; fi";

        $output = $this->executor->run($validated['sos_id'], $command)->output;

        if (preg_match('/^busy (\S+)/', $output, $busy) === 1) {
            return Response::error("Capture {$busy[1]} is still running on this machine: read and stop it, or wait for it to end.");
        }

        if (! str_contains($output, 'listening on')) {
            return Response::error(trim($output) ?: 'tcpdump did not start.');
        }

        return Response::structured([
            'capture_id' => $captureId,
            'capture_log' => $output,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function read(array $validated): Response|ResponseFactory
    {
        $files = $this->files($validated['capture_id']);

        $tcpdump = sprintf(
            'tcpdump -r %s -nn %s -e -c %d',
            $files['pcap'],
            ($validated['decode'] ?? false) ? '-vv' : '-q',
            $validated['count'] ?? 50,
        );

        if (isset($validated['filter'])) {
            $tcpdump .= ' -- '.escapeshellarg($validated['filter']);
        }

        $command = "if [ ! -f {$files['pcap']} ]; then echo missing; "
            ."else pgrep -f {$files['pcap']} >/dev/null && echo running || echo ended; cat {$files['log']}; "
            .'echo '.escapeshellarg(self::PACKETS_SEPARATOR)."; {$tcpdump} 2>&1; fi";

        $output = $this->executor->run($validated['sos_id'], $command)->output;

        if (! str_contains($output, self::PACKETS_SEPARATOR)) {
            return Response::error("No capture {$validated['capture_id']} on this machine: it was stopped, or removed an hour after it ended.");
        }

        [$header, $packets] = explode(self::PACKETS_SEPARATOR."\n", $output, 2);
        [$state, $captureLog] = array_pad(explode("\n", $header, 2), 2, '');

        return Response::structured([
            'running' => $state === 'running',
            'capture_log' => $captureLog,
            'packets' => $packets,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stop(array $validated): Response
    {
        $files = $this->files($validated['capture_id']);

        $this->executor->run(
            $validated['sos_id'],
            "kill $(pgrep -f {$files['pcap']}) 2>/dev/null; rm -f {$files['pcap']} {$files['log']}",
        );

        return Response::text("Capture {$validated['capture_id']} stopped and removed.");
    }

    /**
     * @return array{pcap: string, log: string}
     */
    private function files(string $captureId): array
    {
        return [
            'pcap' => "/tmp/pointer-capture-{$captureId}.pcap",
            'log' => "/tmp/pointer-capture-{$captureId}.log",
        ];
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
            'action' => $schema->string()
                ->enum(self::ACTIONS)
                ->description('`start` a capture, `read` it or `stop` and remove it.')
                ->required(),
            'capture_id' => $schema->string()
                ->description('Id returned by `start`. Required for `read` and `stop`.'),
            'interface' => $schema->string()
                ->max(15)
                ->description('With `start`, the device to capture on, e.g. `br-lan` or `eth1`, from `network-state`. `any` by default.'),
            'filter' => $schema->string()
                ->max(500)
                ->description('tcpdump filter expression, e.g. `port 67 or port 68` or `host 203.0.113.5 and udp`. On `start` it limits what is captured, on `read` what is returned.'),
            'duration' => $schema->integer()
                ->min(1)
                ->max(600)
                ->description('With `start`, seconds before the capture ends, 60 by default.'),
            'max_packets' => $schema->integer()
                ->min(1)
                ->max(10000)
                ->description('With `start`, packets after which the capture ends, 1000 by default.'),
            'count' => $schema->integer()
                ->min(1)
                ->max(500)
                ->description('With `read`, maximum number of packets returned, 50 by default.'),
            'decode' => $schema->boolean()
                ->description('With `read`, add the decoded protocol fields and application content to each packet. Off by default.'),
        ];
    }
}
