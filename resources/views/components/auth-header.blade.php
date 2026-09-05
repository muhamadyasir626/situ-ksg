@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="text-xl font-semibold">{{ $title }}</h1>
    <p class="text-base-content/70">{{ $description }}</p>
</div>
