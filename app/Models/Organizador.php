<?php

// app/Models/Organizador.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organizador extends Model
{
    protected $table = 'organizadores';
    protected $primaryKey = 'ci';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['ci'];

    public function talleres() {
        return $this->belongsToMany(Taller::class, 'talleres_organizadores', 'ci_organizador', 'taller_id')
                    ->withTimestamps();
    }
}
