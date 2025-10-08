<?php
// database/migrations/2025_01_01_000020_create_organizadores_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('organizadores', function (Blueprint $table) {
            // PK = user_id (ID remoto de api_usuarios)
            $table->unsignedBigInteger('user_id')->primary();
            $table->boolean('Activo')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('organizadores');
    }
};
