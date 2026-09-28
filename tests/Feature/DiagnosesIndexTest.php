<?php

use App\Enums\DiagnosisVerdict;
use App\Enums\SystemType;
use App\Models\Diagnosis;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected from the diagnoses index to login', function () {
    get('/diagnoses')->assertRedirect('/login');
});

test('the diagnoses index lists tags, system type, verdict and creation date', function () {
    Diagnosis::factory()
        ->withTags(['dns', 'wan-down'])
        ->verdict(DiagnosisVerdict::Rejected)
        ->create([
            'system_type' => SystemType::NethServer,
            'created_at' => '2026-09-01 10:30:00',
        ]);
    Diagnosis::factory()->create();

    actingAs(User::factory()->create())
        ->get('/diagnoses')
        ->assertOk()
        ->assertSee('dns, wan-down')
        ->assertSee('nethserver')
        ->assertSee('rejected')
        ->assertSee('2026-09-01 10:30')
        ->assertSee('Not verified');
});

test('the diagnoses index paginates ten diagnoses per page, newest first', function () {
    foreach (range(0, 10) as $daysAgo) {
        Diagnosis::factory()->create(['created_at' => now()->subDays($daysAgo)->setTime(12, 0)]);
    }
    $oldest = now()->subDays(10)->format('Y-m-d');
    $newest = now()->format('Y-m-d');
    $user = User::factory()->create();

    actingAs($user)->get('/diagnoses')
        ->assertSee($newest)
        ->assertDontSee($oldest);

    actingAs($user)->get('/diagnoses?page=2')
        ->assertSee($oldest)
        ->assertDontSee($newest);
});
