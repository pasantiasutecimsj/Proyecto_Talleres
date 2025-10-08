<?php
// app/Models/Clase.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Clase extends Model
{
    protected $table = 'clases';

    // ahora usamos docente_user_id como FK hacia docentes.user_id
    protected $fillable = ['fecha_hora', 'asistentes_maximos', 'docente_user_id', 'taller_id'];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];

    /** Relaciones */
    public function taller()
    {
        return $this->belongsTo(Taller::class);
    }

    public function docente()
    {
        return $this->belongsTo(Docente::class, 'docente_user_id', 'user_id');
    }

    public function asistentes()
    {
        return $this->belongsToMany(
            Asistente::class,
            'clase_asistentes',
            'clase_id',
            'ci_asistente'
        )->withPivot('asistio')
         ->withTimestamps();
    }

    /** ===== Helpers / Scopes ===== */
    public function esFutura(): bool
    {
        return $this->fecha_hora instanceof Carbon
            ? $this->fecha_hora->greaterThanOrEqualTo(now())
            : Carbon::parse($this->fecha_hora)->greaterThanOrEqualTo(now());
    }

    public function scopeFuturas($q)
    {
        return $q->where('fecha_hora', '>=', now());
    }

    public function scopePasadas($q)
    {
        return $q->where('fecha_hora', '<', now());
    }
}
