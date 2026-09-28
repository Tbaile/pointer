<?php

use App\Enums\DiagnosisConfidence;
use App\Enums\DiagnosisOutcome;
use App\Enums\DiagnosisVerdict;
use App\Mcp\Tools\RecordDiagnosis;
use App\Models\Diagnosis;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Diagnosis')] class extends Component {
    public Diagnosis $diagnosis;

    public string $tags = '';

    public string $user_symptoms = '';

    public string $machine_symptoms = '';

    public string $conclusion = '';

    public string $resolution = '';

    public string $outcome = '';

    public string $confidence = '';

    public string $confidence_reason = '';

    public string $verdict = '';

    public string $verdict_note = '';

    public bool $saved = false;

    public function mount(Diagnosis $diagnosis): void
    {
        $this->diagnosis = $diagnosis;
        $this->tags = $diagnosis->tags->pluck('tag')->join(', ');
        $this->user_symptoms = $diagnosis->user_symptoms;
        $this->machine_symptoms = $diagnosis->machine_symptoms;
        $this->conclusion = $diagnosis->conclusion ?? '';
        $this->resolution = $diagnosis->resolution ?? '';
        $this->outcome = $diagnosis->outcome->value;
        $this->confidence = $diagnosis->confidence->value;
        $this->confidence_reason = $diagnosis->confidence_reason;
        $this->verdict = $diagnosis->verdict->value ?? '';
        $this->verdict_note = $diagnosis->verdict_note ?? '';
    }

    public function updated(): void
    {
        $this->saved = false;
    }

    public function save(): void
    {
        $this->validate();

        $verdict = DiagnosisVerdict::tryFrom($this->verdict);

        DB::transaction(function () use ($verdict): void {
            $this->diagnosis->fill([
                'user_symptoms' => $this->user_symptoms,
                'machine_symptoms' => $this->machine_symptoms,
                'conclusion' => $this->conclusion ?: null,
                'resolution' => $this->resolution ?: null,
                'outcome' => DiagnosisOutcome::from($this->outcome),
                'confidence' => DiagnosisConfidence::from($this->confidence),
                'confidence_reason' => $this->confidence_reason,
            ]);

            if ($this->diagnosis->verdict !== $verdict) {
                $this->diagnosis->verdict = $verdict;
                $this->diagnosis->verified_by = $verdict ? auth()->id() : null;
                $this->diagnosis->verified_at = $verdict ? now() : null;
            }

            $this->diagnosis->verdict_note = $verdict ? ($this->verdict_note ?: null) : null;
            $this->diagnosis->save();

            $this->diagnosis->tags()->delete();
            $this->diagnosis->tags()->createMany(array_map(fn(string $tag): array => ['tag' => $tag], $this->parsedTags()));
        });

        $this->diagnosis->unsetRelation('tags');
        $this->saved = true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'tags' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $tags = $this->parsedTags();

                    if ($tags === [] || count($tags) > 10) {
                        $fail(__('Enter between 1 and 10 tags.'));
                    }

                    foreach ($tags as $tag) {
                        if (strlen($tag) > 50 || !preg_match(RecordDiagnosis::TAG_PATTERN, $tag)) {
                            $fail(__('Tags must be lowercase kebab-case, e.g. disk-full.'));
                        }
                    }
                },
            ],
            'user_symptoms' => ['required', 'string'],
            'machine_symptoms' => ['required', 'string'],
            'conclusion' => ['nullable', 'string'],
            'resolution' => ['nullable', 'string'],
            'outcome' => ['required', Rule::enum(DiagnosisOutcome::class)],
            'confidence' => ['required', Rule::enum(DiagnosisConfidence::class)],
            'confidence_reason' => ['required', 'string'],
            'verdict' => ['nullable', Rule::enum(DiagnosisVerdict::class)],
            'verdict_note' => ['nullable', 'string'],
        ];
    }

    /**
     * @return list<string>
     */
    private function parsedTags(): array
    {
        return str($this->tags)->explode(',')->map(fn(string $tag): string => trim($tag))->filter()->unique()->values()->all();
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6 px-6 py-10">
    <header class="space-y-1">
        <a
            href="{{ route('diagnoses.index') }}"
            class="text-secondary-neutral text-sm hover:underline"
        >{{ __('Back to diagnoses') }}</a>
        <h1 class="text-primary-neutral text-3xl font-semibold">{{ __('Diagnosis') }} #{{ $diagnosis->id }}</h1>
        <p class="text-secondary-neutral text-sm">
            {{ $diagnosis->system_type->value }} · {{ $diagnosis->sos_id }} ·
            {{ $diagnosis->created_at->format('Y-m-d H:i') }}
        </p>
    </header>

    <form
        wire:submit="save"
        class="space-y-4"
    >
        <div class="space-y-1">
            <label
                for="tags"
                class="text-secondary-neutral block text-sm"
            >{{ __('Tags (comma-separated)') }}</label>
            <input
                id="tags"
                type="text"
                wire:model="tags"
                class="surface-background-input border-secondary text-primary-neutral focus:ring-primary w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2"
            >
            @error('tags')
                <p class="text-danger text-sm">{{ $message }}</p>
            @enderror
        </div>

        @foreach ([
        'user_symptoms' => __('User symptoms'),
        'machine_symptoms' => __('Machine symptoms'),
        'conclusion' => __('Conclusion'),
        'resolution' => __('Resolution'),
    ] as $field => $label)
            <div class="space-y-1">
                <label
                    for="{{ $field }}"
                    class="text-secondary-neutral block text-sm"
                >{{ $label }}</label>
                <textarea
                    id="{{ $field }}"
                    rows="4"
                    wire:model="{{ $field }}"
                    class="surface-background-input border-secondary text-primary-neutral focus:ring-primary w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2"
                ></textarea>
                @error($field)
                    <p class="text-danger text-sm">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <div class="grid grid-cols-2 gap-4">
            @foreach ([
        'outcome' => [__('Outcome'), DiagnosisOutcome::cases()],
        'confidence' => [__('Confidence'), DiagnosisConfidence::cases()],
    ] as $field => [$label, $cases])
                <div class="space-y-1">
                    <label
                        for="{{ $field }}"
                        class="text-secondary-neutral block text-sm"
                    >{{ $label }}</label>
                    <select
                        id="{{ $field }}"
                        wire:model="{{ $field }}"
                        class="surface-background-input border-secondary text-primary-neutral focus:ring-primary w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2"
                    >
                        @foreach ($cases as $case)
                            <option value="{{ $case->value }}">{{ $case->value }}</option>
                        @endforeach
                    </select>
                    @error($field)
                        <p class="text-danger text-sm">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <div class="space-y-1">
            <label
                for="confidence_reason"
                class="text-secondary-neutral block text-sm"
            >{{ __('Confidence reason') }}</label>
            <textarea
                id="confidence_reason"
                rows="3"
                wire:model="confidence_reason"
                class="surface-background-input border-secondary text-primary-neutral focus:ring-primary w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2"
            ></textarea>
            @error('confidence_reason')
                <p class="text-danger text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-1">
            <label
                for="verdict"
                class="text-secondary-neutral block text-sm"
            >{{ __('Verdict') }}</label>
            <select
                id="verdict"
                wire:model="verdict"
                class="surface-background-input border-secondary text-primary-neutral focus:ring-primary w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2"
            >
                <option value="">{{ __('Not verified') }}</option>
                @foreach (DiagnosisVerdict::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->value }}</option>
                @endforeach
            </select>
            @error('verdict')
                <p class="text-danger text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-1">
            <label
                for="verdict_note"
                class="text-secondary-neutral block text-sm"
            >{{ __('Verdict note') }}</label>
            <textarea
                id="verdict_note"
                rows="3"
                wire:model="verdict_note"
                class="surface-background-input border-secondary text-primary-neutral focus:ring-primary w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2"
            ></textarea>
            @error('verdict_note')
                <p class="text-danger text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-4">
            <div class="w-32">
                <x-button>{{ __('Save') }}</x-button>
            </div>
            @if ($saved)
                <p class="text-secondary-neutral text-sm">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</div>
