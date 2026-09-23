<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\ListUciConfigs;
use App\Services\Privacy\SecretMasker;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

test('it lists the UCI configurations', function () {
    $executor = new class implements RemoteExecutor
    {
        public ?string $calledWithCommand = null;

        public function run(string $machineUuid, string $command): string
        {
            $this->calledWithCommand = $command;

            return '{"configs":["dhcp","firewall","network"]}';
        }
    };

    $result = (new ListUciConfigs($executor, new SecretMasker('test-key')))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000']));

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['configs' => ['dhcp', 'firewall', 'network']]);
    expect($executor->calledWithCommand)->toBe("ubus -S call 'uci' 'configs' '{}'");
});
