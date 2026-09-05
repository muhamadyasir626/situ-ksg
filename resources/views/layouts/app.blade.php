<x-layouts::app.sidebar :title="$title ?? null">
    <div class="p-6 lg:p-8">
        {{ $slot }}
    </div>
</x-layouts::app.sidebar>
