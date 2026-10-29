<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Admin\CloseToolRequests;
use App\Mcp\Tools\Admin\CreatePlaybook;
use App\Mcp\Tools\Admin\DeletePlaybook;
use App\Mcp\Tools\Admin\DumpPlaybooks;
use App\Mcp\Tools\Admin\ListPlaybookProposals;
use App\Mcp\Tools\Admin\ListToolRequests;
use App\Mcp\Tools\Admin\SetPlaybookVerdict;
use App\Mcp\Tools\Admin\SettlePlaybookProposal;
use App\Mcp\Tools\Admin\UpdatePlaybook;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Pointer Admin')]
#[Version('0.0.1')]
#[Instructions(<<<'INSTRUCTIONS'
    This server maintains the Pointer data in production: playbooks, playbook proposals and tool requests. It doesn't reach any machine.
    Read before writing: `dump-playbooks` returns each body with its `body_sha1`, which `update-playbook` needs to refuse a body that changed in the meantime. Settle the pending proposals of a playbook with `settle-playbook-proposal` before updating or deleting it. Updating keeps the verdict: set it with `set-playbook-verdict` once the playbook is checked.
    Every write is applied right away, ask the user before calling one.
    INSTRUCTIONS)]
class PointerAdminServer extends Server
{
    protected array $tools = [
        ListToolRequests::class,
        CloseToolRequests::class,
        DumpPlaybooks::class,
        ListPlaybookProposals::class,
        CreatePlaybook::class,
        UpdatePlaybook::class,
        DeletePlaybook::class,
        SettlePlaybookProposal::class,
        SetPlaybookVerdict::class,
    ];
}
