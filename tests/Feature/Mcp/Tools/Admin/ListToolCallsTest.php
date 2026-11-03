<?php

use App\Mcp\Tools\Admin\ListToolCalls;
use App\Models\McpToolCall;
use Laravel\Mcp\Request;

/**
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function listToolCalls(array $arguments = []): array
{
    return (new ListToolCalls)->handle(new Request($arguments))->getStructuredContent();
}

test('it returns the calls oldest first, paged, with the total', function () {
    $toolCalls = McpToolCall::factory()->count(3)->create();

    $page = listToolCalls(['offset' => 1, 'limit' => 1]);

    expect($page['total'])->toBe(3);
    expect($page['tool_calls'])->toHaveCount(1);
    expect($page['tool_calls'][0])->toMatchArray([
        'id' => $toolCalls[1]->id,
        'tool' => $toolCalls[1]->tool,
        'session_id' => $toolCalls[1]->session_id,
        'arguments' => $toolCalls[1]->arguments,
        'success' => true,
    ]);
});

test('it filters by tool, session, success and time range', function () {
    $runCommand = McpToolCall::factory()->create(['tool' => 'run-command', 'session_id' => 'session-a']);
    $failed = McpToolCall::factory()->failed()->create(['tool' => 'disk-usage', 'session_id' => 'session-a']);
    $old = McpToolCall::factory()->create(['tool' => 'disk-usage', 'created_at' => now()->subDays(10)]);

    expect(array_column(listToolCalls(['tool' => 'run-command'])['tool_calls'], 'id'))->toBe([$runCommand->id]);
    expect(array_column(listToolCalls(['session_id' => 'session-a'])['tool_calls'], 'id'))->toBe([$runCommand->id, $failed->id]);
    expect(array_column(listToolCalls(['success' => false])['tool_calls'], 'id'))->toBe([$failed->id]);
    expect(array_column(listToolCalls(['to' => now()->subDays(5)->toIso8601String()])['tool_calls'], 'id'))->toBe([$old->id]);
});

test('it counts calls and failures per tool, most called first', function () {
    McpToolCall::factory()->count(2)->create(['tool' => 'run-command']);
    McpToolCall::factory()->failed()->create(['tool' => 'run-command']);
    McpToolCall::factory()->create(['tool' => 'disk-usage']);

    $result = listToolCalls(['group_by' => 'tool']);

    expect($result['total'])->toBe(2);
    expect($result['groups'])->toBe([
        ['tool' => 'run-command', 'calls' => 3, 'failures' => 1],
        ['tool' => 'disk-usage', 'calls' => 1, 'failures' => 0],
    ]);
});
