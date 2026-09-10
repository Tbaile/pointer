<?php

use App\Ai\Agents\PointerAgent;
use App\Contracts\RemoteExecutor;
use App\Enums\SystemType;

function pointerAgent(SystemType $system = SystemType::NethSecurity): PointerAgent
{
    $executor = new class implements RemoteExecutor
    {
        public function run(string $machineUuid, string $command): array
        {
            return [];
        }
    };

    return new PointerAgent($executor, 'a1b2c3d4-0000-0000-0000-000000000000', $system);
}

test('it is constructed for a given system type', function () {
    expect(pointerAgent()->system)->toBe(SystemType::NethSecurity);
    expect(pointerAgent(SystemType::NethServer)->system)->toBe(SystemType::NethServer);
});

test('it renders its instructions from the prompt view', function () {
    $instructions = pointerAgent()->instructions();

    expect($instructions)->toBe(view('prompts.pointer')->render());
    expect($instructions)->not->toBeEmpty();
});

test('it tells the agent what kind of system it is investigating', function () {
    $instructions = pointerAgent()->instructions();

    expect($instructions)
        ->toContain('NethSecurity')
        ->toContain('OpenWrt')
        ->toContain('uci')
        ->toContain('api-cli')
        ->toContain('You are read-only.');
});
