<?php

use App\Ai\Agents\PointerAgent;
use App\Contracts\RemoteExecutor;
use App\Enums\SystemType;

function pointerAgent(SystemType $system = SystemType::NethSecurity, string $osRelease = "ID=nethsecurity\n"): PointerAgent
{
    $executor = new class implements RemoteExecutor
    {
        public function run(string $machineUuid, string $command): array
        {
            return [];
        }
    };

    return new PointerAgent($executor, 'a1b2c3d4-0000-0000-0000-000000000000', $system, $osRelease);
}

test('it is constructed for a given system type', function () {
    expect(pointerAgent()->system)->toBe(SystemType::NethSecurity);
    expect(pointerAgent(SystemType::NethServer)->system)->toBe(SystemType::NethServer);
});

test('it renders its instructions from the prompt view', function () {
    $osRelease = "ID=nethsecurity\n";

    $instructions = pointerAgent(osRelease: $osRelease)->instructions();

    expect($instructions)->toBe(view('prompts.pointer', ['osRelease' => $osRelease])->render());
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

test('it gives the agent the already-fetched os-release output', function () {
    $osRelease = "ID=nethsecurity\nVERSION_ID=8.8.0\n";

    $instructions = pointerAgent(osRelease: $osRelease)->instructions();

    expect($instructions)->toContain($osRelease);
});

test('it throws for a system type it does not yet support', function () {
    $agent = pointerAgent(SystemType::NethServer);

    expect(fn () => $agent->instructions())->toThrow(RuntimeException::class, 'Unsupported system type: nethserver');
    expect(fn () => $agent->tools())->toThrow(RuntimeException::class, 'Unsupported system type: nethserver');
});
