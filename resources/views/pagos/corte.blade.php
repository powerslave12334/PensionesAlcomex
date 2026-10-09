@extends('layouts.app')
@section('title', 'Corte de caja')

@section('content')
<style>
    @media print {
        nav, .navbar, .d-print-none { display: none !important; }
    }
</style>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Corte de caja — {{ $fecha->format('d/m/Y') }}</h4>
    <div class="d-flex gap-2 d-print-none">
        <form method="GET" action="{{ route('pagos.corte') }}" class="d-flex gap-2">
            <input type="date" name="fecha" value="{{ $fecha->toDateString() }}" class="form-control">
            <button class="btn btn-outline-secondary">Ver día</button>
        </form>
        <button onclick="window.print()" class="btn btn-outline-primary">Imprimir</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success">${{ number_format($totales['total'], 2) }}</div>
                <small class="text-muted">Total cobrado</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold">{{ $totales['cantidad'] }}</div>
                <small class="text-muted">{{ Str::plural('cobro', $totales['cantidad']) }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold">${{ number_format($totales['descuento'], 2) }}</div>
                <small class="text-muted">Descuentos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold">${{ number_format($totales['impuesto'], 2) }}</div>
                <small class="text-muted">Impuestos</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Totales por método de pago</strong></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Método</th><th class="text-center">Comprobantes</th>
                <th class="text-end">Subtotal</th><th class="text-end">Descuento</th>
                <th class="text-end">Impuesto</th><th class="text-end">Total</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($porMetodo as $fila)
                <tr>
                    <td>{{ $fila['metodo'] }}</td>
                    <td class="text-center">{{ $fila['cantidad'] }}</td>
                    <td class="text-end">${{ number_format($fila['subtotal'], 2) }}</td>
                    <td class="text-end">${{ number_format($fila['descuento'], 2) }}</td>
                    <td class="text-end">${{ number_format($fila['impuesto'], 2) }}</td>
                    <td class="text-end fw-semibold">${{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Sin cobros registrados este día.</td></tr>
            @endforelse
            </tbody>
            <tfoot>
            <tr class="table-light fw-bold">
                <td>TOTAL</td>
                <td class="text-center">{{ $totales['cantidad'] }}</td>
                <td class="text-end">${{ number_format($totales['subtotal'], 2) }}</td>
                <td class="text-end">${{ number_format($totales['descuento'], 2) }}</td>
                <td class="text-end">${{ number_format($totales['impuesto'], 2) }}</td>
                <td class="text-end">${{ number_format($totales['total'], 2) }}</td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

@if ($excluidos->isNotEmpty())
    <div class="alert alert-secondary mt-3 mb-0">
        <strong>{{ $excluidos->count() }}</strong> movimiento(s) del día
        <strong>no entran al total</strong> (cancelados o reembolsados):
        {{ $excluidos->pluck('numero_recibo')->join(', ') }}.
    </div>
@endif
@endsection
