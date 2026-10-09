<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoVehiculo extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'tipos_vehiculo';

    protected $fillable = ['nombre', 'descripcion', 'tarifa_base', 'activo'];

    protected $casts = ['tarifa_base' => 'decimal:2', 'activo' => 'boolean'];

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    public function espacios(): HasMany
    {
        return $this->hasMany(Espacio::class);
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when($termino, function (Builder $q) use ($termino) {
            $t = "%{$termino}%";
            $q->where(fn (Builder $w) => $w
                ->where('nombre', 'like', $t)
                ->orWhere('descripcion', 'like', $t));
        });
    }
}
