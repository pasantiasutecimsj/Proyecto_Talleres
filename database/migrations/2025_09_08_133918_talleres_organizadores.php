<?php
// database/migrations/2025_01_01_000060_create_talleres_organizadores_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('talleres_organizadores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('taller_id')
                  ->constrained('talleres')
                  ->cascadeOnDelete();

            // nuevo vínculo al organizador (local → organizadores.user_id)
            $table->unsignedBigInteger('organizador_user_id');

            $table->timestamps();

            $table->foreign('organizador_user_id')
                  ->references('user_id')->on('organizadores')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // evita duplicados por par taller-organizador
            $table->unique(['taller_id', 'organizador_user_id']);

            $table->index('organizador_user_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('talleres_organizadores');
    }
};
