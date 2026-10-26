<?php

use App\Enums\Permission;
use App\Http\Middleware\LogMcpToolCalls;
use App\Mcp\Servers\PointerServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/pointer', PointerServer::class)
    ->middleware(['auth:passport', 'can:'.Permission::UseMcp->value, LogMcpToolCalls::class]);
