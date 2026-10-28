<?php

use App\Enums\ProposalStatus;
use App\Mcp\Tools\Admin\SettlePlaybookProposal;
use App\Models\PlaybookProposal;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

use function Pest\Laravel\actingAs;

test('it settles the proposal as the caller without touching the playbook', function () {
    $user = User::factory()->create();
    $proposal = PlaybookProposal::factory()->create();
    $body = $proposal->playbook->body;

    actingAs($user);

    $result = (new SettlePlaybookProposal)->handle(new Request(['id' => $proposal->id, 'status' => 'accepted', 'review_note' => 'Folded into the rewrite.']));

    $proposal->refresh();

    expect((string) $result->content())->toBe("Proposal {$proposal->id} accepted.");
    expect($proposal->status)->toBe(ProposalStatus::Accepted);
    expect($proposal->review_note)->toBe('Folded into the rewrite.');
    expect($proposal->reviewed_by)->toBe($user->id);
    expect($proposal->reviewed_at)->not->toBeNull();
    expect($proposal->playbook->body)->toBe($body);
});

test('it refuses a proposal already settled', function () {
    $proposal = PlaybookProposal::factory()->reviewed(ProposalStatus::Rejected)->create();

    $result = (new SettlePlaybookProposal)->handle(new Request(['id' => $proposal->id, 'status' => 'accepted']));

    expect($result->isError())->toBeTrue();
    expect($proposal->fresh()->status)->toBe(ProposalStatus::Rejected);
});

test('it rejects pending as an outcome', function () {
    $proposal = PlaybookProposal::factory()->create();

    expect(fn () => (new SettlePlaybookProposal)->handle(new Request(['id' => $proposal->id, 'status' => 'pending'])))->toThrow(ValidationException::class);
});
