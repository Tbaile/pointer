<?php

use App\Models\Diagnosis;
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
    public function diagnosesToCheck(): int
    {
        return Diagnosis::whereNull('verdict')->count();
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6 px-6 py-10">
    <header class="space-y-1">
        <h1 class="text-primary-neutral text-3xl font-semibold">Dashboard</h1>
        <p class="text-secondary-neutral">Welcome back, {{ $this->user->name }}.</p>
    </header>

    <div class="grid grid-cols-2">
        <x-card :title="trans_choice(
            '{0} No diagnoses to check|{1} :value diagnose to check|[2,*] :value diagnoses to check',
            $this->diagnosesToCheck,
            ['value' => $this->diagnosesToCheck],
        )">
            <x-button
                :href="route('diagnoses.index')"
                class="mt-4"
            >
                {{ __('View diagnoses') }}
            </x-button>
        </x-card>
    </div>
</div>
