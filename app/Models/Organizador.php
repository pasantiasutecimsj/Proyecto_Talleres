<?php
// app/Models/Organizador.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasActivo;

class Organizador extends Model
{
    use HasActivo;

    protected $table = 'organizadores';
    protected $primaryKey = 'ci';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['ci', 'Activo'];
    protected $casts   = ['Activo' => 'boolean'];

    public function talleres()
    {
        // Si Taller usa HasActivo, ya viene filtrado por Activo=1
        return $this->belongsToMany(Taller::class, 'talleres_organizadores', 'ci_organizador', 'taller_id')
                    ->withTimestamps();
    }
}
