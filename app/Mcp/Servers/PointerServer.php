<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\PointerPrompt;
use App\Mcp\Tools\ListDiagnosisTags;
use App\Mcp\Tools\NethSecurity\GetUciConfig;
use App\Mcp\Tools\NethSecurity\GetUciSection;
use App\Mcp\Tools\NethSecurity\ListDevices;
use App\Mcp\Tools\NethSecurity\ListUciConfigs;
use App\Mcp\Tools\NethSecurity\Ping;
use App\Mcp\Tools\NethSecurity\TcpProbe;
use App\Mcp\Tools\NethSecurity\Traceroute;
use App\Mcp\Tools\NethSecurity\VictoriaQuery;
use App\Mcp\Tools\RecordDiagnosis;
use App\Mcp\Tools\RequestTool;
use App\Mcp\Tools\SearchDiagnoses;
use App\Mcp\Tools\SearchNethvoiceDocumentation;
use App\Mcp\Tools\SearchNs8Documentation;
use App\Mcp\Tools\SearchNsecDocumentation;
use App\Mcp\Tools\SearchToolRequests;
use App\Mcp\Tools\UpvoteToolRequest;
use App\Mcp\Tools\WhatIsIt;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Pointer')]
#[Version('0.0.1')]
#[Instructions(<<<'INSTRUCTIONS'
    This server gives read access to a remote Nethesis machine, identified by its SOS id, to diagnose problems on it. Every command runs on that machine, never on the local computer.
    Pass the SOS id the user gave as `sos_id` to every tool that takes one. Call `what-is-it` first: its answer is the `system_type` for the other tools and decides which ones apply. The NethSecurity tools only work on `nethsecurity` machines.
    Search the documentation of the product the machine runs: `search-nsec-documentation` for `nethsecurity`, `search-ns8-documentation` for `nethserver`, plus `search-nethvoice-documentation` for NethVoice and telephony problems.
    Search past diagnoses with `search-diagnoses` before investigating, and record the outcome with `record-diagnosis` once the investigation ends.
    When a tool you need is missing, search `search-tool-requests` and either upvote the matching request with `upvote-tool-request` or create one with `request-tool`.
    INSTRUCTIONS)]
class PointerServer extends Server
{
    protected array $tools = [
        WhatIsIt::class,
        ListDevices::class,
        VictoriaQuery::class,
        ListUciConfigs::class,
        GetUciConfig::class,
        GetUciSection::class,
        Ping::class,
        Traceroute::class,
        TcpProbe::class,
        SearchNsecDocumentation::class,
        SearchNs8Documentation::class,
        SearchNethvoiceDocumentation::class,
        ListDiagnosisTags::class,
        SearchDiagnoses::class,
        RecordDiagnosis::class,
        SearchToolRequests::class,
        RequestTool::class,
        UpvoteToolRequest::class,
    ];

    protected array $prompts = [
        PointerPrompt::class,
    ];
}
