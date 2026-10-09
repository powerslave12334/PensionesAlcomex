<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    /** Valores exactos del ENUM de la migración. */
    public const ESTADOS = ['PENDIENTE', 'PAGADO', 'CANCELADO', 'REEMBOLSADO'];

    public const ESTADO_LABELS = [
        'PENDIENTE'   => 'Pendiente',
        'PAGADO'      => 'Pagado',
        'CANCELADO'   => 'Cancelado',
        'REEMBOLSADO' => 'Reembolsado',
    ];

    public const ESTADO_CLASSES = [
        'PENDIENTE'   => 'bg-secondary',
        'PAGADO'      => 'bg-success',
        'CANCELADO'   => 'bg-danger',
        'REEMBOLSADO' => 'bg-warning text-dark',
    ];

    /** Matriz de transiciones (US-0406). Vacío = estado final: el registro no cambia. */
    public const TRANSICIONES = [
        'PENDIENTE'   => ['PAGADO', 'CANCELADO'],
        'PAGADO'      => ['CANCELADO', 'REEMBOLSADO'],
        'CANCELADO'   => [],
        'REEMBOLSADO' => [],
    ];

    protected $fillable = [
        'contrato_pension_id', 'cliente_id', 'numero_recibo', 'fecha_pago',
        'periodo_inicio', 'periodo_fin', 'subtotal', 'descuento', 'impuesto',
        'total', 'metodo_pago_id', 'referencia', 'estado', 'usuario_id',
        'observaciones',
    ];

    protected $casts = [
        'fecha_pago'     => 'datetime',
        'periodo_inicio' => 'date',
        'periodo_fin'    => 'date',
        'subtotal'       => 'decimal:2',
        'descuento'      => 'decimal:2',
        'impuesto'       => 'decimal:2',
        'total'          => 'decimal:2',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoPension::class, 'contrato_pension_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Filtros del historial (US-0404): texto libre, estado, método, contrato,
     * cliente y rango de fechas. Devuelve el Builder para poder clonarlo y
     * calcular el resumen con los mismos criterios.
     */
    public function scopeFiltrar(
        Builder $query,
        ?string $q = null,
        ?string $estado = null,
        ?int $metodoId = null,
        ?int $contratoId = null,
        ?int $clienteId = null,
        ?string $desde = null,
        ?string $hasta = null
    ): Builder {
        return $query
            ->when($q, fn (Builder $b, string $t) => $b->where(function ($w) use ($t) {
                $w->where('numero_recibo', 'like', "%{$t}%")
                    ->orWhereHas('contrato', fn ($c) => $c->where('numero_contrato', 'like', "%{$t}%"))
                    ->orWhereHas('cliente', fn ($c) => $c
                        ->where('nombre', 'like', "%{$t}%")
                        ->orWhere('apellido_paterno', 'like', "%{$t}%"));
            }))
            ->when($estado, fn (Builder $b, string $e) => $b->where('estado', $e))
            ->when($metodoId, fn (Builder $b, int $m) => $b->where('metodo_pago_id', $m))
            ->when($contratoId, fn (Builder $b, int $c) => $b->where('contrato_pension_id', $c))
            ->when($clienteId, fn (Builder $b, int $c) => $b->where('cliente_id', $c))
            ->when($desde, fn (Builder $b, string $d) => $b->whereDate('fecha_pago', '>=', $d))
            ->when($hasta, fn (Builder $b, string $d) => $b->whereDate('fecha_pago', '<=', $d));
    }

    /** Pagos de un día para el corte de caja (US-0405). */
    public function scopeDelDia(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_pago', $fecha);
    }

    /** Estados a los que se puede pasar desde el actual; vacío = final. */
    public function transicionesPermitidas(): array
    {
        return self::TRANSICIONES[$this->estado] ?? [];
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADO_LABELS[$this->estado] ?? $this->estado;
    }

    public function getEstadoClaseAttribute(): string
    {
        return self::ESTADO_CLASSES[$this->estado] ?? 'bg-secondary';
    }
}
