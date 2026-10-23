<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\PointerPrompt;
use App\Mcp\Tools\DiskUsage;
use App\Mcp\Tools\FileChecksums;
use App\Mcp\Tools\GetPlaybook;
use App\Mcp\Tools\KernelCounters;
use App\Mcp\Tools\ListPlaybookTags;
use App\Mcp\Tools\NethSecurity\Apk;
use App\Mcp\Tools\NethSecurity\ArpProbe;
use App\Mcp\Tools\NethSecurity\ConntrackList;
use App\Mcp\Tools\NethSecurity\DnsLookup as NsecDnsLookup;
use App\Mcp\Tools\NethSecurity\GetUciConfig;
use App\Mcp\Tools\NethSecurity\GetUciSection;
use App\Mcp\Tools\NethSecurity\HaStatus;
use App\Mcp\Tools\NethSecurity\ListDevices;
use App\Mcp\Tools\NethSecurity\ListUciConfigs;
use App\Mcp\Tools\NethSecurity\Mwan3Status;
use App\Mcp\Tools\NethSecurity\NftList;
use App\Mcp\Tools\NethSecurity\Ping;
use App\Mcp\Tools\NethSecurity\ReadSystemLog;
use App\Mcp\Tools\NethSecurity\ServiceStatus;
use App\Mcp\Tools\NethSecurity\TcpProbe;
use App\Mcp\Tools\NethSecurity\Traceroute;
use App\Mcp\Tools\NethSecurity\VictoriaQuery;
use App\Mcp\Tools\NethSecurity\WireguardStatus;
use App\Mcp\Tools\NethServer\AsteriskCli;
use App\Mcp\Tools\NethServer\AuditLog;
use App\Mcp\Tools\NethServer\ClusterAction;
use App\Mcp\Tools\NethServer\Crowdsec;
use App\Mcp\Tools\NethServer\DnsLookup as Ns8DnsLookup;
use App\Mcp\Tools\NethServer\FirewallStatus;
use App\Mcp\Tools\NethServer\HttpProbe;
use App\Mcp\Tools\NethServer\ListModules;
use App\Mcp\Tools\NethServer\ModuleAction;
use App\Mcp\Tools\NethServer\ModuleContainers;
use App\Mcp\Tools\NethServer\ModuleEnvironment;
use App\Mcp\Tools\NethServer\Ping as Ns8Ping;
use App\Mcp\Tools\NethServer\ReadLogs;
use App\Mcp\Tools\NethServer\Rtpengine;
use App\Mcp\Tools\NethServer\StartNodeSupport;
use App\Mcp\Tools\NethServer\StopNodeSupport;
use App\Mcp\Tools\NethServer\TcpProbe as Ns8TcpProbe;
use App\Mcp\Tools\NethServer\TlsProbe;
use App\Mcp\Tools\NethServer\Tracepath;
use App\Mcp\Tools\NetworkState;
use App\Mcp\Tools\ProcessList;
use App\Mcp\Tools\ProposePlaybookChange;
use App\Mcp\Tools\RecordPlaybook;
use App\Mcp\Tools\RequestTool;
use App\Mcp\Tools\RunCommand;
use App\Mcp\Tools\SearchNethvoiceDocumentation;
use App\Mcp\Tools\SearchNs8Documentation;
use App\Mcp\Tools\SearchNsecDocumentation;
use App\Mcp\Tools\SearchPlaybooks;
use App\Mcp\Tools\SearchToolRequests;
use App\Mcp\Tools\UpvotePlaybook;
use App\Mcp\Tools\UpvoteToolRequest;
use App\Mcp\Tools\WhatIsIt;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Pointer')]
#[Version('0.0.1')]
#[Instructions(<<<'INSTRUCTIONS'
    This server gives read access to a remote Nethesis machine, identified by its SOS id, to diagnose problems on it. Every command runs on that machine, never on the local computer: do not use local shell, file or web tools for anything about the machine. Tool calls run one at a time.
    Pass the SOS id the user gave as `sos_id` to every tool that takes one. Call `what-is-it` first: its answer is the `system_type` for the other tools and decides which ones apply. The NethSecurity tools only work on `nethsecurity` machines.
    Call `list-playbook-tags` and `search-playbooks` before investigating and open the matching ones with `get-playbook`, then search the documentation of the product the machine runs: `search-nsec-documentation` for `nethsecurity`, `search-ns8-documentation` for `nethserver`, plus `search-nethvoice-documentation` for NethVoice and telephony problems.
    When the investigation ends, report to the user first, then ask before saving: upvote the playbooks whose checks helped with `upvote-playbook`, propose a change to one that lacked a useful check with `propose-playbook-change`, or record a new one with `record-playbook`. Save only what the user confirmed or the machine showed.
    When a tool you need is missing, load the full tool list to make sure, then search `search-tool-requests` and either upvote the matching request with `upvote-tool-request` or create one with `request-tool`.
    Only when no other tool can answer, use `run-command` with the tool request for the missing capability; keep the command short and readable.
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
        NsecDnsLookup::class,
        ReadSystemLog::class,
        ServiceStatus::class,
        NftList::class,
        Apk::class,
        ConntrackList::class,
        HaStatus::class,
        Mwan3Status::class,
        WireguardStatus::class,
        ArpProbe::class,
        ProcessList::class,
        DiskUsage::class,
        FileChecksums::class,
        KernelCounters::class,
        ReadLogs::class,
        ListModules::class,
        ModuleContainers::class,
        ModuleEnvironment::class,
        FirewallStatus::class,
        Crowdsec::class,
        ClusterAction::class,
        ModuleAction::class,
        AsteriskCli::class,
        Rtpengine::class,
        AuditLog::class,
        Ns8Ping::class,
        Tracepath::class,
        Ns8TcpProbe::class,
        Ns8DnsLookup::class,
        HttpProbe::class,
        TlsProbe::class,
        NetworkState::class,
        StartNodeSupport::class,
        StopNodeSupport::class,
        SearchNsecDocumentation::class,
        SearchNs8Documentation::class,
        SearchNethvoiceDocumentation::class,
        ListPlaybookTags::class,
        SearchPlaybooks::class,
        GetPlaybook::class,
        RecordPlaybook::class,
        ProposePlaybookChange::class,
        UpvotePlaybook::class,
        SearchToolRequests::class,
        RequestTool::class,
        UpvoteToolRequest::class,
        RunCommand::class,
    ];

    protected array $prompts = [
        PointerPrompt::class,
    ];
}
