<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tarifa extends Model
{
    /** Valores exactos del ENUM de la migración. */
    public const TIPOS = ['HORA', 'DIA', 'NOCHE', 'MENSUAL', 'ANUAL', 'EVENTO'];

    public const TIPO_LABELS = [
        'HORA'    => 'Por hora',
        'DIA'     => 'Por día',
        'NOCHE'   => 'Noche',
        'MENSUAL' => 'Mensual',
        'ANUAL'   => 'Anual',
        'EVENTO'  => 'Evento',
    ];

    protected $table = 'tarifas';

    protected $fillable = [
        'nombre', 'tipo_vehiculo_id', 'tipo_tarifa', 'precio', 'periodo',
        'hora_inicio', 'hora_fin', 'vigencia_desde', 'vigencia_hasta', 'activo',
    ];

    protected $casts = [
        'precio'         => 'decimal:2',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
        'activo'         => 'boolean',
    ];

    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(ContratoPension::class);
    }

    /**
     * US-0302: activa y cuya vigencia incluye a la fecha indicada (hoy por defecto).
     * `vigencia_desde`/`vigencia_hasta` nulas = sin límite por ese lado.
     */
    public function scopeVigente(Builder $query, ?string $fecha = null): Builder
    {
        $fecha = $fecha ?? now()->toDateString();

        return $query
            ->where('activo', true)
            ->where(fn (Builder $q) => $q
                ->whereNull('vigencia_desde')->orWhere('vigencia_desde', '<=', $fecha))
            ->where(fn (Builder $q) => $q
                ->whereNull('vigencia_hasta')->orWhere('vigencia_hasta', '>=', $fecha));
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when($termino, function (Builder $q) use ($termino) {
            $t = "%{$termino}%";
            $q->where(fn (Builder $w) => $w
                ->where('nombre', 'like', $t)
                ->orWhere('periodo', 'like', $t));
        });
    }

    /** ¿Vigente según los datos del propio modelo? (comparación lexicográfica de Y-m-d). */
    public function esVigente(?string $fecha = null): bool
    {
        $fecha = $fecha ?? now()->toDateString();

        if (! $this->activo) {
            return false;
        }
        if ($this->vigencia_desde && $this->vigencia_desde->toDateString() > $fecha) {
            return false;
        }
        if ($this->vigencia_hasta && $this->vigencia_hasta->toDateString() < $fecha) {
            return false;
        }

        return true;
    }

    /** Texto para los <select>: "Mensual · Automovil · $1,500.00". */
    public function getEtiquetaAttribute(): string
    {
        $tipo = self::TIPO_LABELS[$this->tipo_tarifa] ?? $this->tipo_tarifa;

        return sprintf(
            '%s · %s · $%s',
            $tipo,
            $this->tipoVehiculo?->nombre ?? '—',
            number_format((float) $this->precio, 2)
        );
    }
}
