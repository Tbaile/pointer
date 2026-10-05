<?php

use App\Enums\SystemType;
use App\Mcp\Tools\UpvotePlaybook;
use App\Models\Playbook;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

test('it adds one to the use count', function () {
    $playbook = Playbook::factory()->create();

    $result = (new UpvotePlaybook)->handle(new Request(['system_type' => 'nethsecurity', 'id' => $playbook->id]));

    expect((string) $result->content())->toBe("Playbook {$playbook->id} now used 2 times.");
    expect($playbook->fresh()->uses_count)->toBe(2);
});

test('it rejects an unknown playbook or one on another product', function () {
    $nethserver = Playbook::factory()->create(['system_type' => SystemType::NethServer]);

    expect(fn () => (new UpvotePlaybook)->handle(new Request(['system_type' => 'nethsecurity', 'id' => $nethserver->id])))
        ->toThrow(ValidationException::class);
    expect(fn () => (new UpvotePlaybook)->handle(new Request(['system_type' => 'nethsecurity', 'id' => $nethserver->id + 1])))
        ->toThrow(ValidationException::class);

    expect($nethserver->fresh()->uses_count)->toBe(1);
});
