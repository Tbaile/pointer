<?php

namespace App\Mcp\Tools\NethSecurity;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Read UCI Configuration')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the sections of a UCI configuration of a NethSecurity firewall, e.g. `network`, `firewall`, `mwan3`, `dhcp`, `openvpn`, `ipsec`, `fstab`, as `{"values": {<section>: {<option>: <value>, ...}}}`. Each section carries its `.name`, `.type` and `.index`; list options are arrays. Secrets are masked.
    Narrow large configurations with `type` and/or `match`, e.g. type `rule` with match `{"target": "DROP"}` in `firewall`. ListUciConfigs lists every configuration present, GetUciSection reads a single section.
    DESCRIPTION)]
class GetUciConfig extends NsApiTool
{
    protected string $path = 'uci';

    protected string $method = 'get';

    protected array $maskedFields = [
        'values.*.password',
        'values.*.passwd',
        'values.*.private_key',
        'values.*.preshared_key',
        'values.*.pre_shared_key',
        'values.*.secret',
        'values.*.token',
        'values.*.ssh_key',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function parameters(Request $request): array
    {
        return $request->validate([
            'config' => ['required', 'string', 'regex:/^[a-z0-9_-]+$/'],
            'type' => ['string', 'regex:/^[A-Za-z0-9_]+$/'],
            'match' => ['array', 'min:1'],
            'match.*' => ['string'],
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
            ...parent::schema($schema),
            'config' => $schema->string()
                ->description('Name of the UCI configuration, i.e. the file name under /etc/config.')
                ->required(),
            'type' => $schema->string()
                ->description('Only sections of this type, e.g. `interface`, `zone`, `rule`, `redirect`.'),
            'match' => $schema->object()
                ->description('Only sections whose options equal all of these values, e.g. `{"proto": "dhcp"}`.'),
        ];
    }
}
