<?php

use App\Mcp\Tools\RunCommandApproved;
use App\Models\ToolRequest;
use App\Services\Remote\CommandResult;
use Laravel\Ai\Classification;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

test('it runs the command without classifying it', function () {
    Classification::fake();
    $toolRequest = ToolRequest::factory()->create();
    $executor = new FakeRemoteExecutor(new CommandResult("ok\n", '', 0));

    $result = (new RunCommandApproved($executor))->handle(new Request([
        'sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000',
        'tool_request_id' => $toolRequest->id,
        'command' => 'runagent -m webtop1 mysql -e "select 1"',
    ]));

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 0, 'output' => "ok\n", 'error_output' => '']);
    expect($executor->calledWithCommand)->toBe('runagent -m webtop1 mysql -e "select 1"');
    Classification::assertNothingClassified();
});
