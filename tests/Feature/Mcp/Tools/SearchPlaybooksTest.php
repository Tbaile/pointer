<?php

use App\Enums\PlaybookVerdict;
use App\Enums\ProposalStatus;
use App\Enums\SystemType;
use App\Mcp\Tools\SearchPlaybooks;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

/**
 * @param  array<string, mixed>  $arguments
 * @return list<int>
 */
function searchPlaybookIds(array $arguments): array
{
    $result = (new SearchPlaybooks)->handle(new Request(['system_type' => 'nethsecurity', ...$arguments]));

    expect($result)->toBeInstanceOf(ResponseFactory::class);

    return array_column($result->getStructuredContent()['playbooks'], 'id');
}

test('it returns matching playbooks without their body', function () {
    $playbook = Playbook::factory()->withTags(['dns-resolution'])->create();

    $result = (new SearchPlaybooks)->handle(new Request(['system_type' => 'nethsecurity', 'tags' => ['dns-resolution']]));

    expect($result->getStructuredContent()['playbooks'])->toBe([[
        'id' => $playbook->id,
        'title' => $playbook->title,
        'tags' => ['dns-resolution'],
        'uses_count' => 1,
        'verdict' => null,
        'updated_at' => $playbook->updated_at->toDateString(),
        'pending_proposals_count' => 0,
    ]]);
});

test('it counts only the pending proposals of each playbook', function () {
    $playbook = Playbook::factory()->withTags(['dns-resolution'])->create();
    PlaybookProposal::factory()->for($playbook)->create();
    PlaybookProposal::factory()->for($playbook)->reviewed(ProposalStatus::Accepted)->create();
    PlaybookProposal::factory()->for($playbook)->reviewed(ProposalStatus::Rejected)->create();

    $result = (new SearchPlaybooks)->handle(new Request(['system_type' => 'nethsecurity', 'tags' => ['dns-resolution']]));

    expect($result->getStructuredContent()['playbooks'][0]['pending_proposals_count'])->toBe(1);
});

test('it only returns playbooks on the same product', function () {
    $nethsecurity = Playbook::factory()->withTags(['disk-full'])->create();
    Playbook::factory()->withTags(['disk-full'])->create(['system_type' => SystemType::NethServer]);

    expect(searchPlaybookIds(['tags' => ['disk-full']]))->toBe([$nethsecurity->id]);
});

test('it never returns rejected playbooks', function () {
    $kept = Playbook::factory()->withTags(['disk-full'])->create();
    Playbook::factory()->withTags(['disk-full'])->verdict(PlaybookVerdict::Rejected)->create();

    expect(searchPlaybookIds(['tags' => ['disk-full']]))->toBe([$kept->id]);
});

test('it matches free text against title and body', function () {
    $byTitle = Playbook::factory()->create(['title' => 'dnsmasq not running']);
    $byBody = Playbook::factory()->create(['body' => '- Always: `logread -e dnsmasq`']);
    Playbook::factory()->create();

    expect(searchPlaybookIds(['query' => 'dnsmasq']))->toEqualCanonicalizing([$byTitle->id, $byBody->id]);
});

test('it requires every query word across title, body and tags', function () {
    $allWords = Playbook::factory()->withTags(['adblock'])->create(['title' => 'DNS resolution fails', 'body' => 'Domain blocked']);
    Playbook::factory()->withTags(['adblock'])->create(['title' => 'DNS resolution fails', 'body' => 'Upstream down']);

    expect(searchPlaybookIds(['query' => 'dns adblock blocked']))->toBe([$allWords->id]);
});

test('it ranks confirmed first, then tag overlap, then use count', function () {
    $oneTagMostUsed = Playbook::factory()->withTags(['dns-resolution'])->create();
    $oneTagMostUsed->increment('uses_count', 5);
    $oneTagLeastUsed = Playbook::factory()->withTags(['dns-resolution'])->create();
    $twoTags = Playbook::factory()->withTags(['dns-resolution', 'adblock'])->create();
    $confirmed = Playbook::factory()->withTags(['dns-resolution'])->verdict(PlaybookVerdict::Confirmed)->create();

    expect(searchPlaybookIds(['tags' => ['dns-resolution', 'adblock']]))
        ->toBe([$confirmed->id, $twoTags->id, $oneTagMostUsed->id, $oneTagLeastUsed->id]);
});

test('it respects the limit', function () {
    Playbook::factory()->count(3)->withTags(['disk-full'])->create();

    expect(searchPlaybookIds(['tags' => ['disk-full'], 'limit' => 2]))->toHaveCount(2);
});

test('it requires tags or a query', function () {
    expect(fn () => searchPlaybookIds([]))->toThrow(ValidationException::class);
});
