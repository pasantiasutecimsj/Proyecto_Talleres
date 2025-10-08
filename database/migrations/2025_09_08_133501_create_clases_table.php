<?php
// database/migrations/2025_01_01_000040_create_clases_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('clases', function (Blueprint $table) {
            $table->id();

            $table->dateTime('fecha_hora');
            $table->unsignedInteger('asistentes_maximos')->nullable();

            // nuevo vínculo al docente (local → docentes.user_id)
            $table->unsignedBigInteger('docente_user_id');

            $table->foreignId('taller_id')
                  ->constrained('talleres')
                  ->cascadeOnDelete();

            $table->boolean('Activo')->default(true)->index();
            $table->timestamps();

            // FK local para asegurar existencia de docente en nuestra tabla
            $table->foreign('docente_user_id')
                  ->references('user_id')->on('docentes')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->index(['taller_id', 'fecha_hora']);
            $table->index('docente_user_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('clases');
    }
};
