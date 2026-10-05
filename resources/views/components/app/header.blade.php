<header class="border-secondary elevation-0 flex items-center justify-between border-b px-6 py-3">
    <nav class="flex items-center gap-6">
        <a
            href="{{ route('dashboard') }}"
            class="text-primary-neutral font-semibold"
        >
            {{ config('app.name') }}
        </a>
        <a
            href="{{ route('playbooks.index') }}"
            class="text-secondary-neutral text-sm hover:underline"
        >
            {{ __('Playbooks') }}
        </a>
    </nav>

    <div class="flex items-center gap-3 text-sm">
        <span class="text-secondary-neutral">{{ auth()->user()?->name }}</span>

        <form
            method="POST"
            action="{{ route('logout') }}"
        >
            @csrf
            <button
                type="submit"
                class="text-danger hover:underline"
            >
                Log out
            </button>
        </form>
    </div>
</header>
