<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehiculo extends Model
{
    use HasFactory;

    protected $table = 'vehiculos';

    protected $fillable = [
        'cliente_id', 'placas', 'uid_rfid', 'marca', 'modelo', 'anio', 'color',
        'tipo_vehiculo_id', 'vin', 'numero_motor', 'foto_url',
        'observaciones', 'activo',
    ];

    protected $casts = [
        'anio' => 'integer',
        'activo' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(ContratoPension::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoAcceso::class);
    }

    /** Movimiento abierto (el vehículo está dentro del recinto). */
    public function entradaAbierta(): HasOne
    {
        return $this->hasOne(MovimientoAcceso::class)
            ->where('tipo', 'ENTRADA')
            ->whereDoesntHave('salida');
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when($termino, function (Builder $q) use ($termino) {
            $t = "%{$termino}%";
            $q->where(fn (Builder $w) => $w
                ->where('placas', 'like', $t)
                ->orWhere('marca', 'like', $t)
                ->orWhere('modelo', 'like', $t)
                ->orWhere('vin', 'like', $t));
        });
    }
}
