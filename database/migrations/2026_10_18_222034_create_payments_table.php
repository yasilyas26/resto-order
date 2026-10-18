<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            // Satu order bisa punya beberapa percobaan bayar, tiap percobaan ID-nya unik (ORD-XXXXXX-1, -2, ...).
            $table->string('midtrans_order_id', 50)->unique();
            $table->string('snap_token')->nullable();
            $table->string('transaction_status', 30)->nullable();
            $table->string('payment_type', 50)->nullable();
            $table->unsignedInteger('gross_amount');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
