<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ActivoScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Filtra SIEMPRE por Activo = 1 (true)
        $builder->where($model->getTable().'.Activo', true);
    }
}
