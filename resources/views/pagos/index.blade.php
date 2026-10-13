@extends('layouts.app')
@section('title', 'Pagos y recibos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Pagos y recibos</h4>
    <div class="d-flex gap-2">
        @can('pagos.ver')
            <a href="{{ route('pagos.tickets') }}" class="btn btn-outline-primary">Buscar ticket</a>
        @endcan
        @can('pagos.corte')
            <a href="{{ route('pagos.corte') }}" class="btn btn-outline-secondary">Corte de caja</a>
        @endcan
    </div>
</div>

{{-- US-0404: resumen calculado con los mismos filtros --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success">${{ number_format((float) $resumen->total, 2) }}</div>
                <small class="text-muted">Cobrado con este filtro</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold">{{ $resumen->cantidad }}</div>
                <small class="text-muted">{{ Str::plural('recibo', $resumen->cantidad) }}</small>
            </div>
        </div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" value="{{ $filtros['q'] }}" class="form-control"
               placeholder="Buscar recibo, folio o cliente">
    </div>
    <div class="col-md-2">
        <select name="estado" class="form-select" aria-label="Estado">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\Pago::ESTADOS as $e)
                <option value="{{ $e }}" @selected($filtros['estado'] === $e)>
                    {{ \App\Models\Pago::ESTADO_LABELS[$e] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="metodo" class="form-select" aria-label="Método de pago">
            <option value="">Todos los métodos</option>
            @foreach ($metodos as $m)
                <option value="{{ $m->id }}" @selected($filtros['metodo'] === $m->id)>{{ $m->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="contrato" class="form-select" aria-label="Contrato">
            <option value="">Todos los contratos</option>
            @foreach ($contratos as $c)
                <option value="{{ $c->id }}" @selected($filtros['contrato'] === $c->id)>{{ $c->numero_contrato }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="cliente" class="form-select" aria-label="Cliente">
            <option value="">Todos los clientes</option>
            @foreach ($clientes as $cl)
                <option value="{{ $cl->id }}" @selected($filtros['cliente'] === $cl->id)>
                    {{ $cl->nombre }} {{ $cl->apellido_paterno }}
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
            <a href="{{ route('pagos.index') }}" class="btn btn-outline-light">Limpiar</a>
        @endif
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Recibo</th><th>Fecha</th><th>Contrato</th><th>Cliente</th>
                <th>Método</th><th>Referencia</th><th class="text-end">Total</th>
                <th class="text-center">Estado</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($pagos as $pago)
                <tr>
                    <td class="fw-bold">{{ $pago->numero_recibo }}</td>
                    <td>{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                    <td>{{ $pago->contrato?->numero_contrato ?? '—' }}</td>
                    <td>{{ $pago->cliente?->nombre_completo ?? '—' }}</td>
                    <td>{{ $pago->metodoPago?->nombre ?? '—' }}</td>
                    <td class="small">{{ $pago->referencia ?: '—' }}</td>
                    <td class="text-end">${{ number_format((float) $pago->total, 2) }}</td>
                    <td class="text-center">
                        <span class="badge {{ $pago->estado_clase }}">{{ $pago->estado_label }}</span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('pagos.show', $pago) }}" class="btn btn-sm btn-outline-secondary">Recibo</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        No hay pagos{{ $tieneFiltro ? ' que coincidan con el filtro' : ' registrados' }}.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $pagos->links() }}</div>
@endsection
