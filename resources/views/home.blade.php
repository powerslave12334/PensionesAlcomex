@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <h6 class="text-muted">Vehículos dentro</h6>
                <h2 class="mb-0">{{ $adentro ?? '—' }}</h2>
                <small class="text-muted">
                    {{ $adentro === null ? 'Sin permiso accesos.ver' : 'Entradas sin salida' }}
                </small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <h6 class="text-muted">Espacios ocupados</h6>
                <h2 class="mb-0">—</h2>
                <small class="text-muted">Sprint 8</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <h6 class="text-muted">Ingresos del día</h6>
                <h2 class="mb-0">$0.00</h2>
                <small class="text-muted">Sprint 8</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <h6 class="text-muted">Ingresos del mes</h6>
                <h2 class="mb-0">$0.00</h2>
                <small class="text-muted">Sprint 8</small>
            </div></div>
        </div>

        {{-- US-0307: contratos por vencer en los próximos 15 días --}}
        @if ($porVencer->isNotEmpty())
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
                                    <small class="text-muted">
                                        vence {{ $contrato->fecha_fin?->format('d/m/Y') }}
                                    </small>
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
        @endif
    </div>
</div>
@endsection
