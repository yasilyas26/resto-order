@props(['src', 'alt' => '', 'imgClass' => ''])

{{-- Foto dengan kerangka (skeleton) beranimasi selama gambar dimuat. Kalau gambarnya gagal dimuat, diganti ikon mangkuk.
     Mengisi penuh induknya (h-full w-full); bentuk sudut dan efek lain (mis. grayscale) lewat imgClass. --}}
<div x-data="{ loaded: false, failed: false }"
    x-init="$nextTick(() => { const img = $refs.img; if (img && img.complete) { img.naturalWidth ? loaded = true : failed = true } })"
    {{ $attributes->class(['relative h-full w-full overflow-hidden']) }}>
    <x-skeleton x-show="!loaded && !failed" class="absolute inset-0 h-full w-full rounded-none" />

    <img x-ref="img" src="{{ $src }}" alt="{{ $alt }}" loading="lazy" x-show="!failed"
        @load="loaded = true" @@error="failed = true"
        class="h-full w-full object-cover transition-opacity duration-500 {{ $imgClass }}" :class="loaded ? 'opacity-100' : 'opacity-0'">

    <div x-show="failed" x-cloak class="flex h-full w-full items-center justify-center bg-brand-50 text-brand-300">
        <x-icon name="bowl" class="h-1/2 w-1/2" />
    </div>
</div>
