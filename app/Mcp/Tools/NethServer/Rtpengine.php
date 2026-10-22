<?php

namespace App\Mcp\Tools\NethServer;

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

#[Title('RTPengine')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the media relay state of a NethVoice proxy module from its `rtpengine` container (`rtpengine-ctl list`).
    Only works on `nethserver` machines, for a NethVoice proxy instance on the node of the current session.

    - `numsessions`: calls whose media the proxy relays right now. Only calls signalled through the proxy (phones registered through it, trunks) go through RTPengine: phones registered straight to Asterisk don't show up here.
    - `totals`: rates of the running calls, then totals since RTPengine started (`Total number of 1-way streams`, `Total number of streams with no relayed packets`, timeouts), MOS, jitter and packet loss averages.
    - `session` with `call_id`: one call. A header (`callid`, `created`, `proxy`), then per leg `--- Tag '<tag>', type: ..., callduration <s>` and its media, one line per stream: `-------- Port <local address>:<port> <> <remote address>:<port>, SSRC <hex>, <n> p, <n> b, <n> e, <unix time> ts`, i.e. packets, bytes and errors received from the remote side and the time of the last one. A stream at `0 p` or with an old `ts` while the call is up is the direction with no audio; a remote private address on a phone behind NAT points at the address it advertises.
    - Report the legs as `leg-A`, `leg-B`: don't repeat the Call-ID, the tags or the remote addresses.
    - `Call ID not found` means the call ended or its media never went through the proxy.
    DESCRIPTION)]
class Rtpengine extends Tool
{
    private const array COMMANDS = ['numsessions', 'totals', 'session'];

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'module' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'command' => ['required', 'in:'.implode(',', self::COMMANDS)],
            'call_id' => ['required_if:command,session', 'string', 'max:255', 'not_in:all,own,foreign', 'regex:/^[A-Za-z0-9.!%*_+~()<>:\/\[\]?{}@=-]+$/'],
        ]);

        $list = $validated['command'] === 'session' ? 'sessions '.escapeshellarg($validated['call_id']) : $validated['command'];

        try {
            $result = $this->executor->run($validated['sos_id'], 'runagent -m '.escapeshellarg($validated['module']).' podman exec rtpengine rtpengine-ctl list '.$list.' 2>&1');
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "rtpengine-ctl exited with code {$result->exitCode}");
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
            'module' => $schema->string()
                ->description('NethVoice proxy instance id from `list-modules`, e.g. `nethvoice-proxy1`.')
                ->required(),
            'command' => $schema->string()
                ->enum(self::COMMANDS)
                ->description('What to read, e.g. `numsessions`.')
                ->required(),
            'call_id' => $schema->string()
                ->description('SIP Call-ID of one call, from the technician or the logs. Required for `session`.'),
        ];
    }
}
