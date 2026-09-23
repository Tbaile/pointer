<?php

namespace App\Mcp\Tools\NethSecurity;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description(<<<'DESCRIPTION'
    Read a single section of a UCI configuration of a NethSecurity firewall, as `{"values": {<option>: <value>, ...}}` with its `.name` and `.type`; list options are arrays. Secrets are masked.
    A section that does not exist yields the error "ubus did not return a JSON object". GetUciConfig reads every section of a configuration.
    DESCRIPTION)]
class GetUciSection extends NsApiTool
{
    protected string $path = 'uci';

    protected string $method = 'get';

    protected array $maskedFields = [
        'values.password',
        'values.passwd',
        'values.private_key',
        'values.preshared_key',
        'values.pre_shared_key',
        'values.secret',
        'values.token',
        'values.ssh_key',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function parameters(Request $request): array
    {
        return $request->validate([
            'config' => ['required', 'string', 'regex:/^[a-z0-9_-]+$/'],
            'section' => ['required', 'string', 'regex:/^(@[A-Za-z0-9_]+\[-?\d+\]|[A-Za-z0-9_]+)$/'],
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
            'section' => $schema->string()
                ->description('Section name, e.g. `RED` or `ns_lan`, or `@<type>[<index>]` for the n-th section of a type, e.g. `@zone[0]` or `@rule[-1]` for the last.')
                ->required(),
        ];
    }
}
