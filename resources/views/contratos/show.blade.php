@extends('layouts.app')
@section('title', 'Expediente ' . $contrato->numero_contrato)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        {{ $contrato->numero_contrato }}
        <span class="badge {{ $contrato->estado_clase }} ms-2">{{ $contrato->estado_label }}</span>
        @if ($contrato->fecha_fin)
            @php $dias = $contrato->diasParaVencer(); @endphp
            @if ($dias !== null && $dias < 0)
                <span class="badge bg-danger ms-1">Vencido hace {{ abs($dias) }} días</span>
            @elseif ($dias !== null && $dias <= \App\Models\ContratoPension::DIAS_AVISO)
                <span class="badge bg-warning text-dark ms-1">Vence en {{ $dias }} días</span>
            @endif
        @endif
    </h4>
    <div class="d-flex gap-2">
        @can('pagos.crear')
            @if (! in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true))
                <a href="{{ route('pagos.create', ['contrato_id' => $contrato->id]) }}"
                   class="btn btn-success">Cobrar</a>
            @endif
        @endcan
        @can('contratos.editar')
            <a href="{{ route('contratos.edit', $contrato) }}" class="btn btn-outline-primary">Editar</a>
        @endcan
        <a href="{{ route('contratos.ticket', ['contrato' => $contrato, 'auto' => 1]) }}" target="_blank" class="btn btn-outline-dark">Imprimir ticket</a>
        <a href="{{ route('contratos.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><strong>Contrato</strong></div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">Tarifa</dt>
                    <dd class="col-7">{{ $contrato->tarifa?->nombre ?? '—' }}</dd>
                    <dt class="col-5">Precio congelado</dt>
                    <dd class="col-7 fw-bold">${{ number_format((float) $contrato->precio, 2) }}</dd>
                    <dt class="col-5">Periodicidad</dt>
                    <dd class="col-7">{{ \App\Models\ContratoPension::PERIODICIDAD_LABELS[$contrato->periodicidad] }}</dd>
                    <dt class="col-5">Depósito</dt>
                    <dd class="col-7">${{ number_format((float) $contrato->deposito, 2) }}</dd>
                    <dt class="col-5">Inicio</dt>
                    <dd class="col-7">{{ $contrato->fecha_inicio->format('d/m/Y') }}</dd>
                    <dt class="col-5">Término</dt>
                    <dd class="col-7">{{ $contrato->fecha_fin?->format('d/m/Y') ?? 'sin fecha' }}</dd>
                    <dt class="col-5">Entrada de la unidad</dt>
                    <dd class="col-7">{{ $contrato->fecha_entrada?->format('d/m/Y H:i') ?? '—' }}</dd>
                    <dt class="col-5">Empresa</dt>
                    <dd class="col-7">{{ $contrato->nombre_empresa ?: '—' }}</dd>
                    <dt class="col-5">Contacto empresa</dt>
                    <dd class="col-7">{{ $contrato->contacto_empresa ?: '—' }}</dd>
                    <dt class="col-5">Dirección</dt>
                    <dd class="col-7">{{ $contrato->direccion_empresa ?: '—' }}</dd>
                    <dt class="col-5">Documentación</dt>
                    <dd class="col-7">
                        @if ($contrato->documentacion_label)
                            <span class="badge bg-secondary">{{ $contrato->documentacion_label }}</span>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-5">Observaciones</dt>
                    <dd class="col-7">{{ $contrato->observaciones ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><strong>Cliente</strong></div>
            <div class="card-body small">
                <p class="mb-1 fw-bold">{{ $contrato->cliente?->nombre_completo ?? '—' }}</p>
                <p class="mb-1">Tel: {{ $contrato->cliente?->telefono ?: '—' }}</p>
                <p class="mb-1">Email: {{ $contrato->cliente?->email ?: '—' }}</p>
                <hr class="my-2">
                <p class="mb-1 fw-bold">
                    {{ $contrato->vehiculo?->placas }} — {{ $contrato->vehiculo?->marca }} {{ $contrato->vehiculo?->modelo }}
                </p>
                <p class="mb-0">
                    {{ $contrato->vehiculo?->tipoVehiculo?->nombre }} · {{ $contrato->vehiculo?->anio }} ·
                    {{ $contrato->vehiculo?->color }}
                </p>
            </div>
        </div>
    </div>

    {{-- US-0304: cajón actual + asignación --}}
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><strong>Cajón asignado</strong></div>
            <div class="card-body">
                @php
                    $libresDelTipo = $contrato->vehiculo?->tipoVehiculo?->espacios()
                        ?->where('activo', true)->where('estado', 'DISPONIBLE')->count() ?? 0;
                @endphp

                @if ($contrato->asignacionVigiente?->espacio)
                    <p class="mb-1">
                        <span class="badge bg-dark fs-6">{{ $contrato->asignacionVigiente->espacio->codigo }}</span>
                        {{ $contrato->asignacionVigiente->espacio->zona?->nombre }}
                    </p>
                    <p class="small text-muted mb-2">
                        Desde {{ $contrato->asignacionVigiente->fecha_inicio?->format('d/m/Y') }}
                    </p>
                    @can('contratos.asignar')
                        <form action="{{ route('contratos.espacio.destroy', $contrato) }}" method="POST"
                              onsubmit="return confirm('¿Liberar el cajón de este contrato?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Liberar cajón</button>
                        </form>
                    @endcan
                @else
                    <p class="text-muted small">Sin cajón asignado.</p>
                @endif

                @can('contratos.asignar')
                    @if (! $contrato->asignacionVigiente && ! in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true))
                        <hr class="my-2">
                        @if ($libresDelTipo === 0)
                            <p class="small text-warning mb-0">Sin cajones libres de este tipo de vehículo.</p>
                        @else
                            <form action="{{ route('contratos.espacio', $contrato) }}" method="POST" class="row g-2">
                                @csrf
                                <div class="col-9">
                                    <select name="espacio_id" class="form-select form-select-sm"
                                            aria-label="Cajón a asignar" required>
                                        <option value="">— Seleccione cajón —</option>
                                        @foreach ($contrato->vehiculo?->tipoVehiculo?->espacios()
                                                    ->where('activo', true)
                                                    ->where('estado', 'DISPONIBLE')
                                                    ->orderBy('codigo')->get() ?? [] as $espacio)
                                            <option value="{{ $espacio->id }}">
                                                {{ $espacio->codigo }} · {{ $espacio->zona?->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-3">
                                    <button class="btn btn-sm btn-primary w-100">Asignar</button>
                                </div>
                            </form>
                        @endif
                    @endif
                @endcan
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Historial de cajones</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Cajón</th><th>Zona</th><th>Desde</th><th>Hasta</th><th>Motivo</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($contrato->asignaciones as $asignacion)
                        <tr>
                            <td>{{ $asignacion->espacio?->codigo }}</td>
                            <td>{{ $asignacion->espacio?->zona?->nombre }}</td>
                            <td>{{ $asignacion->fecha_inicio?->format('d/m/Y') }}</td>
                            <td>{{ $asignacion->fecha_fin?->format('d/m/Y') ?: 'vigente' }}</td>
                            <td>{{ $asignacion->motivo ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin asignaciones registradas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Pagos</strong>
                @php
                    $cobrado = $contrato->pagos->where('estado', 'PAGADO')->sum('total');
                @endphp
                <span class="small text-muted">
                    Cobrado: <strong>${{ number_format((float) $cobrado, 2) }}</strong>
                </span>
            </div>
            <div class="card-body text-center text-muted py-4">
                @if ($contrato->pagos->isEmpty())
                    Sin pagos registrados todavía.
                @else
                    <table class="table table-sm mb-0 text-start">
                        <thead class="table-light">
                        <tr>
                            <th>Recibo</th><th>Fecha</th><th>Periodo</th>
                            <th>Método</th><th class="text-end">Total</th>
                            <th class="text-center">Estado</th><th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($contrato->pagos as $pago)
                            <tr>
                                <td class="fw-semibold">{{ $pago->numero_recibo }}</td>
                                <td>{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                                <td class="small">
                                    {{ $pago->periodo_inicio?->format('d/m/Y') }} —
                                    {{ $pago->periodo_fin?->format('d/m/Y') }}
                                </td>
                                <td>{{ $pago->metodoPago?->nombre ?? '—' }}</td>
                                <td class="text-end">${{ number_format((float) $pago->total, 2) }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $pago->estado_clase }}">{{ $pago->estado_label }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('pagos.show', $pago) }}"
                                       class="btn btn-sm btn-outline-secondary">Recibo</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        {{-- US-0305: cambio de estado --}}
        @can('contratos.cambiar_estado')
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Cambiar estado</strong></div>
                <div class="card-body">
                    @if ($transiciones)
                        <form action="{{ route('contratos.estado', $contrato) }}" method="POST" class="row g-2">
                            @csrf
                            <div class="col-8">
                                <select name="estado" class="form-select" aria-label="Nuevo estado" required>
                                    @foreach ($transiciones as $nuevo)
                                        <option value="{{ $nuevo }}">
                                            {{ \App\Models\ContratoPension::ESTADO_LABELS[$nuevo] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-primary w-100">Aplicar</button>
                            </div>
                        </form>
                        <small class="text-muted d-block mt-2">
                            Estados permitidos desde «{{ $contrato->estado_label }}».
                        </small>
                    @else
                        <p class="mb-0 text-muted">Este contrato está cerrado: no admite más cambios de estado.</p>
                    @endif
                </div>
            </div>
        @endcan

        {{-- US-0305: renovación --}}
        @can('contratos.renovar')
            @if (in_array($contrato->estado, ['PENDIENTE', 'ACTIVA', 'VENCIDA'], true))
                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-white"><strong>Renovar</strong></div>
                    <div class="card-body">
                        <form action="{{ route('contratos.renovar', $contrato) }}" method="POST">
                            @csrf
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small mb-1">Nueva periodicidad</label>
                                    <select name="periodicidad" class="form-select form-select-sm" required>
                                        @foreach (\App\Models\ContratoPension::PERIODICIDADES as $p)
                                            <option value="{{ $p }}" @selected($contrato->periodicidad === $p)>
                                                {{ \App\Models\ContratoPension::PERIODICIDAD_LABELS[$p] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="actualizar_precio"
                                               value="1" id="actPrecio">
                                        <label class="form-check-label small" for="actPrecio">
                                            Actualizar a tarifa vigente
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-outline-primary btn-sm w-100">Renovar contrato</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan
    </div>
</div>

@if (session('ticket_nuevo'))
    @push('scripts')
        <script>
            // Recién creado: abre el ticket térmico en una pestaña para imprimir.
            window.open('{{ route('contratos.ticket', ['contrato' => $contrato, 'auto' => 1]) }}', '_blank');
        </script>
    @endpush
@endif
@endsection
