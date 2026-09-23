<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;

#[Description('Startup analysis and initial information using Pointer MCP.')]
class PointerPrompt extends Prompt
{
    public function handle(): Response
    {
        return Response::view('prompts.mcp.pointer');
    }
}
