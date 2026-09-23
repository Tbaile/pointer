<?php

use App\Enums\SystemType;
use App\Mcp\Tools\ListDiagnosisTags;
use App\Models\Diagnosis;
use Laravel\Mcp\Request;

/**
 * @param  array<string, mixed>  $arguments
 * @return list<array{tag: string, uses: int}>
 */
function listDiagnosisTags(array $arguments = []): array
{
    return (new ListDiagnosisTags)->handle(new Request($arguments))->getStructuredContent()['tags'];
}

test('it lists tags with their usage, most used first', function () {
    Diagnosis::factory()->withTags(['vpn-down', 'certificate-expired'])->create();
    Diagnosis::factory()->withTags(['vpn-down'])->create();
    Diagnosis::factory()->withTags(['disk-full'])->create();

    expect(listDiagnosisTags())->toBe([
        ['tag' => 'vpn-down', 'uses' => 2],
        ['tag' => 'certificate-expired', 'uses' => 1],
        ['tag' => 'disk-full', 'uses' => 1],
    ]);
});

test('it filters tags by product', function () {
    Diagnosis::factory()->withTags(['vpn-down'])->create();
    Diagnosis::factory()->withTags(['container-crash'])->create(['system_type' => SystemType::NethServer]);

    expect(listDiagnosisTags(['system_type' => 'nethserver']))->toBe([
        ['tag' => 'container-crash', 'uses' => 1],
    ]);
});
