<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zona extends Model
{
    protected $table = 'zonas';

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

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
