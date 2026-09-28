<?php

use App\Enums\DiagnosisConfidence;
use App\Enums\DiagnosisOutcome;
use App\Enums\DiagnosisVerdict;
use App\Models\Diagnosis;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected from a diagnosis to login', function () {
    $diagnosis = Diagnosis::factory()->create();

    get("/diagnoses/{$diagnosis->id}")->assertRedirect('/login');
});

test('the diagnosis form is pre-filled', function () {
    $diagnosis = Diagnosis::factory()->withTags(['dns', 'wan-down'])->create([
        'user_symptoms' => 'Internet is slow',
    ]);

    actingAs(User::factory()->create())
        ->get("/diagnoses/{$diagnosis->id}")
        ->assertOk()
        ->assertSee('dns, wan-down')
        ->assertSee('Internet is slow');
});

test('saving updates the diagnosis, its tags and the verdict', function () {
    $user = User::factory()->create();
    $diagnosis = Diagnosis::factory()->withTags(['dns'])->create();

    actingAs($user);

    Livewire::test('pages::diagnoses.edit', ['diagnosis' => $diagnosis])
        ->set('tags', 'wan-down, disk-full, wan-down')
        ->set('conclusion', '')
        ->set('outcome', DiagnosisOutcome::Unresolved->value)
        ->set('confidence', DiagnosisConfidence::Low->value)
        ->set('verdict', DiagnosisVerdict::Confirmed->value)
        ->set('verdict_note', 'Checked on site')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    $diagnosis->refresh();

    expect($diagnosis->tags->pluck('tag')->all())->toEqualCanonicalizing(['wan-down', 'disk-full'])
        ->and($diagnosis->conclusion)->toBeNull()
        ->and($diagnosis->outcome)->toBe(DiagnosisOutcome::Unresolved)
        ->and($diagnosis->confidence)->toBe(DiagnosisConfidence::Low)
        ->and($diagnosis->verdict)->toBe(DiagnosisVerdict::Confirmed)
        ->and($diagnosis->verdict_note)->toBe('Checked on site')
        ->and($diagnosis->verified_by)->toBe($user->id)
        ->and($diagnosis->verified_at)->not->toBeNull();
});

test('clearing the verdict removes the verifier', function () {
    $diagnosis = Diagnosis::factory()->withTags(['dns'])->verdict(DiagnosisVerdict::Rejected)->create();

    actingAs(User::factory()->create());

    Livewire::test('pages::diagnoses.edit', ['diagnosis' => $diagnosis])
        ->set('verdict', '')
        ->call('save')
        ->assertHasNoErrors();

    $diagnosis->refresh();

    expect($diagnosis->verdict)->toBeNull()
        ->and($diagnosis->verified_by)->toBeNull()
        ->and($diagnosis->verified_at)->toBeNull();
});

test('tags must be lowercase kebab-case', function () {
    $diagnosis = Diagnosis::factory()->withTags(['dns'])->create();

    actingAs(User::factory()->create());

    Livewire::test('pages::diagnoses.edit', ['diagnosis' => $diagnosis])
        ->set('tags', 'Disk Full')
        ->call('save')
        ->assertHasErrors('tags');

    expect($diagnosis->tags()->pluck('tag')->all())->toBe(['dns']);
});
