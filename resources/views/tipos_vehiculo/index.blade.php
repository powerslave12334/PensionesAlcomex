@extends('layouts.app')
@section('title', 'Tipos de vehículo')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Tipos de vehículo</h4>
    @can('tipos_vehiculo.crear')
        <a href="{{ route('tipos-vehiculo.create') }}" class="btn btn-primary">+ Nuevo tipo</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar tipo">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Buscar</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr><th>#</th><th>Nombre</th><th>Descripción</th><th class="text-end">Tarifa base</th>
                <th class="text-center">Vehículos</th><th class="text-center">Cajones</th>
                <th class="text-center">Estado</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($tipos as $t)
                <tr>
                    <td>{{ $t->id }}</td>
                    <td class="fw-bold">{{ $t->nombre }}</td>
                    <td>{{ $t->descripcion ?: '—' }}</td>
                    <td class="text-end">${{ number_format((float) $t->tarifa_base, 2) }}</td>
                    <td class="text-center">{{ $t->vehiculos_count }}</td>
                    <td class="text-center">{{ $t->espacios_count }}</td>
                    <td class="text-center">
                        <span class="badge {{ $t->activo ? 'bg-success' : 'bg-secondary' }}">
                            {{ $t->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        @can('tipos_vehiculo.editar')
                            <a href="{{ route('tipos-vehiculo.edit', $t) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('tipos_vehiculo.eliminar')
                            <form action="{{ route('tipos-vehiculo.destroy', $t) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar este tipo de vehículo?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay tipos de vehículo registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $tipos->links() }}</div>
@endsection
