<?php

use App\Mcp\Servers\PointerServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('pointer', PointerServer::class);
