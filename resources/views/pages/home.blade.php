<x-layouts.guest>
    <x-card
        :title="config('app.name')"
        :description="__('Lets an AI agent inspect NethServer and NethSecurity machines.')"
        :brand="false"
    >
        <a
            href="{{ filament()->getUrl() }}"
            class="bg-primary-active hover:bg-primary-hover focus:ring-primary mt-8 block w-full rounded-lg px-3 py-2 text-center text-sm font-semibold text-white focus:outline-none focus:ring-2"
        >{{ auth()->check() ? __('Dashboard') : __('Login') }}</a>
    </x-card>
</x-layouts.guest>
