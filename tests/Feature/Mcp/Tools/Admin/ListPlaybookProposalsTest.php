<?php

use App\Enums\ProposalStatus;
use App\Mcp\Tools\Admin\ListPlaybookProposals;
use App\Models\PlaybookProposal;
use Laravel\Mcp\Request;

test('it returns the proposals with their staleness', function () {
    $proposal = PlaybookProposal::factory()->create(['tags' => ['dns-resolution']]);
    $proposal->playbook->update(['body' => 'changed']);

    $result = (new ListPlaybookProposals)->handle(new Request([]))->getStructuredContent();

    expect($result['total'])->toBe(1);
    expect($result['proposals'][0])->toBe([
        'id' => $proposal->id,
        'playbook_id' => $proposal->playbook_id,
        'status' => 'pending',
        'stale' => true,
        'tags' => ['dns-resolution'],
        'reason' => $proposal->reason,
        'review_note' => null,
        'body' => $proposal->body,
    ]);
});

test('it filters by status', function () {
    $pending = PlaybookProposal::factory()->create();
    PlaybookProposal::factory()->reviewed(ProposalStatus::Rejected)->create();

    $result = (new ListPlaybookProposals)->handle(new Request(['status' => 'pending']))->getStructuredContent();

    expect(array_column($result['proposals'], 'id'))->toBe([$pending->id]);
});
