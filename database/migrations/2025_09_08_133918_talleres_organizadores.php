<?php

// 2025_01_01_000060_create_talleres_organizadores_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('talleres_organizadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taller_id')->constrained('talleres')->cascadeOnDelete();
            $table->char('ci_organizador', 8);
            $table->timestamps();

            $table->foreign('ci_organizador')->references('ci')->on('organizadores')->cascadeOnUpdate()->restrictOnDelete();
            $table->unique(['taller_id', 'ci_organizador']); // evita duplicados
        });
    }
    public function down(): void {
        Schema::dropIfExists('talleres_organizadores');
    }
};
