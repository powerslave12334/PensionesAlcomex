<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre', 'apellido_paterno', 'apellido_materno', 'telefono', 'email',
        'direccion', 'ciudad', 'estado', 'codigo_postal', 'rfc',
        'fecha_nacimiento', 'observaciones', 'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido_paterno} {$this->apellido_materno}");
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when($termino, function (Builder $q) use ($termino) {
            $t = "%{$termino}%";
            $q->where(fn (Builder $w) => $w
                ->where('nombre', 'like', $t)
                ->orWhere('apellido_paterno', 'like', $t)
                ->orWhere('apellido_materno', 'like', $t)
                ->orWhere('email', 'like', $t)
                ->orWhere('telefono', 'like', $t)
                ->orWhere('rfc', 'like', $t));
        });
    }
}
