<header class="border-secondary elevation-0 flex items-center justify-between border-b px-6 py-3">
    <span class="text-primary-neutral font-semibold">{{ config('app.name') }}</span>

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
