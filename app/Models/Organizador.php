<?php
// app/Models/Organizador.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasActivo;

class Organizador extends Model
{
    use HasActivo;

    protected $table = 'organizadores';

    // PK ahora es el ID remoto de api_usuarios
    protected $primaryKey = 'user_id';
    public $incrementing = false;   // viene de otra API
    protected $keyType = 'int';

    protected $fillable = ['user_id', 'ci', 'Activo'];
    protected $casts   = ['Activo' => 'boolean'];

    public function talleres()
    {
        // Pivot ajustado a organizador_user_id
        return $this->belongsToMany(
            Taller::class,
            'talleres_organizadores',
            'organizador_user_id', // FK en pivot hacia este modelo
            'taller_id'            // FK en pivot hacia Taller
        )->withTimestamps();
    }
}
