<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispositivo extends Model
{
    protected $table = 'dispositivos';

    public const TIPOS = [
        'CAMARA', 'LECTOR_RFID', 'LECTOR_QR', 'LECTOR_PLACAS',
        'BARRERA', 'SENSOR', 'IMPRESORA',
    ];

    public const TIPO_LABELS = [
        'CAMARA'        => 'Cámara',
        'LECTOR_RFID'   => 'Lector RFID',
        'LECTOR_QR'     => 'Lector QR',
        'LECTOR_PLACAS' => 'Lector de placas (LPR)',
        'BARRERA'       => 'Barrera',
        'SENSOR'        => 'Sensor',
        'IMPRESORA'     => 'Impresora',
    ];

    public const ESTADOS = ['ACTIVO', 'INACTIVO', 'FALLA'];

    protected $fillable = [
        'nombre', 'tipo', 'marca', 'modelo', 'numero_serie', 'ip', 'mac',
        'ubicacion', 'estado', 'configuracion', 'ultimo_contacto', 'activo',
    ];

    protected $casts = [
        'configuracion'   => 'array',
        'ultimo_contacto' => 'datetime',
        'activo'          => 'boolean',
    ];

    public function puertas(): HasMany
    {
        return $this->hasMany(Puerta::class);
    }

    public function esBarrera(): bool
    {
        return $this->tipo === 'BARRERA';
    }

    /** Estado mock de la barrera: ABIERTA / CERRADA. */
    public function getBarreraAttribute(): string
    {
        return $this->configuracion['barrera'] ?? 'CERRADA';
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_LABELS[$this->tipo] ?? $this->tipo;
    }
}
