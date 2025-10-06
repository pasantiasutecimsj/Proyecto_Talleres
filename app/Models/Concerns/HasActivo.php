<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ActivoScope;

trait HasActivo
{
    // Agrega el scope global automáticamente
    protected static function bootHasActivo(): void
    {
        static::addGlobalScope(new ActivoScope);
    }

    // Cast y fillable helper (por si te sirve tenerlo simétrico en modelos)
    protected $casts = [
        'Activo' => 'boolean',
    ];

    // ===== Helpers =====
    public function desactivar(): bool
    {
        $this->Activo = false;
        return $this->save();
    }

    public function restaurar(): bool
    {
        $this->Activo = true;
        return $this->save();
    }

    // ===== Scopes útiles =====
    public function scopeSoloInactivos($q)
    {
        // quitamos el global y filtramos por Activo = 0
        return $q->withoutGlobalScope(ActivoScope::class)->where($this->getTable().'.Activo', false);
    }

    public function scopeConInactivos($q)
    {
        return $q->withoutGlobalScope(ActivoScope::class);
    }
}
