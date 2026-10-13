@extends('layouts.app')
@section('title', 'Recibo ' . $pago->numero_recibo)

@section('content')
<style>
    @media print {
        nav, .navbar, .d-print-none { display: none !important; }
        .flex-grow-1 { margin: 0 !important; }
        main, .p-4 { padding: 0 !important; }
        .recibo { border: 0 !important; box-shadow: none !important; }
        body { background: #fff !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
    <h4 class="mb-0">Recibo {{ $pago->numero_recibo }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('pagos.index') }}" class="btn btn-outline-secondary">Volver</a>
        <button onclick="window.print()" class="btn btn-outline-primary">Imprimir / Guardar PDF</button>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm recibo">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div>
                        <h5 class="mb-0">{{ config('app.name') }}</h5>
                        <small class="text-muted">Recibo de pensión vehicular</small>
                    </div>
                    <div class="text-end">
                        <div class="fs-5 fw-bold">{{ $pago->numero_recibo }}</div>
                        <span class="badge {{ $pago->estado_clase }}">{{ $pago->estado_label }}</span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-6">
                        <div class="text-muted small">Cliente</div>
                        <div class="fw-semibold">{{ $pago->cliente?->nombre_completo ?? '—' }}</div>
                        <div class="text-muted small">{{ $pago->cliente?->email ?: '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Contrato / vehículo</div>
                        <div class="fw-semibold">{{ $pago->contrato?->numero_contrato ?? '—' }}</div>
                        <div class="text-muted small">
                            {{ $pago->contrato?->vehiculo?->placas ?? '—' }}
                            {{ $pago->contrato?->vehiculo?->marca }}
                            {{ $pago->contrato?->vehiculo?->modelo }}
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4">
                        <div class="text-muted small">Fecha de pago</div>
                        <div>{{ $pago->fecha_pago->format('d/m/Y') }}</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Periodo que cubre</div>
                        <div>
                            {{ $pago->periodo_inicio?->format('d/m/Y') }} — {{ $pago->periodo_fin?->format('d/m/Y') }}
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Registró</div>
                        <div>{{ $pago->usuario?->name ?? '—' }}</div>
                    </div>
                </div>

                <table class="table table-sm">
                    <thead class="table-light">
                    <tr><th>Concepto</th><th class="text-end">Importe</th></tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            Pensión {{ $pago->contrato?->periodicidad
                                ? \App\Models\ContratoPension::PERIODICIDAD_LABELS[$pago->contrato->periodicidad]
                                : 'vehicular' }}
                            @if ($pago->meses_cobrados)
                                <div class="text-muted small">
                                    {{ $pago->meses_cobrados }} {{ $pago->meses_cobrados === 1 ? 'mes' : 'meses' }} ·
                                    ${{ number_format((float) $pago->subtotal / $pago->meses_cobrados, 2) }} por mes
                                </div>
                            @endif
                        </td>
                        <td class="text-end">${{ number_format((float) $pago->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Descuento</td>
                        <td class="text-end">−${{ number_format((float) $pago->descuento, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Impuesto</td>
                        <td class="text-end">+${{ number_format((float) $pago->impuesto, 2) }}</td>
                    </tr>
                    </tbody>
                    <tfoot>
                    <tr class="table-light fw-bold">
                        <td>Total</td>
                        <td class="text-end">${{ number_format((float) $pago->total, 2) }}</td>
                    </tr>
                    </tfoot>
                </table>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="text-muted small">Método de pago</div>
                        <div>{{ $pago->metodoPago?->nombre ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Referencia</div>
                        <div>{{ $pago->referencia ?: '—' }}</div>
                    </div>
                </div>

                @if ($pago->observaciones)
                    <div class="mt-3 border-top pt-2">
                        <div class="text-muted small">Observaciones</div>
                        <div class="small" style="white-space: pre-line">{{ $pago->observaciones }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4 d-print-none">
        {{-- US-0406: cancelar / reembolsar con motivo --}}
        @can('pagos.cancelar')
            @php $acciones = $pago->transicionesPermitidas(); @endphp
            @if ($acciones)
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><strong>Corregir este cobro</strong></div>
                    <div class="card-body">
                        <form action="{{ route('pagos.estado', $pago) }}" method="POST" class="row g-2">
                            @csrf
                            <div class="col-12">
                                <label class="form-label" for="accion">Movimiento</label>
                                <select name="accion" id="accion" class="form-select" required>
                                    @foreach ($acciones as $a)
                                        <option value="{{ $a }}">{{ \App\Models\Pago::ESTADO_LABELS[$a] }}</option>
                                    @endforeach
                                </select>
                                @error('accion') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="motivo">Motivo <span class="text-danger">*</span></label>
                                <textarea name="motivo" id="motivo" rows="3" class="form-control" required
                                          maxlength="300">{{ old('motivo') }}</textarea>
                                @error('motivo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                <small class="text-muted">Queda registrado en el recibo; nada se borra.</small>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-outline-danger w-100"
                                        onclick="return confirm('¿Confirmar el movimiento de este recibo?')">
                                    Aplicar movimiento
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @else
                <div class="alert alert-secondary mb-0">
                    Recibo en estado final ({{ $pago->estado_label }}): no admite más movimientos.
                </div>
            @endif
        @endcan

        @if ($pago->contrato)
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Contrato</strong></div>
                <div class="card-body">
                    <a href="{{ route('contratos.show', $pago->contrato) }}" class="btn btn-outline-secondary w-100">
                        Ver expediente {{ $pago->contrato->numero_contrato }}
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
