<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasActivo;

class Taller extends Model
{
    use HasActivo;

    protected $table = 'talleres';

    protected $fillable = ['nombre','descripcion','id_ciudad','calle','numero','Activo'];

    protected $casts = [
        'Activo' => 'boolean',
    ];

    public function clases()
    {
        // Si Clase también usa HasActivo, viene filtrado
        return $this->hasMany(Clase::class);
    }

    public function organizadores()
    {
        // Pivot actualizado: organizador_user_id ↔ organizadores.user_id
        return $this->belongsToMany(
            Organizador::class,
            'talleres_organizadores',
            'taller_id',            // FK en pivot hacia este modelo (taller)
            'organizador_user_id'   // FK en pivot hacia Organizador
        )->withTimestamps();
    }
}
