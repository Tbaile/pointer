<x-layouts.guest>
    <a
        href="{{ filament()->getUrl() }}"
        class="bg-primary-active hover:bg-primary-hover focus:ring-primary absolute right-6 top-6 rounded-lg px-4 py-2 font-medium text-white focus:outline-none focus:ring-2"
    >{{ auth()->check() ? __('Dashboard') : __('Login') }}</a>

    <h1 class="text-primary-neutral text-4xl font-semibold">{{ config('app.name') }}</h1>
</x-layouts.guest>
