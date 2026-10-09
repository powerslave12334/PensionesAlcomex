@extends('layouts.app')
@section('title', 'Detalle del acceso')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        Movimiento #{{ $acceso->id }}
        <span class="badge {{ $acceso->tipo === 'ENTRADA' ? 'bg-primary' : 'bg-dark' }}">{{ $acceso->tipo_label }}</span>
        @if ($acceso->abierta)
            <span class="badge bg-info text-dark">Sin salida</span>
        @endif
    </h4>
    <div class="d-flex gap-2">
        @if ($acceso->abierta && \Illuminate\Support\Facades\Gate::allows('accesos.registrar'))
            <a href="{{ route('accesos.salida', $acceso) }}" class="btn btn-warning">Registrar salida</a>
        @endif
        <a href="{{ route('accesos.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Movimiento</strong></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Fecha y hora</span>
                    <strong>{{ $acceso->fecha_hora->format('d/m/Y H:i') }}</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Tipo</span><span>{{ $acceso->tipo_label }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Método de acceso</span><span>{{ $acceso->metodo_label }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Puerta</span><span>{{ $acceso->puerta?->nombre ?? '—' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Cajón</span><span>{{ $acceso->espacio?->codigo ?? '—' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Registró</span><span>{{ $acceso->usuario?->name ?? '—' }}</span>
                </li>
                <li class="list-group-item">
                    <span class="text-muted d-block">Observaciones</span>
                    {{ $acceso->observaciones ?: '—' }}
                </li>
            </ul>
        </div>

        @if ($acceso->foto_entrada || $acceso->foto_salida)
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-light"><strong>Evidencia</strong></div>
                <div class="card-body">
                    @if ($acceso->foto_entrada)
                        <p class="mb-1 small text-muted">Entrada:</p>
                        <img src="{{ $acceso->foto_entrada }}" class="img-fluid rounded mb-3" alt="Evidencia de entrada">
                    @endif
                    @if ($acceso->foto_salida)
                        <p class="mb-1 small text-muted">Salida:</p>
                        <img src="{{ $acceso->foto_salida }}" class="img-fluid rounded" alt="Evidencia de salida">
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Vehículo y cliente</strong></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Placas</span><strong>{{ $acceso->vehiculo?->placas ?? $acceso->placas_detectadas ?? '—' }}</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Vehículo</span>
                    <span>{{ trim(($acceso->vehiculo?->marca . ' ' . $acceso->vehiculo?->modelo . ' ' . $acceso->vehiculo?->anio) ?: '—') }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Cliente</span><span>{{ $acceso->cliente?->nombre_completo ?? '—' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Contrato</span>
                    <span>{{ $acceso->contrato?->numero_contrato ?? 'Visitante (sin pensión)' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Permanencia</span>
                    <strong>{{ $acceso->permanencia ?? $acceso->salida?->permanencia ?? 'En curso…' }}</strong>
                </li>
                @if ($acceso->tipo === 'ENTRADA' && $acceso->salida && $acceso->salida->estado_cobro !== 'NO_APLICA')
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Cobro en la salida</span>
                        <strong>${{ number_format((float) $acceso->salida->cobro, 2) }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Estado del cobro</span>
                        <span class="badge {{ $acceso->salida->estado_cobro_clase }}">{{ $acceso->salida->estado_cobro_label }}</span>
                    </li>
                @endif
                @if ($acceso->tipo === 'SALIDA')
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Tarifa aplicada</span>
                        <span>{{ $acceso->tarifa?->nombre ?? '—' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Cobro</span>
                        <strong>${{ number_format((float) $acceso->cobro, 2) }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Estado del cobro</span>
                        <span class="badge {{ $acceso->estado_cobro_clase }}">{{ $acceso->estado_cobro_label }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Método de cobro</span>
                        <span>{{ $acceso->metodoPago?->nombre ?? '—' }}</span>
                    </li>
                @endif
            </ul>
        </div>

        <div class="d-flex gap-2 mt-3">
            @if ($acceso->entrada)
                <a href="{{ route('accesos.show', $acceso->entrada) }}" class="btn btn-outline-primary btn-sm">
                    Ver entrada #{{ $acceso->entrada->id }}
                </a>
            @endif
            @if ($acceso->salida)
                <a href="{{ route('accesos.show', $acceso->salida) }}" class="btn btn-outline-dark btn-sm">
                    Ver salida #{{ $acceso->salida->id }}
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
