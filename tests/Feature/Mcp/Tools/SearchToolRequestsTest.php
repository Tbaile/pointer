<?php

use App\Enums\SystemType;
use App\Mcp\Tools\SearchToolRequests;
use App\Models\ToolRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

/**
 * @param  array<string, mixed>  $arguments
 * @return list<int>
 */
function searchToolRequestIds(array $arguments = []): array
{
    $result = (new SearchToolRequests)->handle(new Request(['system_type' => 'nethsecurity', ...$arguments]));

    expect($result)->toBeInstanceOf(ResponseFactory::class);

    return array_column($result->getStructuredContent()['tool_requests'], 'id');
}

test('it returns the tool requests with their count', function () {
    $toolRequest = ToolRequest::factory()->create(['title' => 'Read the OpenVPN log']);

    $result = (new SearchToolRequests)->handle(new Request(['system_type' => 'nethsecurity']));

    expect($result->getStructuredContent()['tool_requests'])->toBe([[
        'id' => $toolRequest->id,
        'title' => 'Read the OpenVPN log',
        'description' => $toolRequest->description,
        'requests_count' => 1,
    ]]);
});

test('it matches free text against title and description', function () {
    $byTitle = ToolRequest::factory()->create(['title' => 'Read the dnsmasq log']);
    $byDescription = ToolRequest::factory()->create(['description' => 'Check whether dnsmasq is running.']);
    ToolRequest::factory()->create();

    expect(searchToolRequestIds(['query' => 'dnsmasq']))->toEqualCanonicalizing([$byTitle->id, $byDescription->id]);
});

test('it only returns tool requests on the same product', function () {
    $nethsecurity = ToolRequest::factory()->create();
    ToolRequest::factory()->create(['system_type' => SystemType::NethServer]);

    expect(searchToolRequestIds())->toBe([$nethsecurity->id]);
});

test('it never returns deleted tool requests', function () {
    $kept = ToolRequest::factory()->create();
    ToolRequest::factory()->create()->delete();

    expect(searchToolRequestIds())->toBe([$kept->id]);
});

test('it ranks the most requested first', function () {
    $once = ToolRequest::factory()->create();
    $often = ToolRequest::factory()->create();
    $often->increment('requests_count', 4);

    expect(searchToolRequestIds())->toBe([$often->id, $once->id]);
});

test('it respects the limit', function () {
    ToolRequest::factory()->count(3)->create();

    expect(searchToolRequestIds(['limit' => 2]))->toHaveCount(2);
});
