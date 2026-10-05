<?php

use App\Enums\ProposalStatus;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function user(): ?User
    {
        return auth()->user();
    }

    #[Computed]
    public function unverifiedPlaybooks(): int
    {
        return Playbook::whereNull('verdict')->count();
    }

    #[Computed]
    public function pendingProposals(): int
    {
        return PlaybookProposal::query()->where('status', ProposalStatus::Pending)->count();
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6 px-6 py-10">
    <header class="space-y-1">
        <h1 class="text-primary-neutral text-3xl font-semibold">Dashboard</h1>
        <p class="text-secondary-neutral">Welcome back, {{ $this->user->name }}.</p>
    </header>

    <x-card :title="__('Playbooks')">
        <ul class="mt-4 space-y-2">
            <li>
                <a
                    href="{{ route('playbooks.index', ['verdict' => 'unverified']) }}"
                    class="text-primary-neutral hover:underline"
                >{{ trans_choice('{0} No unverified playbooks|{1} :count unverified playbook|[2,*] :count unverified playbooks', $this->unverifiedPlaybooks) }}</a>
            </li>
            <li>
                <a
                    href="{{ route('playbooks.index', ['pending' => 1]) }}"
                    class="text-primary-neutral hover:underline"
                >{{ trans_choice('{0} No pending proposals|{1} :count pending proposal|[2,*] :count pending proposals', $this->pendingProposals) }}</a>
            </li>
        </ul>
    </x-card>
</div>
