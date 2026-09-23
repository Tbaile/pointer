<?php

use App\Mcp\Servers\PointerServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::local('pointer', PointerServer::class);

Mcp::web('/mcp/pointer', PointerServer::class)
    ->middleware('auth:passport');
