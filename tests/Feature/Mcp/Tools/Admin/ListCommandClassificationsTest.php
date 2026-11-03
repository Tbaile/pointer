<?php

use App\Mcp\Tools\Admin\ListCommandClassifications;
use App\Models\CommandClassification;
use Laravel\Mcp\Request;

/**
 * @param  array<string, mixed>  $arguments
 * @return array{total: int, classifications: list<array<string, mixed>>}
 */
function listCommandClassifications(array $arguments = []): array
{
    return (new ListCommandClassifications)->handle(new Request($arguments))->getStructuredContent();
}

test('it returns the classifications oldest first, paged, with the total', function () {
    $classifications = CommandClassification::factory()->count(3)->create();

    $page = listCommandClassifications(['offset' => 1, 'limit' => 1]);

    expect($page['total'])->toBe(3);
    expect($page['classifications'])->toHaveCount(1);
    expect($page['classifications'][0])->toMatchArray([
        'id' => $classifications[1]->id,
        'command' => $classifications[1]->command,
        'probabilities' => $classifications[1]->probabilities,
        'flagged' => [],
        'error' => null,
    ]);
});

test('it keeps only the flagged or failed commands', function () {
    CommandClassification::factory()->create();
    $flagged = CommandClassification::factory()->flagged()->create();
    $failed = CommandClassification::factory()->failed()->create();

    expect(array_column(listCommandClassifications(['flagged' => true])['classifications'], 'id'))->toBe([$flagged->id]);
    expect(array_column(listCommandClassifications(['errors' => true])['classifications'], 'id'))->toBe([$failed->id]);
});

test('it filters by command text and machine', function () {
    $grep = CommandClassification::factory()->create(['command' => 'logread | grep openvpn', 'sos_id' => 'sos-a']);
    CommandClassification::factory()->create(['command' => 'df -h', 'sos_id' => 'sos-a']);

    expect(array_column(listCommandClassifications(['contains' => 'grep'])['classifications'], 'id'))->toBe([$grep->id]);
    expect(listCommandClassifications(['sos_id' => 'sos-a'])['total'])->toBe(2);
});
