<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Espacio extends Model
{
    protected $table = 'espacios';

    /** Valores exactos del ENUM de la migración. */
    public const ESTADOS = [
        'DISPONIBLE', 'OCUPADO', 'RESERVADO', 'MANTENIMIENTO', 'BLOQUEADO',
    ];

    /** Texto que se muestra en la UI (el valor guardado sigue siendo el ENUM). */
    public const ESTADO_LABELS = [
        'DISPONIBLE'    => 'Libre',
        'OCUPADO'       => 'Ocupado',
        'RESERVADO'     => 'Reservado',
        'MANTENIMIENTO' => 'Mantenimiento',
        'BLOQUEADO'     => 'Bloqueado',
    ];

    public const ESTADO_CLASSES = [
        'DISPONIBLE'    => 'bg-success',
        'OCUPADO'       => 'bg-danger',
        'RESERVADO'     => 'bg-warning text-dark',
        'MANTENIMIENTO' => 'bg-secondary',
        'BLOQUEADO'     => 'bg-dark',
    ];

    protected $fillable = [
        'zona_id', 'codigo', 'tipo_vehiculo_id', 'estado',
        'numero', 'piso', 'observaciones', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionEspacio::class);
    }

    /**
     * Asignación vigente (fecha_fin IS NULL). La regla de negocio garantiza
     * una sola asignación vigente por cajón.
     */
    public function asignacionVigiente(): HasOne
    {
        return $this->hasOne(AsignacionEspacio::class, 'espacio_id')
            ->whereNull('fecha_fin');
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADO_LABELS[$this->estado] ?? $this->estado;
    }

    public function getEstadoClaseAttribute(): string
    {
        return self::ESTADO_CLASSES[$this->estado] ?? 'bg-secondary';
    }

    public function scopeFiltrar(Builder $query, ?int $zonaId, ?string $estado): Builder
    {
        return $query
            ->when($zonaId, fn (Builder $q) => $q->where('zona_id', $zonaId))
            ->when($estado, fn (Builder $q) => $q->where('estado', $estado));
    }
}
