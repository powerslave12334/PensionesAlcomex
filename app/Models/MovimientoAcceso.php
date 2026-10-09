<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MovimientoAcceso extends Model
{
    protected $table = 'movimientos_acceso';

    public const TIPOS = ['ENTRADA', 'SALIDA'];

    public const METODOS = ['MANUAL', 'QR', 'RFID', 'LPR'];

    public const ESTADOS_COBRO = ['NO_APLICA', 'PENDIENTE', 'COBRADO'];

    public const TIPO_LABELS = ['ENTRADA' => 'Entrada', 'SALIDA' => 'Salida'];

    public const METODO_LABELS = [
        'MANUAL' => 'Manual',
        'QR'     => 'Código QR',
        'RFID'   => 'Tarjeta RFID',
        'LPR'    => 'Lectura de placas',
    ];

    public const ESTADO_COBRO_LABELS = [
        'NO_APLICA' => 'Sin cobro',
        'PENDIENTE' => 'Por cobrar',
        'COBRADO'   => 'Cobrado',
    ];

    public const ESTADO_COBRO_CLASSES = [
        'NO_APLICA' => 'bg-secondary',
        'PENDIENTE' => 'bg-warning text-dark',
        'COBRADO'   => 'bg-success',
    ];

    protected $fillable = [
        'vehiculo_id', 'cliente_id', 'contrato_pension_id', 'espacio_id',
        'tipo', 'fecha_hora', 'placas_detectadas', 'usuario_id', 'puerta_id',
        'metodo_acceso', 'foto_entrada', 'foto_salida', 'observaciones',
        // Sprint 5 (migración aditiva)
        'entrada_id', 'permanencia_minutos', 'tarifa_id', 'cobro',
        'estado_cobro', 'metodo_pago_id',
    ];

    protected $casts = [
        'fecha_hora'          => 'datetime',
        'permanencia_minutos' => 'integer',
        'cobro'               => 'decimal:2',
    ];

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoPension::class, 'contrato_pension_id');
    }

    public function espacio(): BelongsTo
    {
        return $this->belongsTo(Espacio::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function puerta(): BelongsTo
    {
        return $this->belongsTo(Puerta::class);
    }

    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }

    /** En la SALIDA: la ENTRADA que cierra. */
    public function entrada(): BelongsTo
    {
        return $this->belongsTo(self::class, 'entrada_id');
    }

    /** En la ENTRADA: la SALIDA que la cerró (null = sigue adentro). */
    public function salida(): HasOne
    {
        return $this->hasOne(self::class, 'entrada_id');
    }

    /** Entradas sin salida: vehículos que están adentro ahorita. */
    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->where('tipo', 'ENTRADA')->whereDoesntHave('salida');
    }

    public function scopeFiltrar(
        Builder $query,
        ?string $q,
        ?string $tipo,
        ?string $metodo,
        ?string $desde,
        ?string $hasta,
        ?string $estadoCobro
    ): Builder {
        return $query
            ->when($q, function (Builder $b) use ($q) {
                $t = "%{$q}%";
                $b->where(fn (Builder $w) => $w
                    ->where('placas_detectadas', 'like', $t)
                    ->orWhereHas('vehiculo', fn (Builder $v) => $v->buscar($q))
                    ->orWhereHas('cliente', fn (Builder $c) => $c->buscar($q)));
            })
            ->when($tipo, fn (Builder $b, string $v) => $b->where('tipo', $v))
            ->when($metodo, fn (Builder $b, string $v) => $b->where('metodo_acceso', $v))
            ->when($estadoCobro, fn (Builder $b, string $v) => $b->where('estado_cobro', $v))
            ->when($desde, fn (Builder $b) => $b->whereDate('fecha_hora', '>=', $desde))
            ->when($hasta, fn (Builder $b) => $b->whereDate('fecha_hora', '<=', $hasta));
    }

    /** true si la entrada todavía no tiene salida registrada. */
    public function getAbiertaAttribute(): bool
    {
        return $this->tipo === 'ENTRADA' && $this->salida === null;
    }

    /** "2 h 15 min" — null si el movimiento no está cerrado. */
    public function getPermanenciaAttribute(): ?string
    {
        if ($this->permanencia_minutos === null) {
            return null;
        }

        $min = (int) $this->permanencia_minutos;
        $h = intdiv($min, 60);
        $m = $min % 60;

        return $h > 0 ? "{$h} h {$m} min" : "{$m} min";
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_LABELS[$this->tipo] ?? $this->tipo;
    }

    public function getMetodoLabelAttribute(): string
    {
        return self::METODO_LABELS[$this->metodo_acceso] ?? $this->metodo_acceso;
    }

    public function getEstadoCobroLabelAttribute(): string
    {
        return self::ESTADO_COBRO_LABELS[$this->estado_cobro] ?? $this->estado_cobro;
    }

    public function getEstadoCobroClaseAttribute(): string
    {
        return self::ESTADO_COBRO_CLASSES[$this->estado_cobro] ?? 'bg-secondary';
    }
}
