<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\PointerPrompt;
use App\Mcp\Tools\NethSecurity\GetUciConfig;
use App\Mcp\Tools\NethSecurity\GetUciSection;
use App\Mcp\Tools\NethSecurity\ListDevices;
use App\Mcp\Tools\NethSecurity\ListUciConfigs;
use App\Mcp\Tools\NethSecurity\VictoriaQuery;
use App\Mcp\Tools\SearchNethvoiceDocumentation;
use App\Mcp\Tools\SearchNs8Documentation;
use App\Mcp\Tools\SearchNsecDocumentation;
use App\Mcp\Tools\WhatIsIt;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Pointer')]
#[Version('0.0.1')]
#[Instructions('This server provides proxy access to the machine to diagnose.')]
class PointerServer extends Server
{
    protected array $tools = [
        WhatIsIt::class,
        ListDevices::class,
        VictoriaQuery::class,
        ListUciConfigs::class,
        GetUciConfig::class,
        GetUciSection::class,
        SearchNsecDocumentation::class,
        SearchNs8Documentation::class,
        SearchNethvoiceDocumentation::class,
    ];

    protected array $prompts = [
        PointerPrompt::class,
    ];
}
