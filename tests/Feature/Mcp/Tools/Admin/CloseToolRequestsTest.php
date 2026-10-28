<?php

use App\Mcp\Tools\Admin\CloseToolRequests;
use App\Models\ToolRequest;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

test('it soft-deletes the given tool requests', function () {
    [$first, $second, $kept] = ToolRequest::factory()->count(3)->create();

    $result = (new CloseToolRequests)->handle(new Request(['ids' => [$first->id, $second->id]]));

    expect((string) $result->content())->toBe("Closed tool requests {$first->id}, {$second->id}.");
    expect(ToolRequest::pluck('id')->all())->toBe([$kept->id]);
    expect(ToolRequest::withTrashed()->count())->toBe(3);
});

test('it rejects an already closed tool request', function () {
    $closed = ToolRequest::factory()->create();
    $closed->delete();

    expect(fn () => (new CloseToolRequests)->handle(new Request(['ids' => [$closed->id]])))->toThrow(ValidationException::class);
});
