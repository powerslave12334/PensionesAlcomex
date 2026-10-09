@extends('layouts.app')
@section('title', 'Zonas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Zonas del predio</h4>
    @can('zonas.crear')
        <a href="{{ route('zonas.create') }}" class="btn btn-primary">+ Nueva zona</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar zona">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Buscar</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr><th>#</th><th>Nombre</th><th>Descripción</th><th class="text-center">Cajones</th>
                <th class="text-center">Estado</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($zonas as $z)
                <tr>
                    <td>{{ $z->id }}</td>
                    <td class="fw-bold">{{ $z->nombre }}</td>
                    <td>{{ $z->descripcion ?: '—' }}</td>
                    <td class="text-center">{{ $z->espacios_count }}</td>
                    <td class="text-center">
                        <span class="badge {{ $z->activo ? 'bg-success' : 'bg-secondary' }}">
                            {{ $z->activo ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        @can('zonas.editar')
                            <a href="{{ route('zonas.edit', $z) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('zonas.eliminar')
                            <form action="{{ route('zonas.destroy', $z) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar esta zona?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No hay zonas registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $zonas->links() }}</div>
@endsection
