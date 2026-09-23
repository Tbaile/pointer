<?php

namespace App\Mcp\Tools;

use App\Enums\CheckResult;
use App\Enums\CheckSource;
use App\Enums\DiagnosisConfidence;
use App\Enums\DiagnosisOutcome;
use App\Enums\SystemType;
use App\Models\Diagnosis;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Record Diagnosis')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Record the outcome of an analysis in the diagnosis history, so later analyses can learn from it.
    Call it exactly once, when the investigation ends: issue fixed, handed off, or given up. Record unresolved cases too, with outcome `unresolved` and no conclusion: knowing what did not work is as useful as a fix.

    - `user_symptoms`: what the user reported, in their terms. `machine_symptoms`: what you observed on the machine.
    - `checks`: every check that shaped the conclusion, with the tool you called, its arguments, what it showed and whether it supports or refutes the conclusion. Include the ones that ruled hypotheses out.
    - `tags`: short lowercase kebab-case labels for the problem, e.g. `openvpn-tunnel-down`, `disk-full`. Call `list-diagnosis-tags` first and reuse existing tags when they fit; only invent a new one when none does.
    - `confidence`: `high` only when a check directly proves the cause; `low` when the conclusion is a guess. Explain why in `confidence_reason`.
    - Write every field so the case applies to any machine: strip personal and customer data from the text, the findings and the check arguments, also inside quoted errors and command output. That covers names of people and companies, email addresses, phone numbers, usernames, hostnames, domain names, IP and MAC addresses, serial numbers and secrets. Replace each with a placeholder naming its role, e.g. `<customer-domain>`, `<wan-ip>`, `<lan-subnet>`, `<username>`. Keep the technical details that make the case recognizable: error messages, versions, service, option and interface names, ports.
    DESCRIPTION)]
class RecordDiagnosis extends Tool
{
    private const string TAG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'system_type' => ['required', Rule::enum(SystemType::class)],
            'user_symptoms' => ['required', 'string'],
            'machine_symptoms' => ['required', 'string'],
            'conclusion' => ['nullable', 'string'],
            'resolution' => ['nullable', 'string'],
            'outcome' => ['required', Rule::enum(DiagnosisOutcome::class)],
            'confidence' => ['required', Rule::enum(DiagnosisConfidence::class)],
            'confidence_reason' => ['required', 'string'],
            'tags' => ['required', 'array', 'min:1', 'max:10'],
            'tags.*' => ['string', 'max:50', 'regex:'.self::TAG_PATTERN],
            'checks' => ['required', 'array', 'min:1'],
            'checks.*.tool' => ['required', 'string'],
            'checks.*.arguments' => ['nullable', 'array'],
            'checks.*.finding' => ['required', 'string'],
            'checks.*.result' => ['required', Rule::enum(CheckResult::class)],
        ]);

        $diagnosis = Diagnosis::record(
            [
                ...collect($validated)->except(['tags', 'checks'])->all(),
                'user_id' => $request->user()?->getAuthIdentifier(),
            ],
            $validated['checks'],
            $validated['tags'],
            CheckSource::Reported,
        );

        return Response::text("Diagnosis {$diagnosis->id} recorded.");
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sos_id' => $schema->string()
                ->description('The SOS id of the target machine.')
                ->required(),
            'system_type' => $schema->string()
                ->enum(array_column(SystemType::cases(), 'value'))
                ->description('The product the machine runs, as returned by `what-is-it`.')
                ->required(),
            'user_symptoms' => $schema->string()
                ->description('The problem as the user described it.')
                ->required(),
            'machine_symptoms' => $schema->string()
                ->description('What you observed on the machine: errors, metrics, configuration anomalies.')
                ->required(),
            'conclusion' => $schema->string()
                ->description('The root cause you identified. Omit when you found none.'),
            'resolution' => $schema->string()
                ->description('The fix applied or suggested. Omit when there is none.'),
            'outcome' => $schema->string()
                ->enum(array_column(DiagnosisOutcome::cases(), 'value'))
                ->description('`resolved`: fixed or cause proven. `partial`: cause likely but not proven, or only partly fixed. `unresolved`: no cause found.')
                ->required(),
            'confidence' => $schema->string()
                ->enum(array_column(DiagnosisConfidence::cases(), 'value'))
                ->description('How confident you are in the conclusion.')
                ->required(),
            'confidence_reason' => $schema->string()
                ->description('Why you have that confidence.')
                ->required(),
            'tags' => $schema->array()
                ->items($schema->string()->pattern(trim(self::TAG_PATTERN, '/')))
                ->min(1)
                ->max(10)
                ->description('Lowercase kebab-case labels for the problem, reusing existing ones from `list-diagnosis-tags`.')
                ->required(),
            'checks' => $schema->array()
                ->items($schema->object([
                    'tool' => $schema->string()->description('Name of the tool called.')->required(),
                    'arguments' => $schema->object()->description('Arguments passed to the tool, without the SOS id.'),
                    'finding' => $schema->string()->description('What the result showed.')->required(),
                    'result' => $schema->string()
                        ->enum(array_column(CheckResult::cases(), 'value'))
                        ->description('Whether the finding supports or refutes the conclusion, or is inconclusive.')
                        ->required(),
                ]))
                ->min(1)
                ->description('The checks that shaped the conclusion.')
                ->required(),
        ];
    }
}
