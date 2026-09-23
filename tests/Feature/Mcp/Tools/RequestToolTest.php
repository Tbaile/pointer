<?php

use App\Enums\SystemType;
use App\Mcp\Tools\RequestTool;
use App\Models\ToolRequest;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * @param  array<string, mixed>  $overrides
 */
function requestTool(array $overrides = []): Response
{
    return (new RequestTool)->handle(new Request([
        'system_type' => 'nethsecurity',
        'title' => 'Read the OpenVPN server log',
        'description' => 'Return the last lines of the OpenVPN log to see why a tunnel drops.',
        ...$overrides,
    ]));
}

test('it records the tool request with one request', function () {
    $result = requestTool();

    $toolRequest = ToolRequest::sole();

    expect((string) $result->content())->toBe("Tool request {$toolRequest->id} recorded.");
    expect($toolRequest->system_type)->toBe(SystemType::NethSecurity);
    expect($toolRequest->title)->toBe('Read the OpenVPN server log');
    expect($toolRequest->requests_count)->toBe(1);
});

test('it rejects invalid input without storing anything', function (array $overrides) {
    expect(fn () => requestTool($overrides))->toThrow(ValidationException::class);

    expect(ToolRequest::count())->toBe(0);
})->with([
    'unknown product' => [['system_type' => 'windows']],
    'no title' => [['title' => null]],
    'no description' => [['description' => null]],
]);
