@props(['type' => 'success', 'title', 'message' => null, 'button' => null])

{{-- Modal kabar untuk hasil sebuah aksi: success (berhasil), error (gagal), atau info (mis. tidak ada perubahan).
     Terbuka sendiri saat halaman dimuat; tutup lewat tombol, Esc, atau klik di luar. --}}
@php
    $look = [
        'success' => ['icon' => 'check', 'ring' => 'bg-emerald-100 text-emerald-600', 'btn' => 'bg-brand-600 hover:bg-brand-700', 'label' => 'Oke, mengerti', 'role' => 'alertdialog'],
        'error' => ['icon' => 'alert', 'ring' => 'bg-red-100 text-red-600', 'btn' => 'bg-brand-600 hover:bg-brand-700', 'label' => 'Coba lagi', 'role' => 'alertdialog'],
        'info' => ['icon' => 'info', 'ring' => 'bg-sky-100 text-sky-600', 'btn' => 'bg-gray-800 hover:bg-gray-900', 'label' => 'Oke, mengerti', 'role' => 'dialog'],
    ][$type] ?? null;
    abort_if($look === null, 500, "Jenis modal tidak dikenal: {$type}");
@endphp

<div x-data="{ open: true }" x-show="open" x-cloak x-transition.opacity.duration.200ms
    @keydown.escape.window="open = false"
    x-effect="if (open) $nextTick(() => $refs.ok?.focus())"
    class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 p-4" @click.self="open = false">
    <div role="{{ $look['role'] }}" aria-modal="true" aria-labelledby="feedback-title-{{ $type }}" @if ($message) aria-describedby="feedback-message-{{ $type }}" @endif
        class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full {{ $look['ring'] }}">
            <x-icon :name="$look['icon']" class="h-8 w-8" />
        </span>
        <h2 id="feedback-title-{{ $type }}" class="mt-4 text-lg font-extrabold tracking-tight">{{ $title }}</h2>
        @if ($message)
            <p id="feedback-message-{{ $type }}" class="mt-2 text-sm text-gray-600">{{ $message }}</p>
        @endif
        <button type="button" x-ref="ok" @click="open = false"
            class="mt-6 w-full rounded-xl px-4 py-3 text-sm font-bold text-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 {{ $look['btn'] }}">{{ $button ?? $look['label'] }}</button>
    </div>
</div>
