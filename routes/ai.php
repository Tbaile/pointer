<?php

use App\Enums\Permission;
use App\Http\Middleware\LogMcpToolCalls;
use App\Mcp\Servers\PointerAdminServer;
use App\Mcp\Servers\PointerServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/pointer', PointerServer::class)
    ->middleware(['auth:passport', 'can:'.Permission::UseMcp->value, LogMcpToolCalls::class]);

Mcp::web('/mcp/pointer-admin', PointerAdminServer::class)
    ->middleware(['auth:passport', 'can:'.Permission::ManagePointer->value, LogMcpToolCalls::class]);
