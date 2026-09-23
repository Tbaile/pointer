<?php

use App\Enums\DiagnosisConfidence;
use App\Enums\DiagnosisVerdict;
use App\Enums\SystemType;
use App\Mcp\Tools\SearchDiagnoses;
use App\Models\Diagnosis;
use App\Models\DiagnosisCheck;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

/**
 * @param  array<string, mixed>  $arguments
 * @return list<int>
 */
function searchDiagnosisIds(array $arguments): array
{
    $result = (new SearchDiagnoses)->handle(new Request(['system_type' => 'nethsecurity', ...$arguments]));

    expect($result)->toBeInstanceOf(ResponseFactory::class);

    return array_column($result->getStructuredContent()['diagnoses'], 'id');
}

test('it returns matching diagnoses with their checks', function () {
    $diagnosis = Diagnosis::factory()->withTags(['disk-full'])->create();
    DiagnosisCheck::factory()->for($diagnosis)->create(['tool' => 'victoria-query', 'finding' => 'Root at 100%.']);

    $result = (new SearchDiagnoses)->handle(new Request(['system_type' => 'nethsecurity', 'tags' => ['disk-full']]));

    $found = $result->getStructuredContent()['diagnoses'][0];

    expect($found['id'])->toBe($diagnosis->id);
    expect($found['tags'])->toBe(['disk-full']);
    expect($found['checks'])->toBe([[
        'tool' => 'victoria-query',
        'arguments' => ['config' => 'network'],
        'finding' => 'Root at 100%.',
        'result' => 'supports',
    ]]);
});

test('it only returns diagnoses on the same product', function () {
    $nethsecurity = Diagnosis::factory()->withTags(['disk-full'])->create();
    Diagnosis::factory()->withTags(['disk-full'])->create(['system_type' => SystemType::NethServer]);

    expect(searchDiagnosisIds(['tags' => ['disk-full']]))->toBe([$nethsecurity->id]);
});

test('it never returns rejected diagnoses', function () {
    $kept = Diagnosis::factory()->withTags(['disk-full'])->create();
    Diagnosis::factory()->withTags(['disk-full'])->verdict(DiagnosisVerdict::Rejected)->create();

    expect(searchDiagnosisIds(['tags' => ['disk-full']]))->toBe([$kept->id]);
});

test('it matches free text against symptoms and conclusion', function () {
    $bySymptom = Diagnosis::factory()->create(['machine_symptoms' => 'dnsmasq is not running']);
    $byConclusion = Diagnosis::factory()->create(['conclusion' => 'dnsmasq crashed on a bad host entry']);
    Diagnosis::factory()->create();

    expect(searchDiagnosisIds(['query' => 'dnsmasq']))->toEqualCanonicalizing([$bySymptom->id, $byConclusion->id]);
});

test('it ranks confirmed first, then tag overlap, then confidence', function () {
    $oneTagHigh = Diagnosis::factory()->withTags(['vpn-down'])->create(['confidence' => DiagnosisConfidence::High]);
    $oneTagLow = Diagnosis::factory()->withTags(['vpn-down'])->create(['confidence' => DiagnosisConfidence::Low]);
    $twoTags = Diagnosis::factory()->withTags(['vpn-down', 'certificate-expired'])->create();
    $confirmed = Diagnosis::factory()->withTags(['vpn-down'])->verdict(DiagnosisVerdict::Confirmed)->create();

    expect(searchDiagnosisIds(['tags' => ['vpn-down', 'certificate-expired']]))
        ->toBe([$confirmed->id, $twoTags->id, $oneTagHigh->id, $oneTagLow->id]);
});

test('it respects the limit', function () {
    Diagnosis::factory()->count(3)->withTags(['disk-full'])->create();

    expect(searchDiagnosisIds(['tags' => ['disk-full'], 'limit' => 2]))->toHaveCount(2);
});

test('it requires tags or a query', function () {
    expect(fn () => searchDiagnosisIds([]))->toThrow(ValidationException::class);
});
