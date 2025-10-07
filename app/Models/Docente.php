<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    protected $table = 'docentes';
    protected $primaryKey = 'ci';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['ci', 'Activo'];

    protected $casts = [
        'Activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Activos por defecto
        static::addGlobalScope('activo', function (Builder $q) {
            $q->where('Activo', 1);
        });
    }

    /** ===== Scopes helpers (como en Organizador) ===== */
    public function scopeConInactivos(Builder $q): Builder
    {
        return $q->withoutGlobalScope('activo');
    }

    public function scopeSoloInactivos(Builder $q): Builder
    {
        return $q->withoutGlobalScope('activo')->where('Activo', 0);
    }

    /** Atajos */
    public function desactivar(): void { $this->forceFill(['Activo' => 0])->save(); }
    public function restaurar(): void  { $this->forceFill(['Activo' => 1])->save(); }

    public function clases() {
        return $this->hasMany(Clase::class, 'ci_docente', 'ci');
    }
}
