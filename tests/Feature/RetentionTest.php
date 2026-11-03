<?php

use App\Models\CommandClassification;
use App\Models\McpToolCall;

use function Pest\Laravel\artisan;

test('tool calls older than the retention are pruned', function () {
    $old = McpToolCall::factory()->create(['created_at' => now()->subDays(366)]);
    $recent = McpToolCall::factory()->create(['created_at' => now()->subDays(364)]);

    artisan('model:prune', ['--model' => McpToolCall::class])->assertSuccessful();

    expect(McpToolCall::find($old->id))->toBeNull()
        ->and(McpToolCall::find($recent->id))->not->toBeNull();
});

test('command classifications older than the retention are pruned', function () {
    $old = CommandClassification::factory()->create(['created_at' => now()->subDays(366)]);
    $recent = CommandClassification::factory()->create(['created_at' => now()->subDays(364)]);

    artisan('model:prune', ['--model' => CommandClassification::class])->assertSuccessful();

    expect(CommandClassification::find($old->id))->toBeNull()
        ->and(CommandClassification::find($recent->id))->not->toBeNull();
});
