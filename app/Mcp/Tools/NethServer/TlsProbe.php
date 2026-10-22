<?php

namespace App\Mcp\Tools\NethServer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ns8-tls-probe')]
#[Title('NS8 TLS Probe')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Open a TLS connection from a NethServer 8 node to a port of a target and return the certificate it serves (`openssl s_client`), without sending data.
    Only works on `nethserver` machines.

    - `reachable` is true when a certificate came back. `output` has its `subject`, `issuer`, `notBefore`/`notAfter` (GMT) and `Subject Alternative Name`, then `Hostname <name> does match certificate` or `does NOT match certificate`.
    - `name` is the name sent as SNI and checked against the certificate; it defaults to `target` when that is a hostname. A route served under another name gets the default certificate, e.g. a self-signed one for `host-<n>.ns8.test`.
    - The exit code is 1 when the name does NOT match, even with a certificate in `output`.
    - `Could not find certificate`: no TLS answer within 8 seconds, a refused port or a service that does not speak TLS. `ns8-tcp-probe` tells these apart.
    - `starttls` for services that start in clear text and upgrade, e.g. `smtp` on 25 or 587, `imap` on 143, `pop3` on 110, `sieve` on 4190, `ldap` on 389. Leave it out on ports that speak TLS from the start (443, 465, 993, 995, 636, 5061).
    - What Traefik holds for a name: `module-action` `get-certificate` on the Traefik module.
    DESCRIPTION)]
class TlsProbe extends NetworkProbeTool
{
    private const array STARTTLS = ['smtp', 'imap', 'pop3', 'sieve', 'ldap'];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'port' => ['integer', 'min:1', 'max:65535'],
            'name' => ['string', 'regex:'.self::HOSTNAME_PATTERN],
            'starttls' => ['in:'.implode(',', self::STARTTLS)],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function command(array $validated): string
    {
        $target = filter_var($validated['target'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? "[{$validated['target']}]" : $validated['target'];
        $name = $validated['name'] ?? (filter_var($validated['target'], FILTER_VALIDATE_IP) ? null : $validated['target']);

        $command = 'timeout 8 openssl s_client -connect '.escapeshellarg($target.':'.(int) ($validated['port'] ?? 443));

        if ($name !== null) {
            $command .= ' -servername '.escapeshellarg($name);
        }

        if (isset($validated['starttls'])) {
            $command .= ' -starttls '.escapeshellarg($validated['starttls']);
        }

        $command .= ' </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer -dates -ext subjectAltName';

        if ($name !== null) {
            $command .= ' -checkhost '.escapeshellarg($name);
        }

        return $command;
    }

    protected function reachable(string $output, int $exitCode): bool
    {
        return str_starts_with($output, 'subject=');
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...parent::schema($schema),
            'port' => $schema->integer()
                ->min(1)
                ->max(65535)
                ->description('TCP port of the TLS service. Defaults to `443`, e.g. `5061` for SIP/TLS.'),
            'name' => $schema->string()
                ->description('Name to send as SNI and check the certificate against, e.g. `app.example.org`. Defaults to `target` when it is a hostname.'),
            'starttls' => $schema->string()
                ->enum(self::STARTTLS)
                ->description('Protocol to upgrade with STARTTLS before TLS, e.g. `smtp` on port 587.'),
        ];
    }
}
