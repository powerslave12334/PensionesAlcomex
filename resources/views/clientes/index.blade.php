@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Clientes / Propietarios</h4>
    @can('clientes.crear')
        <a href="{{ route('clientes.create') }}" class="btn btn-primary">+ Nuevo cliente</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control"
               placeholder="Buscar por nombre, correo, teléfono o RFC">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Buscar</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th><th>Nombre completo</th><th>Teléfono</th><th>Correo</th>
                <th>RFC</th><th class="text-center">Estado</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($clientes as $cliente)
                <tr>
                    <td>{{ $cliente->id }}</td>
                    <td>{{ $cliente->nombre_completo }}</td>
                    <td>{{ $cliente->telefono ?: '—' }}</td>
                    <td>{{ $cliente->email ?: '—' }}</td>
                    <td>{{ $cliente->rfc ?: '—' }}</td>
                    <td class="text-center">
                        <span class="badge {{ $cliente->activo ? 'bg-success' : 'bg-secondary' }}">
                            {{ $cliente->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        @can('clientes.editar')
                            <a href="{{ route('clientes.edit', $cliente) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('clientes.eliminar')
                            <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar este cliente?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">
                    No hay clientes registrados{{ request('q') ? ' para «'.request('q').'»' : '' }}.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $clientes->links() }}</div>
@endsection
