<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Metode yang dipilih pelanggan di halaman pilih metode (kunci dari PaymentMethods). Null = bebas pilih di popup Snap.
            $table->string('method', 30)->nullable()->after('snap_token');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
