<?php
// app/Models/Clase.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Clase extends Model
{
    protected $table = 'clases';
    protected $fillable = ['fecha_hora','asistentes_maximos','ci_docente','taller_id'];
    protected $casts = ['fecha_hora' => 'datetime'];

    public function taller()  { return $this->belongsTo(Taller::class); }
    public function docente() { return $this->belongsTo(Docente::class, 'ci_docente', 'ci'); }

    public function asistentes()
    {
        return $this->belongsToMany(Asistente::class, 'clase_asistentes', 'clase_id', 'ci_asistente')
                    ->withPivot('asistio')
                    ->withTimestamps();
    }

    // ===== Helpers / Scopes
    public function esFutura(): bool
    {
        return $this->fecha_hora instanceof Carbon
            ? $this->fecha_hora->greaterThanOrEqualTo(now())
            : Carbon::parse($this->fecha_hora)->greaterThanOrEqualTo(now());
    }

    public function scopeFuturas($q) { return $q->where('fecha_hora', '>=', now()); }
    public function scopePasadas($q) { return $q->where('fecha_hora', '<',  now()); }
}
