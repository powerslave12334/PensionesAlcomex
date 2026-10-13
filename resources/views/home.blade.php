@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">

    {{-- ─── Tarjetas de estadísticas ──────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total contratos</h6>
                    <h2 class="mb-0">{{ number_format($stats['total_contratos']) }}</h2>
                    <small class="text-muted">Registrados</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card shadow-sm border-0 h-100 border-start border-success border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Activos</h6>
                    <h2 class="mb-0 text-success">{{ $stats['activos'] }}</h2>
                    <small class="text-muted">En vigor</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card shadow-sm border-0 h-100 border-start border-warning border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Pendientes</h6>
                    <h2 class="mb-0 text-warning">{{ $stats['pendientes'] }}</h2>
                    <small class="text-muted">Sin cobrar</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card shadow-sm border-0 h-100 border-start border-primary border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total cobros</h6>
                    <h2 class="mb-0 text-primary">{{ number_format($stats['total_cobros']) }}</h2>
                    <small class="text-muted">Recibos</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card shadow-sm border-0 h-100 border-start border-info border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Recaudado</h6>
                    <h2 class="mb-0 text-info">${{ number_format((float) $stats['recaudado'], 2) }}</h2>
                    <small class="text-muted">Total percibido</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card shadow-sm border-0 h-100 border-start border-secondary border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Cajones ocupados</h6>
                    <h2 class="mb-0">{{ $stats['cajones_ocupados'] }}</h2>
                    <small class="text-muted">Del parqueo</small>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Gráficos ──────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Cobros por mes (últimos 6)</h5>
                </div>
                <div class="card-body">
                    <canvas id="chartCobrosMes" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Contratos por estado</h5>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartEstados" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Listados ──────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">

        {{-- Cobros recientes --}}
        <div class="col-12 col-xl-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Cobros recientes</h5>
                    @can('pagos.ver')
                        <a href="{{ route('pagos.index') }}" class="btn btn-sm btn-outline-primary">Ver todos</a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Contrato</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th class="text-end">Total</th>
                                <th>Método</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recientes as $pago)
                            <tr>
                                <td><a href="{{ route('pagos.show', $pago['id']) }}" class="text-decoration-none">{{ $pago['folio'] }}</a></td>
                                <td>{{ $pago['contrato'] }}</td>
                                <td>{{ $pago['cliente'] }}</td>
                                <td>{{ $pago['fecha'] }}</td>
                                <td class="text-end">${{ $pago['total'] }}</td>
                                <td><span class="badge bg-secondary">{{ $pago['metodo'] }}</span></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No hay cobros registrados</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Contratos críticos --}}
        <div class="col-12 col-xl-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Contratos críticos</h5>
                    @can('contratos.ver')
                        <a href="{{ route('contratos.index') }}" class="btn btn-sm btn-outline-primary">Ver todos</a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Cliente</th>
                                <th>Placas</th>
                                <th>Estado</th>
                                <th class="text-center">Cobros</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($criticos as $c)
                            @php
                                $badge = match($c['estado']) {
                                    'PENDIENTE'  => 'bg-warning text-dark',
                                    'VENCIDA'    => 'bg-danger',
                                    'SUSPENDIDA' => 'bg-secondary',
                                    default      => 'bg-info text-dark',
                                };
                            @endphp
                            <tr>
                                <td><a href="{{ route('contratos.show', $c['id']) }}" class="text-decoration-none">{{ $c['folio'] }}</a></td>
                                <td>{{ $c['cliente'] }}</td>
                                <td>{{ $c['vehiculo'] }}</td>
                                <td><span class="badge {{ $badge }}">{{ $c['estado'] }}</span></td>
                                <td class="text-center">{{ $c['cobros_pagados'] }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay contratos críticos</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Contratos por vencer (US-0307) ───────────────────────── --}}
    @if ($porVencer->isNotEmpty())
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
                    <strong>Contratos por vencer ({{ $porVencer->count() }})</strong>
                    @can('contratos.ver')
                        <a href="{{ route('contratos.index', ['por_vencer' => 1]) }}" class="btn btn-sm btn-outline-dark">
                            Ver todos
                        </a>
                    @endcan
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($porVencer->take(5) as $contrato)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <strong>{{ $contrato->numero_contrato }}</strong>
                            — {{ $contrato->cliente?->nombre_completo }}
                            <small class="text-muted">vence {{ $contrato->fecha_fin?->format('d/m/Y') }}</small>
                        </span>
                        <span class="badge bg-warning text-dark">
                            {{ $contrato->diasParaVencer() }} días
                        </span>
                    </li>
                    @endforeach
                </ul>
                @if ($porVencer->count() > 5)
                    <div class="card-footer small text-muted">
                        …y {{ $porVencer->count() - 5 }} contrato(s) más.
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    // Datos embebidos desde PHP
    const cobrosMes  = @json($cobrosMes);
    const estados    = @json($estados);

    // ── Gráfico 1: Cobros por mes (barras) ──────────────────────────
    const ctxMes = document.getElementById('chartCobrosMes');
    if (ctxMes) {
        new Chart(ctxMes, {
            type: 'bar',
            data: {
                labels: cobrosMes.map(r => r.mes),
                datasets: [{
                    label: 'Cobros',
                    data: cobrosMes.map(r => r.total),
                    backgroundColor: 'rgba(13, 110, 253, 0.55)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    // ── Gráfico 2: Contratos por estado (dona) ──────────────────────
    const ctxEstados = document.getElementById('chartEstados');
    if (ctxEstados) {
        const labels = Object.keys(estados);
        const values = Object.values(estados);
        const colores = {
            ACTIVA:     'rgba(25, 135, 84, 0.7)',
            PENDIENTE:  'rgba(255, 193, 7, 0.7)',
            VENCIDA:    'rgba(220, 53, 69, 0.7)',
            CANCELADA:  'rgba(33, 37, 41, 0.7)',
            SUSPENDIDA: 'rgba(108, 117, 125, 0.7)',
            FINALIZADA: 'rgba(13, 202, 240, 0.7)',
        };

        new Chart(ctxEstados, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map(l => colores[l] || 'rgba(200,200,200,0.5)'),
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 14 } }
                }
            }
        });
    }
})();
</script>
@endpush
@endsection