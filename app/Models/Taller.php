<?php

// app/Models/Taller.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Taller extends Model
{
    protected $table = 'talleres';
    protected $fillable = ['nombre','descripcion','id_ciudad','calle','numero'];

    public function clases() {
        return $this->hasMany(Clase::class);
    }

    public function organizadores() {
        return $this->belongsToMany(Organizador::class, 'talleres_organizadores', 'taller_id', 'ci_organizador')
                    ->withTimestamps();
    }
}
