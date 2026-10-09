@extends('layouts.app')
@section('title', 'Dispositivos y barreras')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Dispositivos y barreras <span class="fs-6 text-muted">(US-0506)</span></h4>
    <a href="{{ route('accesos.index') }}" class="btn btn-outline-secondary">Volver</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Hardware instalado</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Dispositivo</th><th>Tipo</th><th>Ubicación</th><th>Estado</th><th>Último contacto</th><th></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($dispositivos as $d)
                        <tr>
                            <td>
                                <strong>{{ $d->nombre }}</strong><br>
                                <small class="text-muted">{{ $d->marca }} {{ $d->modelo }}</small>
                            </td>
                            <td>{{ $d->tipo_label }}</td>
                            <td class="small">{{ $d->ubicacion ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $d->estado === 'ACTIVO' ? 'bg-success' : ($d->estado === 'FALLA' ? 'bg-danger' : 'bg-secondary') }}">
                                    {{ $d->estado }}
                                </span>
                            </td>
                            <td class="small">{{ $d->ultimo_contacto?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                @if ($d->esBarrera())
                                    <span class="badge {{ $d->barrera === 'ABIERTA' ? 'bg-success' : 'bg-dark' }}">
                                        {{ $d->barrera }}
                                    </span>
                                    <form method="POST" action="{{ route('accesos.barrera') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="dispositivo_id" value="{{ $d->id }}">
                                        <button name="accion" value="ABRIR" class="btn btn-sm btn-outline-success">Abrir</button>
                                        <button name="accion" value="CERRAR" class="btn btn-sm btn-outline-dark">Cerrar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Puertas (casetas)</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Puerta</th><th>Tipo</th><th>Barrera</th><th>Activa</th></tr></thead>
                    <tbody>
                    @foreach ($puertas as $p)
                        <tr>
                            <td>
                                <strong>{{ $p->nombre }}</strong><br>
                                <small class="text-muted">{{ $p->ubicacion }}</small>
                            </td>
                            <td>{{ $p->tipo }}</td>
                            <td class="small">{{ $p->dispositivo?->nombre ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $p->activo ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $p->activo ? 'Sí' : 'No' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
