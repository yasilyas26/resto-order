@props(['name'])

{{-- Logo kecil metode bayar. Kalau file resmi ada di public/images/payments/{nama}.svg (atau .png), itu yang dipakai;
     kalau belum, tampil lencana bergaya warna mereknya. Logo resmi adalah merek dagang masing-masing, jadi unggah
     filenya sendiri ke folder itu bila sudah punya izin pemakaiannya. --}}
@php
    $badges = [
        'qris' => ['QRIS', 'background:#fff;color:#e60012;box-shadow:inset 0 0 0 1.5px #e60012'],
        'gopay' => ['GoPay', 'background:#00aa13;color:#fff'],
        'shopeepay' => ['ShopeePay', 'background:#ee4d2d;color:#fff;font-size:8px'],
        'bca_va' => ['BCA', 'background:#0060af;color:#fff'],
        'bni_va' => ['BNI', 'background:#f15a23;color:#fff'],
        'bri_va' => ['BRI', 'background:#00529c;color:#fff'],
        'permata_va' => ['permata', 'background:#00a651;color:#fff;font-size:8px'],
        'cimb_va' => ['CIMB', 'background:#ec1c24;color:#fff'],
        'mandiri_bill' => ['mandiri', 'background:#003d79;color:#ffb700;font-size:8px'],
    ];
    [$text, $style] = $badges[$name] ?? [strtoupper(substr($name, 0, 4)), 'background:#e5e7eb;color:#374151'];
    $file = collect(['svg', 'png'])->first(fn ($ext) => file_exists(public_path("images/payments/{$name}.{$ext}")));
@endphp

@if ($file)
    <img src="{{ asset("images/payments/{$name}.{$file}") }}" alt="" class="h-8 w-24 shrink-0 object-contain">
@else
    <span aria-hidden="true" class="flex h-8 w-24 shrink-0 items-center justify-center rounded-md px-0.5 text-[10px] font-extrabold leading-none tracking-tight" style="{{ $style }}">{{ $text }}</span>
@endif
