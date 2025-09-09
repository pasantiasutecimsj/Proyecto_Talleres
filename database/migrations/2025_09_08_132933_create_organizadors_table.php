<?php

// 2025_01_01_000020_create_organizadores_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('organizadores', function (Blueprint $table) {
            $table->char('ci', 8)->primary();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('organizadores');
    }
};
