<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            // number tidak unik di database: keunikan dijaga di validasi dengan mengabaikan baris terhapus
            $table->string('number', 20);
            // code unik selamanya (termasuk yang terhapus), jadi QR lama tidak pernah hidup lagi
            $table->string('code', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
