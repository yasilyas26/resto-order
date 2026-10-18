<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            // Salinan nama dan harga disimpan di sini, jadi mengubah harga menu besok tidak mengubah riwayat.
            $table->foreignId('menu_id')->nullable()->constrained()->nullOnDelete();
            $table->string('menu_name');
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('qty');
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('line_total');
            $table->boolean('is_unavailable')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
