<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContratoPension extends Model
{
    /** Valores exactos del ENUM de la migración. */
    public const ESTADOS = [
        'PENDIENTE', 'ACTIVA', 'VENCIDA', 'CANCELADA', 'SUSPENDIDA', 'FINALIZADA',
    ];

    public const ESTADO_LABELS = [
        'PENDIENTE'  => 'Pendiente',
        'ACTIVA'     => 'Activa',
        'VENCIDA'    => 'Vencida',
        'CANCELADA'  => 'Cancelada',
        'SUSPENDIDA' => 'Suspendida',
        'FINALIZADA' => 'Finalizada',
    ];

    public const ESTADO_CLASSES = [
        'PENDIENTE'  => 'bg-secondary',
        'ACTIVA'     => 'bg-success',
        'VENCIDA'    => 'bg-danger',
        'CANCELADA'  => 'bg-dark',
        'SUSPENDIDA' => 'bg-warning text-dark',
        'FINALIZADA' => 'bg-info text-dark',
    ];

    public const PERIODICIDADES = ['MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL'];

    public const PERIODICIDAD_LABELS = [
        'MENSUAL'    => 'Mensual',
        'TRIMESTRAL' => 'Trimestral',
        'SEMESTRAL'  => 'Semestral',
        'ANUAL'      => 'Anual',
    ];

    /** Meses que agrega cada periodicidad (usado por la renovación, US-0305). */
    public const MESES = [
        'MENSUAL' => 1, 'TRIMESTRAL' => 3, 'SEMESTRAL' => 6, 'ANUAL' => 12,
    ];

    /** Días de aviso previos al vencimiento (US-0307). */
    public const DIAS_AVISO = 15;

    /** Documentación entregada por el cliente (una sola opción). */
    public const DOCUMENTACIONES = ['INE', 'TARJETA_CIRCULACION', 'LICENCIA_CONDUCIR'];

    public const DOCUMENTACION_LABELS = [
        'INE'                => 'INE',
        'TARJETA_CIRCULACION' => 'Tarjeta de circulación',
        'LICENCIA_CONDUCIR'  => 'Licencia de conducir',
    ];

    /** Matriz de transiciones permitidas (US-0305). Vacío = estado final. */
    public const TRANSICIONES = [
        'PENDIENTE'  => ['ACTIVA', 'CANCELADA'],
        'ACTIVA'     => ['VENCIDA', 'SUSPENDIDA', 'CANCELADA', 'FINALIZADA'],
        'VENCIDA'    => ['ACTIVA', 'CANCELADA', 'FINALIZADA'],
        'SUSPENDIDA' => ['ACTIVA', 'CANCELADA', 'FINALIZADA'],
        'CANCELADA'  => [],
        'FINALIZADA' => [],
    ];

    protected $table = 'contratos_pension';

    protected $fillable = [
        'cliente_id', 'vehiculo_id', 'tarifa_id', 'numero_contrato',
        'nombre_empresa', 'contacto_empresa', 'direccion_empresa',
        'fecha_inicio', 'fecha_entrada', 'fecha_fin', 'precio', 'periodicidad',
        'dia_cobro', 'estado', 'deposito', 'documentacion', 'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_entrada' => 'datetime',
        'fecha_fin'    => 'date',
        'precio'       => 'decimal:2',
        'deposito'     => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'contrato_pension_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoAcceso::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionEspacio::class, 'contrato_pension_id');
    }

    /** Asignación vigente (fecha_fin IS NULL). */
    public function asignacionVigiente(): HasOne
    {
        return $this->hasOne(AsignacionEspacio::class, 'contrato_pension_id')
            ->whereNull('fecha_fin');
    }

    /** Folio, nombre del cliente o placas. */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when($termino, function (Builder $q) use ($termino) {
            $t = "%{$termino}%";
            $q->where(fn (Builder $w) => $w
                ->where('numero_contrato', 'like', $t)
                ->orWhereHas('cliente', fn (Builder $c) => $c
                    ->where(fn (Builder $cc) => $cc
                        ->where('nombre', 'like', $t)
                        ->orWhere('apellido_paterno', 'like', $t)
                        ->orWhere('email', 'like', $t)))
                ->orWhereHas('vehiculo', fn (Builder $v) => $v->where('placas', 'like', $t)));
        });
    }

    /** Filtros del listado (US-0306). */
    public function scopeFiltrar(Builder $query, ?string $q, ?string $estado, bool $porVencer = false): Builder
    {
        return $query
            ->buscar($q)
            ->when($estado, fn (Builder $w) => $w->where('estado', $estado))
            ->when($porVencer, fn (Builder $w) => $w->porVencer());
    }

    /** US-0307: activas que vencen entre hoy y hoy + 15 días. */
    public function scopePorVencer(Builder $query, int $dias = self::DIAS_AVISO): Builder
    {
        return $query
            ->where('estado', 'ACTIVA')
            ->whereNotNull('fecha_fin')
            ->whereBetween('fecha_fin', [now()->toDateString(), now()->addDays($dias)->toDateString()]);
    }

    /** Días restantes hasta fecha_fin (negativo = ya venció); null si no tiene fecha. */
    public function diasParaVencer(): ?int
    {
        if (! $this->fecha_fin) {
            return null;
        }

        return (int) now()->startOfDay()
            ->diffInDays($this->fecha_fin->copy()->startOfDay(), false);
    }

    /** Estados a los que se puede transicionar desde el actual (US-0305). */
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

    public function getDocumentacionLabelAttribute(): ?string
    {
        return $this->documentacion
            ? (self::DOCUMENTACION_LABELS[$this->documentacion] ?? $this->documentacion)
            : null;
    }

    /** Al crear un nuevo contrato, si viene en PENDIENTE lo pasamos a ACTIVA. */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if ($model->estado === 'PENDIENTE') {
                $model->estado = 'ACTIVA';
            }
        });
    }
}
