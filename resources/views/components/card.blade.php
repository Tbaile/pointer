@props(['title', 'description' => null])
<div {{ $attributes->class('elevation-0 border-secondary w-full max-w-sm rounded-xl border p-8') }}>
    <h1 class="text-primary-neutral text-2xl font-semibold">{{ $title }}</h1>
    @if ($description)
        <p class="text-secondary-neutral mt-1 text-sm">{{ $description }}</p>
    @endif

    {{ $slot }}
</div>
