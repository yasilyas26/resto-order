<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Kapan karyawan menandai order selesai (untuk riwayat dan batas tampil di papan).
            $table->timestamp('completed_at')->nullable()->after('paid_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Centang karyawan "item sudah dibawa ke meja" (PRD K-04). Null = belum.
            $table->timestamp('served_at')->nullable()->after('is_unavailable');
            // Salinan nama kategori saat dipesan, untuk tag stasiun di papan (Dapur/Dimsum/Minum).
            // Disalin seperti nama dan harga, jadi tetap benar walau menu atau kategorinya dihapus.
            $table->string('category_name')->nullable()->after('menu_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['served_at', 'category_name']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
