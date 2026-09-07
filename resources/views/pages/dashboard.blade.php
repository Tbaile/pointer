<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Dashboard')] class extends Component {
    public function with(): array
    {
        return [
            'user' => auth()->user(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6 px-6 py-10">
    <header class="space-y-1">
        <h1 class="text-primary-neutral text-3xl font-semibold">Dashboard</h1>
        <p class="text-secondary-neutral">Welcome back, {{ $user->name }}.</p>
    </header>

    <div class="elevation-0 border-secondary rounded-xl border p-6">
        <p class="text-tertiary-neutral">Nothing here yet.</p>
    </div>
</div>
