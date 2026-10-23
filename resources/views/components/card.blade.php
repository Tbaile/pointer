@props(['title', 'description' => null, 'brand' => true])
<div
    {{ $attributes->class('elevation-0 ring-secondary w-full max-w-lg rounded-xl px-6 py-12 shadow-xs ring-1 sm:px-12') }}>
    @if ($brand)
        <p class="text-primary-neutral mb-4 text-center text-xl font-bold">{{ config('app.name') }}</p>
    @endif

    <h1 class="text-primary-neutral text-center text-2xl font-bold tracking-tight">{{ $title }}</h1>
    @if ($description)
        <p class="text-secondary-neutral mt-2 text-center text-sm">{{ $description }}</p>
    @endif

    {{ $slot }}
</div>
