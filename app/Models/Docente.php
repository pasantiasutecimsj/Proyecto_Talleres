<?php

// app/Models/Docente.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    protected $table = 'docentes';
    protected $primaryKey = 'ci';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['ci'];

    public function clases() {
        return $this->hasMany(Clase::class, 'ci_docente', 'ci');
    }
}
