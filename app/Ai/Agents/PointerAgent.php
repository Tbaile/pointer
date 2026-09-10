<?php

namespace App\Ai\Agents;

use App\Ai\Tools\RunRemoteCommand;
use App\Ai\Tools\SearchNsecDocumentation;
use App\Contracts\RemoteExecutor;
use App\Enums\SystemType;
use Laravel\Ai\Attributes\MaxSteps;
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
use RuntimeException;
use Stringable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-5.6-luna')]
#[MaxSteps(25)]
class PointerAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        private readonly RemoteExecutor $executor,
        private readonly string $sosId,
        public readonly SystemType $system,
        private readonly string $osRelease,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return match ($this->system) {
            SystemType::NethSecurity => view('prompts.pointer', ['osRelease' => $this->osRelease])->render(),
            default => throw new RuntimeException("Unsupported system type: {$this->system->value}"),
        };
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool|ProviderTool>
     */
    public function tools(): iterable
    {
        return match ($this->system) {
            SystemType::NethSecurity => [
                app(SearchNsecDocumentation::class),
                new RunRemoteCommand($this->executor, $this->sosId),
            ],
            default => throw new RuntimeException("Unsupported system type: {$this->system->value}"),
        };
    }
}
