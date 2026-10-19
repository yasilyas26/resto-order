@props(['name'])

{{-- Ikon garis sederhana (stroke mengikuti currentColor), supaya warna dan ukuran diatur lewat class.
     Ukuran bawaan h-5 w-5 hanya dipakai kalau pemanggil tidak memberi class sendiri: kalau digabung,
     dua kelas ukuran bertabrakan dan yang menang ditentukan urutan CSS, bukan urutan penulisan. --}}
<svg {{ $attributes->class([$attributes->has('class') ? null : 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('cart') <circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2.5l2.2 11h10.6L20.5 8H6.2"/> @break
        @case('bank') <path d="M3 10l9-6 9 6"/><path d="M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18"/> @break
        @case('search') <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/> @break
        @case('plus') <path d="M12 5v14M5 12h14"/> @break
        @case('minus') <path d="M5 12h14"/> @break
        @case('x') <path d="M6 6l12 12M18 6L6 18"/> @break
        @case('check') <path d="M5 13l4 4L19 7"/> @break
        @case('arrow-right') <path d="M5 12h14M13 6l6 6-6 6"/> @break
        @case('eye') <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/> @break
        @case('eye-off') <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/> @break
        @case('lock') <rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/> @break
        @case('user') <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/> @break
        @case('bag') <path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6a3 3 0 016 0v2"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('copy') <rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 012-2h9"/> @break
        @case('qr') <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h3M20 17v4"/> @break
        @case('utensils') <path d="M7 3v8M5 3v5a2 2 0 004 0V3M7 11v10M17 3c-2 2-3 5-3 8h3v10"/> @break
        @case('info') <circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/> @break
        @case('shield') <path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/> @break
        @case('pencil') <path d="M4 20h4L19 9l-4-4L4 16v4z"/> @break
        @case('trash') <path d="M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13"/> @break
        @case('receipt') <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6"/> @break
        @case('bowl') <path d="M3 11h18a9 9 0 01-18 0z"/><path d="M8 7c0-2 2-2 2-4M13 7c0-2 2-2 2-4"/> @break
        @case('flame') <path d="M12 3c1 4 5 5 5 10a5 5 0 01-10 0c0-2 1-3 2-4 0 2 1 3 2 3 0-3 1-6 1-9z"/> @break
        @case('logout') <path d="M10 4H5v16h5M15 8l4 4-4 4M19 12H9"/> @break
        @case('key') <rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 11h.01M11 11h.01M15 11h.01M7 14h10"/> @break
        @case('refresh') <path d="M20 11a8 8 0 10-2 6M20 5v6h-6"/> @break
        @case('wallet') <rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M16 14.5h2"/> @break
        @case('id-card') <rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c0-2 6-2 6 0M14 10h4M14 13h3"/> @break
        @case('history') <path d="M3 12a9 9 0 109-9 9 9 0 00-7 3.5M3 4v4h4M12 8v4l3 2"/> @break
        @case('alert') <path d="M12 3l10 17H2L12 3z"/><path d="M12 10v4M12 17h.01"/> @break
        @case('image')<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="M21 16l-5-5-8 8"/> @break
    @endswitch
</svg>
