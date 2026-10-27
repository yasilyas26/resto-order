<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\OptionController;
use App\Http\Controllers\Admin\OptionGroupController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\TrashController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PortalController;
use App\Http\Controllers\Payments\MidtransNotificationController;
use App\Http\Controllers\Staff\OrderBoardController;
use App\Http\Controllers\Staff\RefundHistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Visitor\CheckoutController;
use App\Http\Controllers\Visitor\MenuController as VisitorMenuController;
use App\Http\Controllers\Visitor\OnboardingController;
use App\Http\Controllers\Visitor\OrderHistoryController;
use App\Http\Controllers\Visitor\OrderStatusController;
use App\Http\Controllers\Visitor\PaymentQrController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Sisi pengunjung (guest dan pelanggan). Tidak ada link ke /admin atau /staff di sini (PRD G-13).
// ---------------------------------------------------------------------------

// Pilih meja: hanya lewat scan QR (/m/{kode}). Dibatasi supaya kode tidak bisa ditebak-tebak.
// Karyawan/admin yang membuka halaman ini dikembalikan ke area kerjanya (middleware 'visitor').
Route::middleware('visitor')->group(function () {
    Route::get('/', [OnboardingController::class, 'index'])->name('home');
    Route::get('/m/{code}', [OnboardingController::class, 'scan'])->middleware('throttle:table-code')->name('table.scan');
    Route::get('/ganti-meja', [OnboardingController::class, 'change'])->name('table.change');
});

// Memesan dan melihat riwayat: butuh meja yang sudah dipilih (scan QR atau kode manual) dan masih aktif.
// Berlaku juga untuk pelanggan yang sudah login: tanpa meja, mereka diarahkan ke halaman pilih meja.
Route::middleware(['visitor', 'table'])->group(function () {
    Route::get('/menu', [VisitorMenuController::class, 'index'])->name('menu');
    Route::get('/keranjang', [VisitorMenuController::class, 'cart'])->name('cart');

    // Riwayat hanya untuk pelanggan yang login: tamu tidak punya riwayat (tamu dialihkan ke halaman login).
    Route::middleware('auth')->group(function () {
        Route::get('/riwayat', [OrderHistoryController::class, 'index'])->name('riwayat');
        Route::post('/riwayat/lookup', [OrderHistoryController::class, 'lookup'])->middleware('throttle:30,1')->name('riwayat.lookup');
        Route::get('/riwayat/data', [OrderHistoryController::class, 'data'])->name('riwayat.data');
    });
});

// Checkout dan pembayaran. Total selalu dihitung ulang di server.
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
// Tujuan balik dari Midtrans: hanya mengalihkan, keamanannya dicek di halaman tujuan.
// Harus di atas /order/{order}, kalau tidak "selesai" dianggap kode order.
Route::get('/order/selesai', [OrderStatusController::class, 'finish'])->name('order.finish');

// Halaman dan aksi order hanya untuk pemiliknya (browser yang membuat order, atau akun yang sama).
// Tahu kode order saja tidak cukup: URL yang disalin ke browser lain ditolak.
Route::middleware('order.owner')->group(function () {
    Route::post('/order/{order}/bayar', [CheckoutController::class, 'pay'])->middleware('throttle:checkout')->name('order.pay');
    Route::post('/order/{order}/ganti-metode', [CheckoutController::class, 'changeMethod'])->middleware('throttle:checkout')->name('order.change-method');

    Route::get('/order/{order}', [OrderStatusController::class, 'show'])->name('order.show');
    Route::get('/order/{order}/bayar', [OrderStatusController::class, 'checkout'])->name('order.checkout');
    Route::get('/order/{order}/qr', PaymentQrController::class)->middleware('throttle:30,1')->name('order.qr');
    Route::get('/order/{order}/hasil', [OrderStatusController::class, 'result'])->name('order.result');
    Route::get('/order/{order}/status', [OrderStatusController::class, 'status'])->middleware('throttle:60,1')->name('order.status');
    Route::post('/order/{order}/cek-pembayaran', [OrderStatusController::class, 'recheck'])->middleware('throttle:10,1')->name('order.recheck');
});

// Webhook Midtrans: satu-satunya jalur yang menetapkan order lunas (tanpa CSRF, diamankan signature).
Route::post('/payments/midtrans/notification', MidtransNotificationController::class)->name('payments.midtrans.notification');

// Login Google (khusus pelanggan).
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('guest')->name('auth.google');
// Callback dipakai dua keperluan: login (tamu) dan konfirmasi hapus akun (sudah login), jadi tanpa middleware guest.
// Alamatnya sama, jadi tidak perlu mendaftarkan redirect URI baru di Google Cloud Console.
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/auth/google/konfirmasi-hapus', [GoogleAuthController::class, 'confirmDelete'])->middleware('auth')->name('auth.google.confirm-delete');

// Halaman bawaan Breeze. Pelanggan yang baru login/daftar diarahkan ke riwayat.
Route::get('/dashboard', fn () => redirect()->route('riwayat'))->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Area admin: auth dulu (tamu dilempar ke login), baru role (salah role = 403).
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::patch('account/name', [AccountController::class, 'updateName'])->name('account.name');
        Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password');
        Route::get('dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

        Route::resource('categories', CategoryController::class)->except('show');
        Route::post('menus/{menu}/availability', [MenuController::class, 'toggleAvailability'])->name('menus.availability');
        Route::resource('menus', MenuController::class)->except('show');

        Route::post('option-groups/level', [OptionGroupController::class, 'storeLevels'])->name('option-groups.levels');
        Route::resource('option-groups', OptionGroupController::class)->except('show');
        Route::post('option-groups/{option_group}/options', [OptionController::class, 'store'])->name('options.store');
        Route::put('options/{option}', [OptionController::class, 'update'])->name('options.update');
        Route::delete('options/{option}', [OptionController::class, 'destroy'])->name('options.destroy');

        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');

        Route::post('tables/qr/download', [TableController::class, 'qrDownloadMany'])->name('tables.qr.download-many');
        Route::resource('tables', TableController::class)->except('show');
        Route::post('tables/{table}/regenerate', [TableController::class, 'regenerate'])->name('tables.regenerate');
        Route::get('tables/{table}/qr', [TableController::class, 'qr'])->name('tables.qr');
        Route::get('tables/{table}/qr/download', [TableController::class, 'qrDownload'])->name('tables.qr.download');

        Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
        Route::post('trash/empty', [TrashController::class, 'empty'])->name('trash.empty');
        Route::post('trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');
        Route::delete('trash/{type}/{id}', [TrashController::class, 'forceDelete'])->name('trash.force');
    });

// Area karyawan: admin juga boleh masuk (PRD: admin = semua yang bisa dilakukan role lain).
Route::middleware(['auth', 'role:admin,staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::redirect('/', '/staff/orders')->name('index');

        // Papan order (PRD K-01..K-07). Data dipolling tiap ±5 detik, aksi lewat fetch() dan membalas kartu terbaru.
        Route::get('orders', [OrderBoardController::class, 'index'])->name('orders.index');
        Route::get('orders/data', [OrderBoardController::class, 'data'])->name('orders.data');
        Route::post('orders/{order}/start', [OrderBoardController::class, 'start'])->name('orders.start');
        Route::post('orders/{order}/complete', [OrderBoardController::class, 'complete'])->name('orders.complete');
        Route::post('orders/{order}/items/{item}/serve', [OrderBoardController::class, 'serve'])->name('orders.items.serve');
        Route::post('orders/{order}/items/{item}/unavailable', [OrderBoardController::class, 'unavailable'])->name('orders.items.unavailable');

        // Riwayat dan refund (kasir adalah karyawan: PRD tidak punya role kasir terpisah).
        Route::get('riwayat', [RefundHistoryController::class, 'index'])->name('history.index');
        Route::get('riwayat/data', [RefundHistoryController::class, 'data'])->name('history.data');
        Route::post('riwayat/{order}/refund', [RefundHistoryController::class, 'refund'])->name('history.refund');
    });

// Login khusus karyawan dan admin. Alamat ini tidak pernah ditautkan dari halaman pengunjung.
Route::middleware('guest')->group(function () {
    Route::get('/portal', [PortalController::class, 'show'])->name('portal.login');
    Route::post('/portal', [PortalController::class, 'store']);
});
Route::post('/portal/logout', [PortalController::class, 'logout'])->middleware('auth')->name('portal.logout');

require __DIR__.'/auth.php';
