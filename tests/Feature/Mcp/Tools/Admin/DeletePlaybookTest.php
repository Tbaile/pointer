<?php

use App\Enums\ProposalStatus;
use App\Mcp\Tools\Admin\DeletePlaybook;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use Laravel\Mcp\Request;

test('it deletes the playbook and adds its uses to the one it was merged into', function () {
    $survivor = Playbook::factory()->create();
    $survivor->forceFill(['uses_count' => 3])->save();
    $absorbed = Playbook::factory()->create();
    $absorbed->forceFill(['uses_count' => 2])->save();
    PlaybookProposal::factory()->reviewed(ProposalStatus::Accepted)->create(['playbook_id' => $absorbed->id]);

    $result = (new DeletePlaybook)->handle(new Request(['id' => $absorbed->id, 'merged_into' => $survivor->id]));

    expect($result->isError())->toBeFalse();
    expect(Playbook::find($absorbed->id))->toBeNull();
    expect(PlaybookProposal::count())->toBe(0);
    expect($survivor->fresh()->uses_count)->toBe(5);
});

test('it refuses while the playbook has pending proposals', function () {
    $proposal = PlaybookProposal::factory()->create();

    $result = (new DeletePlaybook)->handle(new Request(['id' => $proposal->playbook_id]));

    expect($result->isError())->toBeTrue();
    expect(Playbook::find($proposal->playbook_id))->not->toBeNull();
});
