@extends('layouts.app')
@section('title', 'Tarifas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Tarifas</h4>
    @can('tarifas.crear')
        <a href="{{ route('tarifas.create') }}" class="btn btn-primary">+ Nueva tarifa</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por nombre">
    </div>
    <div class="col-md-3">
        <select name="tipo" class="form-select" aria-label="Tipo de vehículo">
            <option value="">Todos los tipos</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected($tipo == $t->id)>{{ $t->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="periodo" class="form-select" aria-label="Periodo">
            <option value="">Todos los periodos</option>
            @foreach (\App\Models\Tarifa::TIPOS as $p)
                <option value="{{ $p }}" @selected($periodo === $p)>{{ \App\Models\Tarifa::TIPO_LABELS[$p] }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="vigentes" value="1" id="vigentes"
                   @checked($vigentes)>
            <label class="form-check-label" for="vigentes">Solo vigentes</label>
        </div>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
        @if ($q || $tipo || $periodo || $vigentes)
            <a href="{{ route('tarifas.index') }}" class="btn btn-outline-light">Limpiar</a>
        @endif
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Nombre</th><th>Tipo vehículo</th><th>Periodo</th>
                <th class="text-end">Precio</th><th>Horario</th>
                <th>Vigencia</th><th class="text-center">Estado</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($tarifas as $tarifa)
                <tr>
                    <td class="fw-bold">{{ $tarifa->nombre }}</td>
                    <td>{{ $tarifa->tipoVehiculo?->nombre ?? '—' }}</td>
                    <td><span class="badge bg-light text-dark border">{{ \App\Models\Tarifa::TIPO_LABELS[$tarifa->tipo_tarifa] }}</span></td>
                    <td class="text-end">${{ number_format((float) $tarifa->precio, 2) }}
                        <small class="text-muted d-block">{{ $tarifa->periodo ?: '' }}</small></td>
                    <td>{{ $tarifa->hora_inicio ? $tarifa->hora_inicio . ' - ' . $tarifa->hora_fin : '—' }}</td>
                    <td class="small">
                        {{ $tarifa->vigencia_desde?->format('d/m/Y') ?? 'abierta' }}
                        —
                        {{ $tarifa->vigencia_hasta?->format('d/m/Y') ?? 'abierta' }}
                    </td>
                    <td class="text-center">
                        @if ($tarifa->esVigente())
                            <span class="badge bg-success">Vigente</span>
                        @elseif (! $tarifa->activo)
                            <span class="badge bg-secondary">Inactiva</span>
                        @else
                            <span class="badge bg-warning text-dark">No vigente</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @can('tarifas.editar')
                            <a href="{{ route('tarifas.edit', $tarifa) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('tarifas.eliminar')
                            <form action="{{ route('tarifas.destroy', $tarifa) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar esta tarifa?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay tarifas registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $tarifas->links() }}</div>
@endsection
