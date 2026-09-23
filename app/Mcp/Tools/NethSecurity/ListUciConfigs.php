<?php

namespace App\Mcp\Tools\NethSecurity;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('List the names of the UCI configurations present on a NethSecurity firewall, to be read with the GetUciConfig tool.')]
class ListUciConfigs extends NsApiTool
{
    protected string $path = 'uci';

    protected string $method = 'configs';
}
