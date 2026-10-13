@extends('layouts.app')
@section('title', 'Vehículos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Vehículos</h4>
    @can('vehiculos.crear')
        <a href="{{ route('vehiculos.create') }}" class="btn btn-primary">+ Nuevo vehículo</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control"
               placeholder="Buscar por placa, marca, modelo o VIN">
    </div>
    <div class="col-md-3">
        <select name="tipo" class="form-select">
            <option value="">Todos los tipos</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected(request('tipo') == $t->id)>{{ $t->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Foto</th><th>Placas</th><th>Vehículo</th><th>Tipo</th>
                <th>VIN</th><th class="text-center">Estado</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($vehiculos as $v)
                <tr>
                    <td>
                        @if ($v->foto_url)
                            <img src="{{ asset('storage/'.$v->foto_url) }}" alt="Foto"
                                 class="rounded" style="width:60px;height:45px;object-fit:cover;">
                        @else
                            <span class="text-muted small">Sin foto</span>
                        @endif
                    </td>
                    <td class="fw-bold">{{ $v->placas }}</td>
                    <td>{{ trim(($v->marca ?? '').' '.($v->modelo ?? '')) ?: '—' }} @if($v->anio)<small class="text-muted">({{ $v->anio }})</small>@endif</td>
                    <td>{{ $v->tipoVehiculo?->nombre ?? '—' }}</td>
                    <td><small>{{ $v->vin ?: '—' }}</small></td>
                    <td class="text-center">
                        <span class="badge {{ $v->activo ? 'bg-success' : 'bg-secondary' }}">
                            {{ $v->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        @can('vehiculos.editar')
                            <a href="{{ route('vehiculos.edit', $v) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('vehiculos.eliminar')
                            <form action="{{ route('vehiculos.destroy', $v) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Desactivar este vehículo?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Desactivar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay vehículos registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $vehiculos->links() }}</div>
@endsection
