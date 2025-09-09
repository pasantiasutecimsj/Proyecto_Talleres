<?php

// app/Models/Asistente.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistente extends Model
{
    protected $table = 'asistentes';
    protected $primaryKey = 'ci';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['ci'];

    public function clases() {
        return $this->belongsToMany(Clase::class, 'clase_asistentes', 'ci_asistente', 'clase_id')
                    ->withPivot('asistio')
                    ->withTimestamps();
    }
}
