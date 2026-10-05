@props(['variant' => 'primary'])
<button
    {{ $attributes->merge(['type' => 'submit'])->class([
            'focus:ring-primary w-full rounded-lg px-4 py-2 font-medium focus:outline-none focus:ring-2 disabled:opacity-50',
            'bg-primary-active hover:bg-primary-hover text-white' => $variant === 'primary',
            'border-secondary text-primary-neutral hover:elevation-1 border' => $variant === 'secondary',
        ]) }}
>
    {{ $slot }}
</button>
