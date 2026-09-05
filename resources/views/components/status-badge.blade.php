@props([
    'variant' => 'pending',
])

@php
$variantClasses = match ($variant) {
    'ok' => 'bg-teal-2 text-teal dark:bg-teal/15 dark:text-teal',
    'pending' => 'bg-amber-100 text-warn dark:bg-amber-900/30 dark:text-amber-400',
    'alert' => 'bg-red-100 text-danger dark:bg-red-900/30 dark:text-red-400',
    default => 'bg-zinc-100 text-muted dark:bg-zinc-700 dark:text-zinc-300',
};
@endphp

<span {{ $attributes->class(['inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold', $variantClasses]) }}>
    {{ $slot }}
</span>
