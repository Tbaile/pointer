<?php

use App\Actions\SetPlaybookVerdict as SetPlaybookVerdictAction;
use App\Enums\PlaybookVerdict;
use App\Mcp\Tools\Admin\SetPlaybookVerdict;
use App\Models\Playbook;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

use function Pest\Laravel\actingAs;

function setPlaybookVerdict(): SetPlaybookVerdict
{
    return new SetPlaybookVerdict(new SetPlaybookVerdictAction);
}

test('it confirms the playbook as the caller with the note', function () {
    $user = User::factory()->create();
    $playbook = Playbook::factory()->create();

    actingAs($user);

    $result = setPlaybookVerdict()->handle(new Request(['id' => $playbook->id, 'verdict' => 'confirmed', 'note' => 'Tested on 8.8.0.']));

    $playbook->refresh();

    expect((string) $result->content())->toBe("Playbook {$playbook->id} confirmed.");
    expect($playbook->verdict)->toBe(PlaybookVerdict::Confirmed);
    expect($playbook->verdict_note)->toBe('Tested on 8.8.0.');
    expect($playbook->verified_by)->toBe($user->id);
    expect($playbook->verified_at)->not->toBeNull();
});

test('it clears the verdict with unverified', function () {
    $playbook = Playbook::factory()->verdict(PlaybookVerdict::Rejected)->create(['verdict_note' => 'Wrong fix.']);

    actingAs(User::factory()->create());

    setPlaybookVerdict()->handle(new Request(['id' => $playbook->id, 'verdict' => 'unverified', 'note' => 'ignored']));

    $playbook->refresh();

    expect($playbook->verdict)->toBeNull();
    expect($playbook->verdict_note)->toBeNull();
    expect($playbook->verified_by)->toBeNull();
});

test('it refuses a caller that is not authenticated', function () {
    $playbook = Playbook::factory()->create();

    $result = setPlaybookVerdict()->handle(new Request(['id' => $playbook->id, 'verdict' => 'confirmed']));

    expect($result->isError())->toBeTrue();
    expect($playbook->fresh()->verdict)->toBeNull();
});

test('it rejects an unknown verdict', function () {
    $playbook = Playbook::factory()->create();

    actingAs(User::factory()->create());

    expect(fn () => setPlaybookVerdict()->handle(new Request(['id' => $playbook->id, 'verdict' => 'maybe'])))->toThrow(ValidationException::class);
});
