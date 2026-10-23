<?php

use App\Enums\ProposalStatus;
use App\Enums\SystemType;
use App\Mcp\Tools\GetPlaybook;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

test('it returns the playbook with its body and pending proposals', function () {
    $playbook = Playbook::factory()->withTags(['dns-resolution'])->create(['body' => '## Symptom']);
    $pending = PlaybookProposal::factory()->for($playbook)->create(['reason' => 'Added the adblock check.']);
    PlaybookProposal::factory()->for($playbook)->reviewed(ProposalStatus::Accepted)->create();
    PlaybookProposal::factory()->for($playbook)->reviewed(ProposalStatus::Rejected)->create();

    $result = (new GetPlaybook)->handle(new Request(['system_type' => 'nethsecurity', 'id' => $playbook->id]));

    expect($result->getStructuredContent())->toBe([
        'id' => $playbook->id,
        'title' => $playbook->title,
        'tags' => ['dns-resolution'],
        'body' => '## Symptom',
        'uses_count' => 1,
        'verdict' => null,
        'updated_at' => $playbook->updated_at->toDateString(),
        'pending_proposals' => [[
            'id' => $pending->id,
            'reason' => 'Added the adblock check.',
            'created_at' => $pending->created_at->toDateString(),
        ]],
    ]);
});

test('it rejects an unknown playbook or one on another product', function () {
    $nethserver = Playbook::factory()->create(['system_type' => SystemType::NethServer]);

    expect(fn () => (new GetPlaybook)->handle(new Request(['system_type' => 'nethsecurity', 'id' => $nethserver->id])))
        ->toThrow(ValidationException::class);
    expect(fn () => (new GetPlaybook)->handle(new Request(['system_type' => 'nethsecurity', 'id' => $nethserver->id + 1])))
        ->toThrow(ValidationException::class);
});
