@props([
    'label' => null,
    'value' => null,
    'suffix' => null,
    'description' => null,
    'variant' => 'default',
])

@php
$variantClasses = match ($variant) {
    'positive' => 'text-teal',
    'negative' => 'text-danger',
    'warning' => 'text-warn',
    default => 'text-ink',
};
@endphp

<div {{ $attributes->class('rounded-xl border border-line bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900') }}>
    @if ($label)
        <span class="block text-xs text-muted">{{ $label }}</span>
    @endif
    <div class="mt-1.5 flex items-baseline gap-0.5">
        <strong class="text-2xl font-semibold {{ $variantClasses }}">{{ $value }}</strong>
        @if ($suffix)
            <em class="text-sm not-italic {{ $variantClasses }}">{{ $suffix }}</em>
        @endif
    </div>
    @if ($description)
        <small class="mt-1 block text-xs text-muted">{{ $description }}</small>
    @endif
</div>
