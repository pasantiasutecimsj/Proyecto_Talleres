<?php

// 2025_01_01_000040_create_clases_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('clases', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha_hora');
            $table->unsignedInteger('asistentes_maximos')->nullable();
            $table->char('ci_docente', 8);
            $table->foreignId('taller_id')->constrained('talleres')->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('ci_docente')->references('ci')->on('docentes')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['taller_id', 'fecha_hora']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('clases');
    }
};
