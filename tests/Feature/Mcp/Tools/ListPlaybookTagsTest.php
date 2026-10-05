<?php

use App\Enums\SystemType;
use App\Mcp\Tools\ListPlaybookTags;
use App\Models\Playbook;
use Laravel\Mcp\Request;

/**
 * @param  array<string, mixed>  $arguments
 * @return list<array{tag: string, uses: int}>
 */
function listPlaybookTags(array $arguments = []): array
{
    return (new ListPlaybookTags)->handle(new Request($arguments))->getStructuredContent()['tags'];
}

test('it lists tags with their usage, most used first', function () {
    Playbook::factory()->withTags(['vpn-down', 'certificate-expired'])->create();
    Playbook::factory()->withTags(['vpn-down'])->create();
    Playbook::factory()->withTags(['disk-full'])->create();

    expect(listPlaybookTags())->toBe([
        ['tag' => 'vpn-down', 'uses' => 2],
        ['tag' => 'certificate-expired', 'uses' => 1],
        ['tag' => 'disk-full', 'uses' => 1],
    ]);
});

test('it filters tags by product', function () {
    Playbook::factory()->withTags(['vpn-down'])->create();
    Playbook::factory()->withTags(['container-crash'])->create(['system_type' => SystemType::NethServer]);

    expect(listPlaybookTags(['system_type' => 'nethserver']))->toBe([
        ['tag' => 'container-crash', 'uses' => 1],
    ]);
});
