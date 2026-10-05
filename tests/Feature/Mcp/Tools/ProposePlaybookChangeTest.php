<?php

use App\Enums\PlaybookVerdict;
use App\Enums\ProposalStatus;
use App\Enums\SystemType;
use App\Mcp\Tools\ProposePlaybookChange;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $overrides
 */
function proposePlaybookChange(Playbook $playbook, array $overrides = []): Response
{
    return (new ProposePlaybookChange)->handle(new Request([
        'system_type' => 'nethsecurity',
        'id' => $playbook->id,
        'body' => '# New body',
        'reason' => 'Added the adblock check, it showed the domain blocked.',
        ...$overrides,
    ]));
}

test('it records a pending proposal and leaves the playbook untouched', function () {
    $user = User::factory()->create();
    $playbook = Playbook::factory()->withTags(['dns-resolution'])->verdict(PlaybookVerdict::Confirmed)->create(['body' => '# Old body']);

    actingAs($user);

    $result = proposePlaybookChange($playbook, ['tags' => ['dns-resolution', 'adblock', 'adblock']]);

    $proposal = PlaybookProposal::sole();

    expect((string) $result->content())->toBe("Proposal {$proposal->id} recorded for playbook {$playbook->id}.");
    expect($proposal)
        ->status->toBe(ProposalStatus::Pending)
        ->body->toBe('# New body')
        ->base_body->toBe('# Old body')
        ->tags->toBe(['dns-resolution', 'adblock'])
        ->user_id->toBe($user->id);
    expect($playbook->fresh())
        ->body->toBe('# Old body')
        ->verdict->toBe(PlaybookVerdict::Confirmed);
});

test('it keeps the current tags when none are proposed', function () {
    proposePlaybookChange(Playbook::factory()->create());

    expect(PlaybookProposal::sole()->tags)->toBeNull();
});

test('it rejects invalid input without storing anything', function (Closure $overrides) {
    $playbook = Playbook::factory()->create();

    expect(fn () => proposePlaybookChange($playbook, $overrides($playbook)))->toThrow(ValidationException::class);

    expect(PlaybookProposal::count())->toBe(0);
})->with([
    'unknown playbook' => [fn (Playbook $playbook) => ['id' => $playbook->id + 1]],
    'another product' => [fn (Playbook $playbook) => ['system_type' => SystemType::NethServer->value]],
    'tag not in kebab-case' => [fn () => ['tags' => ['DNS Down']]],
    'no reason' => [fn () => ['reason' => '']],
]);
