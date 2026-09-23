<?php

use App\Enums\CheckResult;
use App\Enums\CheckSource;
use App\Enums\DiagnosisConfidence;
use App\Enums\DiagnosisOutcome;
use App\Enums\SystemType;
use App\Mcp\Tools\RecordDiagnosis;
use App\Models\Diagnosis;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $overrides
 */
function recordDiagnosis(array $overrides = []): Response
{
    return (new RecordDiagnosis)->handle(new Request([
        'sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000',
        'system_type' => 'nethsecurity',
        'user_symptoms' => 'Remote office cannot reach the VPN.',
        'machine_symptoms' => 'OpenVPN instance ns_roadwarrior1 is stopped.',
        'conclusion' => 'Server certificate expired.',
        'resolution' => 'Renew the certificate.',
        'outcome' => 'resolved',
        'confidence' => 'high',
        'confidence_reason' => 'The certificate end date is in the past.',
        'tags' => ['openvpn-tunnel-down', 'certificate-expired', 'openvpn-tunnel-down'],
        'checks' => [
            [
                'tool' => 'get-uci-config',
                'arguments' => ['config' => 'openvpn'],
                'finding' => 'Instance enabled with a server certificate.',
                'result' => 'inconclusive',
            ],
            [
                'tool' => 'victoria-query',
                'finding' => 'Tunnel traffic dropped to zero at the certificate end date.',
                'result' => 'supports',
            ],
        ],
        ...$overrides,
    ]));
}

test('it records the diagnosis with its checks and tags', function () {
    $result = recordDiagnosis();

    $diagnosis = Diagnosis::sole();

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("Diagnosis {$diagnosis->id} recorded.");
    expect($diagnosis->system_type)->toBe(SystemType::NethSecurity);
    expect($diagnosis->outcome)->toBe(DiagnosisOutcome::Resolved);
    expect($diagnosis->confidence)->toBe(DiagnosisConfidence::High);
    expect($diagnosis->verdict)->toBeNull();
    expect($diagnosis->user_id)->toBeNull();
    expect($diagnosis->tags->pluck('tag')->all())->toEqualCanonicalizing(['openvpn-tunnel-down', 'certificate-expired']);
    expect($diagnosis->checks)->toHaveCount(2);
    expect($diagnosis->checks[0]->arguments)->toBe(['config' => 'openvpn']);
    expect($diagnosis->checks[1]->arguments)->toBeNull();
    expect($diagnosis->checks[1]->result)->toBe(CheckResult::Supports);
    expect($diagnosis->checks->pluck('source')->unique()->all())->toBe([CheckSource::Reported]);
});

test('it records an unresolved diagnosis without conclusion or resolution', function () {
    recordDiagnosis(['conclusion' => null, 'resolution' => null, 'outcome' => 'unresolved', 'confidence' => 'low']);

    expect(Diagnosis::sole())
        ->conclusion->toBeNull()
        ->outcome->toBe(DiagnosisOutcome::Unresolved);
});

test('it stores the authenticated user', function () {
    $user = User::factory()->create();

    actingAs($user);

    recordDiagnosis();

    expect(Diagnosis::sole()->user_id)->toBe($user->id);
});

test('it rejects invalid input without storing anything', function (array $overrides) {
    expect(fn () => recordDiagnosis($overrides))->toThrow(ValidationException::class);

    expect(Diagnosis::count())->toBe(0);
})->with([
    'unknown outcome' => [['outcome' => 'fixed']],
    'no checks' => [['checks' => []]],
    'no tags' => [['tags' => []]],
    'tag not in kebab-case' => [['tags' => ['OpenVPN Down']]],
    'check with unknown result' => [['checks' => [['tool' => 'what-is-it', 'finding' => 'x', 'result' => 'maybe']]]],
]);
