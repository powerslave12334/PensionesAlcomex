@extends('layouts.app')
@section('title', 'Espacios / Cajones')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Cajones</h4>
    @can('espacios.crear')
        <a href="{{ route('espacios.create') }}" class="btn btn-primary">+ Nuevo cajón</a>
    @endcan
</div>

{{-- Resumen de estados (US-0205) --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach (\App\Models\Espacio::ESTADOS as $est)
        <span class="badge {{ \App\Models\Espacio::ESTADO_CLASSES[$est] }} p-2">
            {{ \App\Models\Espacio::ESTADO_LABELS[$est] }}: {{ $resumen[$est] ?? 0 }}
        </span>
    @endforeach
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <select name="zona" class="form-select" aria-label="Filtrar por zona">
            <option value="">Todas las zonas</option>
            @foreach ($zonas as $z)
                <option value="{{ $z->id }}" @selected($zona == $z->id)>{{ $z->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="estado" class="form-select" aria-label="Filtrar por estado">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\Espacio::ESTADOS as $est)
                <option value="{{ $est }}" @selected($estado === $est)>
                    {{ \App\Models\Espacio::ESTADO_LABELS[$est] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
        @if ($zona || $estado)
            <a href="{{ route('espacios.index') }}" class="btn btn-outline-light">Limpiar</a>
        @endif
    </div>
</form>

@if ($espacios->isEmpty())
    <div class="alert alert-light border text-center py-5">
        No hay cajones{{ $zona || $estado ? ' que coincidan con el filtro' : '' }}.
        @can('espacios.crear')
            <a href="{{ route('espacios.create') }}">Da de alta el primero</a>.
        @endcan
    </div>
@endif

@foreach ($espacios as $nombreZona => $grupo)
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>{{ $nombreZona }}</strong>
        <span class="text-muted small">{{ $grupo->count() }} cajones</span>
    </div>
    <div class="card-body d-flex flex-wrap gap-2">
        @foreach ($grupo as $e)
        <div class="border rounded p-2 text-center bg-white" style="width: 195px;">
            <div class="fw-bold">{{ $e->codigo }}</div>
            <small class="text-muted d-block">
                {{ $e->tipoVehiculo?->nombre ?? '—' }}@if ($e->piso) · Piso {{ $e->piso }}@endif
            </small>

            <div class="my-1">
                <span class="badge {{ $e->estado_clase }}">{{ $e->estado_label }}</span>
            </div>

            @if ($e->asignacionVigiente?->contrato?->vehiculo)
                <small class="d-block text-success">
                    {{ $e->asignacionVigiente->contrato->vehiculo->placas }}
                </small>
                <small class="d-block text-muted">
                    {{ $e->asignacionVigiente->contrato->cliente?->nombre_completo }}
                </small>
            @else
                <small class="d-block text-muted">Sin asignación vigente</small>
            @endif

            @can('espacios.cambiar_estado')
            <form action="{{ route('espacios.estado', $e) }}" method="POST" class="mt-1">
                @csrf @method('PATCH')
                <select name="estado" class="form-select form-select-sm"
                        onchange="this.form.submit()"
                        aria-label="Cambiar estado del cajón {{ $e->codigo }}">
                    @foreach (\App\Models\Espacio::ESTADOS as $op)
                        <option value="{{ $op }}" @selected($e->estado === $op)>
                            {{ \App\Models\Espacio::ESTADO_LABELS[$op] }}
                        </option>
                    @endforeach
                </select>
            </form>
            @endcan

            @can('espacios.editar')
                <a href="{{ route('espacios.edit', $e) }}"
                   class="btn btn-sm btn-outline-primary mt-1 w-100">Editar</a>
            @endcan
        </div>
        @endforeach
    </div>
</div>
@endforeach
@endsection
