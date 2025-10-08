<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    protected $table = 'docentes';

    // Ahora la PK es el ID remoto de api_usuarios
    protected $primaryKey = 'user_id';
    public $incrementing = false;     // viene de otra API, no es autoincremental local
    protected $keyType = 'int';

    protected $fillable = ['user_id', 'ci', 'Activo'];

    protected $casts = [
        'Activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Activos por defecto (igual que antes)
        static::addGlobalScope('activo', function (Builder $q) {
            $q->where('Activo', 1);
        });
    }

    /** ===== Scopes helpers ===== */
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

    /** Relación con clases (por user_id ahora) */
    public function clases()
    {
        return $this->hasMany(Clase::class, 'docente_user_id', 'user_id');
    }
}
