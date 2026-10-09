<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $fillable = ['nombre', 'requiere_referencia', 'activo'];

    protected $casts = [
        'requiere_referencia' => 'boolean',
        'activo'              => 'boolean',
    ];

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /** Combos del formulario de cobro (US-0402): solo métodos activos. */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('nombre');
    }
}
