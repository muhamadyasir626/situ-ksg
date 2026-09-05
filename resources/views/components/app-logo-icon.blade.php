@props(['variant' => 'horizontal'])

@php($vertical = $variant === 'vertical')

<img
    src="{{ asset($vertical ? 'images/logo-situ_ksg-2.jpeg' : 'images/logo-situ_ksg-1.jpeg') }}"
    alt="SITU-KSG"
    width="1280"
    height="{{ $vertical ? 1142 : 433 }}"
    {{ $attributes }}
/>
