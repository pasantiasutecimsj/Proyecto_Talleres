<?php

// 2025_01_01_000010_create_asistentes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('asistentes', function (Blueprint $table) {
            $table->char('ci', 8)->primary();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('asistentes');
    }
};

