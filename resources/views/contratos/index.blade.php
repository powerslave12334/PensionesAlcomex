@extends('layouts.app')
@section('title', 'Contratos de pensión')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Contratos de pensión</h4>
    @can('contratos.crear')
        <a href="{{ route('contratos.create') }}" class="btn btn-primary">+ Nuevo contrato</a>
    @endcan
</div>

{{-- US-0307: alerta de vencimiento inminente --}}
@can('contratos.ver')
    @if ($totalPorVencer > 0)
        <div class="alert alert-warning d-flex justify-content-between align-items-center py-2" role="alert">
            <span>
                <strong>{{ $totalPorVencer }}</strong>
                {{ Str::plural('contrato', $totalPorVencer) }} por vencer en los próximos
                {{ \App\Models\ContratoPension::DIAS_AVISO }} días.
            </span>
            <a href="{{ route('contratos.index', ['por_vencer' => 1]) }}" class="btn btn-sm btn-warning">Ver lista</a>
        </div>
    @endif
@endcan

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" value="{{ $q }}" class="form-control"
               placeholder="Buscar por folio, cliente o placas">
    </div>
    <div class="col-md-3">
        <select name="estado" class="form-select" aria-label="Estado">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\ContratoPension::ESTADOS as $e)
                <option value="{{ $e }}" @selected($estado === $e)>
                    {{ \App\Models\ContratoPension::ESTADO_LABELS[$e] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="por_vencer" value="1" id="porVencer"
                   @checked($filtroPorVencer)>
            <label class="form-check-label" for="porVencer">Por vencer ({{ \App\Models\ContratoPension::DIAS_AVISO }} días)</label>
        </div>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
        @if ($q || $estado || $filtroPorVencer)
            <a href="{{ route('contratos.index') }}" class="btn btn-outline-light">Limpiar</a>
        @endif
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Folio</th><th>Cliente</th><th>Vehículo</th><th>Cajón</th>
                <th class="text-end">Precio</th><th>Vigencia</th>
                <th class="text-center">Estado</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($contratos as $contrato)
                <tr>
                    <td class="fw-bold">{{ $contrato->numero_contrato }}</td>
                    <td>{{ $contrato->cliente?->nombre_completo ?? '—' }}</td>
                    <td>
                        {{ $contrato->vehiculo?->placas ?? '—' }}
                        <small class="text-muted d-block">
                            {{ $contrato->vehiculo?->marca }} {{ $contrato->vehiculo?->modelo }}
                        </small>
                    </td>
                    <td>{{ $contrato->asignacionVigiente?->espacio?->codigo ?? '—' }}</td>
                    <td class="text-end">${{ number_format((float) $contrato->precio, 2) }}
                        <small class="text-muted d-block">
                            {{ \App\Models\ContratoPension::PERIODICIDAD_LABELS[$contrato->periodicidad] }}
                        </small>
                    </td>
                    <td class="small">
                        {{ $contrato->fecha_inicio->format('d/m/Y') }}
                        —
                        {{ $contrato->fecha_fin?->format('d/m/Y') ?? 'sin fecha' }}
                        @if ($contrato->fecha_fin)
                            @php $dias = $contrato->diasParaVencer(); @endphp
                            @if ($dias !== null && $dias < 0)
                                <span class="badge bg-danger">vencido</span>
                            @elseif ($dias !== null && $dias <= \App\Models\ContratoPension::DIAS_AVISO)
                                <span class="badge bg-warning text-dark">{{ $dias }} días</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $contrato->estado_clase }}">{{ $contrato->estado_label }}</span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('contratos.show', $contrato) }}" class="btn btn-sm btn-outline-secondary">Expediente</a>
                        @can('contratos.editar')
                            <a href="{{ route('contratos.edit', $contrato) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('contratos.eliminar')
                            <form action="{{ route('contratos.destroy', $contrato) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar este contrato?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No hay contratos{{ $q || $estado || $filtroPorVencer ? ' que coincidan con el filtro' : ' registrados' }}.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $contratos->links() }}</div>
@endsection
