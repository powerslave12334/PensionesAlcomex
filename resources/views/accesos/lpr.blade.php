@extends('layouts.app')
@section('title', 'Acceso por lectura de placas (LPR)')

@section('content')
<h4 class="mb-3">Lectura de placas (LPR) <span class="fs-6 text-muted">(cámara simulada)</span></h4>

<div class="alert alert-secondary">
    La cámara reconoce la placa y dispara la barrera. Aquí se captura lo que la
    cámara «leyó»: <strong>fuera</strong> → entrada, <strong>adentro</strong> → salida.
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Placa detectada</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('accesos.lpr.leer') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="placas" class="form-label">Placas <span class="text-danger">*</span></label>
                        <input type="text" name="placas" id="placas" maxlength="20" required autofocus
                               value="{{ old('placas') }}" placeholder="Ej. ABC1234"
                               class="form-control form-control-lg text-uppercase @error('placas') is-invalid @enderror">
                        @error('placas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="foto" class="form-label">URL de la foto del censo</label>
                        <input type="url" name="foto" id="foto" maxlength="500"
                               value="{{ old('foto') }}" placeholder="https://…"
                               class="form-control @error('foto') is-invalid @enderror">
                        @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-primary w-100">Procesar lectura</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-light"><strong>Placas registradas</strong></div>
            <ul class="list-group list-group-flush">
                @foreach ($vehiculos as $v)
                    <li class="list-group-item d-flex justify-content-between">
                        <span><strong>{{ $v->placas }}</strong>
                            <small class="text-muted">— {{ $v->marca }} {{ $v->modelo }}</small></span>
                        @if ($v->entradaAbierta)
                            <span class="badge bg-info text-dark">Adentro</span>
                        @else
                            <span class="badge bg-secondary">Fuera</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Últimos movimientos</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Fecha y hora</th><th>Tipo</th><th>Vehículo</th><th>Método</th><th>Usuario</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($movimientos as $m)
                        <tr>
                            <td>{{ $m->fecha_hora->format('d/m/Y H:i') }}</td>
                            <td><span class="badge {{ $m->tipo === 'ENTRADA' ? 'bg-primary' : 'bg-dark' }}">{{ $m->tipo_label }}</span></td>
                            <td>{{ $m->vehiculo?->placas ?? $m->placas_detectadas ?? '—' }}</td>
                            <td>{{ $m->metodo_label }}</td>
                            <td>{{ $m->usuario?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Todavía no hay movimientos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
