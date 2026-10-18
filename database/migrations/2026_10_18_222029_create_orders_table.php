<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Order tidak pernah dihapus (catatan keuangan), jadi semua FK memakai restrict/null, tanpa soft delete.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // kode publik, mis. ORD-7F3K9Q
            $table->foreignId('table_id')->constrained('tables')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 100);
            $table->string('customer_contact', 100)->nullable();
            $table->string('order_type', 20)->default('dine_in'); // dine_in | takeaway
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('total');

            // Tiga status terpisah, sengaja tidak digabung (lihat PRD "Status order").
            $table->string('payment_status', 20)->default('pending')->index(); // hanya diubah webhook Midtrans
            $table->string('kitchen_status', 20)->default('queued')->index();  // hanya diubah karyawan
            $table->string('refund_status', 20)->default('none');

            $table->unsignedInteger('refund_amount')->default(0);
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // batas bayar percobaan terakhir
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
