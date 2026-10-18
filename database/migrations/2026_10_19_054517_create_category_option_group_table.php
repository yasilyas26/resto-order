<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_option_group', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete: grup yang masih terpasang di kategori tidak bisa dihapus permanen
            $table->foreignId('option_group_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->primary(['category_id', 'option_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_option_group');
    }
};
