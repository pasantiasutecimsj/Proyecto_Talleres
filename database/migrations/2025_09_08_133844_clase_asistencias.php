<?php
// database/migrations/2025_01_01_000050_create_clase_asistentes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('clase_asistentes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clase_id')
                  ->constrained('clases')
                  ->cascadeOnDelete();

            $table->char('ci_asistente', 8);

            $table->boolean('asistio')->default(false);
            $table->timestamps();

            $table->foreign('ci_asistente')
                  ->references('ci')->on('asistentes')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->unique(['clase_id', 'ci_asistente']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('clase_asistentes');
    }
};
