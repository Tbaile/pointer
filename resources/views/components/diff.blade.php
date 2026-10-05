@props(['from', 'to'])
@use('SebastianBergmann\Diff\Differ')
@use('SebastianBergmann\Diff\Output\DiffOnlyOutputBuilder')
@php
    $lines = array_filter(
        new Differ(new DiffOnlyOutputBuilder())->diffToArray($from, $to),
        fn(array $line): bool => in_array($line[1], [Differ::OLD, Differ::ADDED, Differ::REMOVED], true),
    );
@endphp
<div
    {{ $attributes->class('surface-background-code border-secondary overflow-x-auto rounded-lg border py-2 font-mono text-sm') }}>
    @foreach ($lines as [$text, $type])
        <div @class([
            'flex gap-2 px-3',
            'surface-inline-notification-success text-inline-notification-success-description' =>
                $type === Differ::ADDED,
            'surface-inline-notification-error text-inline-notification-error-description' =>
                $type === Differ::REMOVED,
            'text-secondary-neutral' => $type === Differ::OLD,
        ])>
            <span
                class="select-none whitespace-pre">{{ match ($type) {Differ::ADDED => '+',Differ::REMOVED => '-',default => ' '} }}</span>
            <span class="whitespace-pre-wrap">{{ rtrim($text, "\r\n") }}</span>
        </div>
    @endforeach
</div>
