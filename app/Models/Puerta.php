<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Puerta extends Model
{
    protected $table = 'puertas';

    public const TIPOS = ['ENTRADA', 'SALIDA'];

    protected $fillable = ['nombre', 'ubicacion', 'tipo', 'dispositivo_id', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoAcceso::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
