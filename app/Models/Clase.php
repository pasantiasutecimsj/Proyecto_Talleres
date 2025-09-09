<?php

// app/Models/Clase.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clase extends Model
{
    protected $table = 'clases';
    protected $fillable = ['fecha_hora','asistentes_maximos','ci_docente','taller_id'];
    protected $casts = ['fecha_hora' => 'datetime'];

    public function taller() {
        return $this->belongsTo(Taller::class);
    }

    public function docente() {
        return $this->belongsTo(Docente::class, 'ci_docente', 'ci');
    }

    public function asistentes() {
        return $this->belongsToMany(Asistente::class, 'clase_asistentes', 'clase_id', 'ci_asistente')
                    ->withPivot('asistio')
                    ->withTimestamps();
    }
}
