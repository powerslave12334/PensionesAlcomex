@extends('layouts.app')
@section('title', 'Accesos (entradas y salidas)')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Accesos <span class="fs-6 text-muted">(entradas y salidas)</span></h4>
    <div class="d-flex gap-2">
        @can('accesos.configurar')
            <a href="{{ route('accesos.dispositivos') }}" class="btn btn-outline-secondary">Dispositivos</a>
        @endcan
        @can('accesos.ver')
            <a href="{{ route('accesos.qr') }}" class="btn btn-outline-secondary">QR</a>
            <a href="{{ route('accesos.rfid') }}" class="btn btn-outline-secondary">RFID</a>
            <a href="{{ route('accesos.lpr') }}" class="btn btn-outline-secondary">LPR</a>
        @endcan
        @can('accesos.registrar')
            <a href="{{ route('accesos.create') }}" class="btn btn-primary">+ Nueva entrada</a>
        @endcan
    </div>
</div>

{{-- US-0507: resumen con los mismos filtros --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card shadow-sm text-center"><div class="card-body py-3">
            <div class="fs-4 fw-bold text-info">{{ $resumen['adentro'] }}</div>
            <small class="text-muted">Vehículos adentro ahora</small>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center"><div class="card-body py-3">
            <div class="fs-4 fw-bold">{{ $resumen['entradas'] }}</div>
            <small class="text-muted">Entradas de hoy (en el filtro)</small>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center"><div class="card-body py-3">
            <div class="fs-4 fw-bold">{{ $resumen['salidas'] }}</div>
            <small class="text-muted">Salidas de hoy (en el filtro)</small>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center"><div class="card-body py-3">
            <div class="fs-4 fw-bold text-warning">${{ number_format((float) $resumen['porCobrar'], 2) }}</div>
            <small class="text-muted">Cobros de visita pendientes</small>
        </div></div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" value="{{ $filtros['q'] }}" class="form-control"
               placeholder="Buscar placas, vehículo o cliente">
    </div>
    <div class="col-md-2">
        <select name="tipo" class="form-select" aria-label="Tipo">
            <option value="">Entradas y salidas</option>
            @foreach (\App\Models\MovimientoAcceso::TIPOS as $t)
                <option value="{{ $t }}" @selected($filtros['tipo'] === $t)>
                    {{ \App\Models\MovimientoAcceso::TIPO_LABELS[$t] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="metodo" class="form-select" aria-label="Método de acceso">
            <option value="">Todos los métodos</option>
            @foreach (\App\Models\MovimientoAcceso::METODOS as $m)
                <option value="{{ $m }}" @selected($filtros['metodo'] === $m)>
                    {{ \App\Models\MovimientoAcceso::METODO_LABELS[$m] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="estado_cobro" class="form-select" aria-label="Estado del cobro">
            <option value="">Todo cobro</option>
            @foreach (\App\Models\MovimientoAcceso::ESTADOS_COBRO as $e)
                <option value="{{ $e }}" @selected($filtros['estado_cobro'] === $e)>
                    {{ \App\Models\MovimientoAcceso::ESTADO_COBRO_LABELS[$e] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-1">
        <input type="date" name="desde" value="{{ $filtros['desde'] }}" class="form-control" aria-label="Desde">
    </div>
    <div class="col-md-1">
        <input type="date" name="hasta" value="{{ $filtros['hasta'] }}" class="form-control" aria-label="Hasta">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
        @if ($tieneFiltro)
            <a href="{{ route('accesos.index') }}" class="btn btn-outline-light">Limpiar</a>
        @endif
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Fecha y hora</th><th>Tipo</th><th>Vehículo</th><th>Cliente</th>
                <th>Cajón</th><th>Método</th><th>Puerta</th><th>Permanencia</th>
                <th class="text-end">Cobro</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($movimientos as $m)
                <tr class="{{ $m->abierta ? 'table-warning' : '' }}">
                    <td class="text-nowrap">{{ $m->fecha_hora->format('d/m/Y H:i') }}</td>
                    <td>
                        <span class="badge {{ $m->tipo === 'ENTRADA' ? 'bg-primary' : 'bg-dark' }}">
                            {{ $m->tipo_label }}
                        </span>
                    </td>
                    <td>
                        <strong>{{ $m->vehiculo?->placas ?? $m->placas_detectadas ?? '—' }}</strong><br>
                        <small class="text-muted">
                            {{ trim(($m->vehiculo?->marca . ' ' . $m->vehiculo?->modelo) ?: '—') }}
                        </small>
                    </td>
                    <td>{{ $m->cliente?->nombre_completo ?? '—' }}</td>
                    <td>{{ $m->espacio?->codigo ?? '—' }}</td>
                    <td class="small">{{ $m->metodo_label }}</td>
                    <td class="small">{{ $m->puerta?->nombre ?? '—' }}</td>
                    <td class="text-nowrap">
                        @if ($m->tipo === 'ENTRADA')
                            @if ($m->salida)
                                {{ $m->salida->permanencia }}
                            @elseif ($m->abierta)
                                <span class="badge bg-info text-dark">Adentro</span>
                            @else
                                —
                            @endif
                        @else
                            {{ $m->permanencia ?? '—' }}
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if ($m->estado_cobro === 'NO_APLICA' || $m->tipo === 'ENTRADA')
                            —
                        @else
                            <span class="badge {{ $m->estado_cobro_clase }}">
                                {{ $m->estado_cobro_label }}
                            </span>
                            ${{ number_format((float) $m->cobro, 2) }}
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if ($m->abierta && \Illuminate\Support\Facades\Gate::allows('accesos.registrar'))
                            <a href="{{ route('accesos.salida', $m) }}" class="btn btn-sm btn-warning">
                                Registrar salida
                            </a>
                        @endif
                        @can('accesos.ver')
                            <a href="{{ route('accesos.show', $m) }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        No hay movimientos{{ $tieneFiltro ? ' que coincidan con el filtro' : ' registrados' }}.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $movimientos->links() }}</div>
@endsection
