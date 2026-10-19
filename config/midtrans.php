<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Midtrans
    |--------------------------------------------------------------------------
    |
    | Server key hanya dipakai di server dan tidak pernah dikirim ke browser.
    | Browser hanya memegang client key (untuk memuat popup Snap).
    | Mulai dengan sandbox; production adalah tahap terakhir (Fase 7).
    |
    */

    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),

    // Lama pembayaran berlaku sejak Snap token dibuat. Lewat dari ini, order tidak pernah sampai ke dapur.
    'expiry_minutes' => (int) env('MIDTRANS_EXPIRY_MINUTES', 15),

    // API server-ke-server (status, gambar QR). Berbeda dari snap_js_url yang dimuat browser.
    'api_url' => env('MIDTRANS_IS_PRODUCTION', false) ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com',

    'snap_js_url' => env('MIDTRANS_IS_PRODUCTION', false)
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js',

];
