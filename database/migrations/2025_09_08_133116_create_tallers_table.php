<?php

// 2025_01_01_000030_create_talleres_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('talleres', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('id_ciudad'); // viene de api_personas (referencia externa)
            $table->string('calle')->nullable();     // el * del diagrama lo tomo como opcional
            $table->string('numero', 20)->nullable();
            $table->timestamps();

            $table->index('id_ciudad'); // índice para consultas, sin FK real
        });
    }
    public function down(): void {
        Schema::dropIfExists('talleres');
    }
};
