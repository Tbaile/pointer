<?php

namespace App\Ai\Agents;

use App\Ai\Tools\RunRemoteCommand;
use App\Ai\Tools\SearchNsecDocumentation;
use App\Contracts\RemoteExecutor;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-5.6-luna')]
class PointerAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        private readonly RemoteExecutor $executor,
        private readonly string $sosId,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are a helpful assistant. You can run shell commands on the target machine to investigate whatever the user asks about.';
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool|ProviderTool>
     */
    public function tools(): iterable
    {
        return [
            app(SearchNsecDocumentation::class),
            new RunRemoteCommand($this->executor, $this->sosId),
        ];
    }
}
