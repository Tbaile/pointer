<?php

use App\Enums\SystemType;
use App\Mcp\Tools\Admin\ListToolRequests;
use App\Models\ToolRequest;
use Laravel\Mcp\Request;

test('it lists the open tool requests on every product, most requested first', function () {
    $once = ToolRequest::factory()->create();
    $often = ToolRequest::factory()->create(['system_type' => SystemType::NethServer]);
    $often->increment('requests_count', 4);
    ToolRequest::factory()->create()->delete();

    $result = (new ListToolRequests)->handle(new Request([]));

    expect($result->getStructuredContent()['tool_requests'])->toBe([
        [
            'id' => $often->id,
            'system_type' => 'nethserver',
            'title' => $often->title,
            'description' => $often->description,
            'requests_count' => 5,
        ],
        [
            'id' => $once->id,
            'system_type' => 'nethsecurity',
            'title' => $once->title,
            'description' => $once->description,
            'requests_count' => 1,
        ],
    ]);
});
