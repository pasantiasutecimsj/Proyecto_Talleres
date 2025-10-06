<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tablas de dominio NO pivot.
     * Dejo afuera pivots (clase_asistentes, talleres_organizadores)
     * y tablas de sistema de Laravel.
     */
    private array $tables = [
        'asistentes',
        'docentes',
        'organizadores',
        'talleres',
        'clases',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasColumn($table, 'Activo')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    // intento ubicarla al final o después de updated_at si existe
                    $hasUpdatedAt = Schema::hasColumn($table, 'updated_at');

                    $column = $t->boolean('Activo')->default(true)->index();
                    if ($hasUpdatedAt) {
                        $column->after('updated_at');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'Activo')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex([$t->getTable().'_Activo_index'] ?? []); // por si aplica
                    $t->dropColumn('Activo');
                });
            }
        }
    }
};
