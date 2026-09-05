@props([
    'title' => null,
    'description' => null,
])

<x-layouts::app :title="$title">
    <div class="flex flex-col items-center justify-center gap-4 py-20 text-center">
        <x-mary-icon name="o-clock" class="size-12 text-base-content/50" />
        <h1 class="text-xl font-semibold">{{ $title }}</h1>
        @if ($description)
            <p class="max-w-md text-base-content/70">{{ $description }}</p>
        @endif
        <x-mary-badge value="Segera hadir" class="badge-secondary badge-lg mt-2" />
    </div>
</x-layouts::app>
