<?php

use App\Mcp\Tools\UpvoteToolRequest;
use App\Models\ToolRequest;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

test('it adds one to the request count', function () {
    $toolRequest = ToolRequest::factory()->create();

    $result = (new UpvoteToolRequest)->handle(new Request(['id' => $toolRequest->id]));

    expect((string) $result->content())->toBe("Tool request {$toolRequest->id} now requested 2 times.");
    expect($toolRequest->fresh()->requests_count)->toBe(2);
});

test('it rejects unknown and deleted tool requests', function () {
    $deleted = ToolRequest::factory()->create();
    $deleted->delete();

    expect(fn () => (new UpvoteToolRequest)->handle(new Request(['id' => $deleted->id])))->toThrow(ValidationException::class);
    expect(fn () => (new UpvoteToolRequest)->handle(new Request(['id' => $deleted->id + 1])))->toThrow(ValidationException::class);

    expect($deleted->fresh()->requests_count)->toBe(1);
});
