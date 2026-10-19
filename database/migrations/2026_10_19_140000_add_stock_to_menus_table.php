<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            // Sisa stok. Null = tidak dibatasi (stok tidak dilacak). Berkurang saat order dibayar; 0 = otomatis habis.
            $table->unsignedInteger('stock')->nullable()->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }
};
