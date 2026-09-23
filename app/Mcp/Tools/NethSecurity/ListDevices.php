<?php

namespace App\Mcp\Tools\NethSecurity;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List Network Devices')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('List the network devices of a NethSecurity firewall by zone, with their addresses, link state, interface configuration and traffic stats.')]
class ListDevices extends NsApiTool
{
    protected string $path = 'ns.devices';

    protected string $method = 'list-devices';

    protected array $maskedFields = ['all_devices.*.iface.private_key'];
}
