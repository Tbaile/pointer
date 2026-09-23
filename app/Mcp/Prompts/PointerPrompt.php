<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;

#[Title('Diagnose Machine')]
#[Description('Diagnose a problem on a machine reached through Pointer: include the SOS id of the machine and a description of the problem.')]
class PointerPrompt extends Prompt
{
    public function handle(): Response
    {
        return Response::view('prompts.mcp.pointer');
    }
}
