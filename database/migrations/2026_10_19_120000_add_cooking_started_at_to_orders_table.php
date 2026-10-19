<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Kapan karyawan menekan "Mulai Buat". Selisihnya dengan completed_at = waktu masak (kartu Ringkasan admin).
            $table->timestamp('cooking_started_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cooking_started_at');
        });
    }
};
