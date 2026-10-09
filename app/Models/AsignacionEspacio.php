<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionEspacio extends Model
{
    protected $table = 'asignaciones_espacio';

    protected $fillable = [
        'contrato_pension_id', 'espacio_id', 'fecha_inicio',
        'fecha_fin', 'motivo', 'activo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
        'activo'       => 'boolean',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoPension::class, 'contrato_pension_id');
    }

    public function espacio(): BelongsTo
    {
        return $this->belongsTo(Espacio::class);
    }
}
