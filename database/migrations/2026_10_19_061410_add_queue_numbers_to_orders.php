<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penghitung nomor antrean per hari per jenis order. Baris ini dikunci saat nomor dinaikkan,
        // supaya dua order yang lunas bersamaan tidak mendapat nomor kembar.
        Schema::create('queue_counters', function (Blueprint $table) {
            $table->id();
            $table->date('counter_date');
            $table->string('order_type', 20);
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['counter_date', 'order_type']);
        });

        // Nomor baru ada setelah order lunas, jadi kosong selama masih menunggu bayar.
        Schema::table('orders', function (Blueprint $table) {
            $table->date('queue_date')->nullable()->after('paid_at');
            $table->unsignedInteger('queue_number')->nullable()->after('queue_date');
            $table->unique(['queue_date', 'order_type', 'queue_number']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['queue_date', 'order_type', 'queue_number']);
            $table->dropColumn(['queue_date', 'queue_number']);
        });

        Schema::dropIfExists('queue_counters');
    }
};
