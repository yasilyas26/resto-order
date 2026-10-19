{{-- Kotak abu pengganti isi yang sedang dimuat: denyut halus + kilau yang menyapu. Atur ukuran lewat class (mis. h-4 w-32).
     Semua gerak berhenti otomatis kalau pengguna meminta gerak dikurangi. --}}
<div {{ $attributes->class([
    'relative isolate overflow-hidden rounded-md bg-gray-200 animate-pulse motion-reduce:animate-none',
    'before:absolute before:inset-0 before:-translate-x-full before:animate-shimmer motion-reduce:before:animate-none',
    'before:bg-gradient-to-r before:from-transparent before:via-white/70 before:to-transparent',
]) }} aria-hidden="true"></div>
