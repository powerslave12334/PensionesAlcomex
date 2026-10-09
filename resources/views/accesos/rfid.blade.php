@extends('layouts.app')
@section('title', 'Acceso con tarjeta RFID')

@section('content')
<h4 class="mb-3">Acceso con tarjeta RFID <span class="fs-6 text-muted">(lector simulado)</span></h4>

<div class="alert alert-secondary">
    En el caso real el lector envía el UID por serial/TCP. Aquí se captura el UID
    (o se pulsa «Leer» en la fila del vehículo): si el vehículo está <strong>fuera</strong>
    registra <strong>entrada</strong>, si está <strong>adentro</strong> registra <strong>salida</strong>.
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Leer tarjeta</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('accesos.rfid.leer') }}" id="formRfid">
                    @csrf
                    <div class="mb-3">
                        <label for="uid" class="form-label">UID de la tarjeta <span class="text-danger">*</span></label>
                        <input type="text" name="uid" id="uid" maxlength="40" required autofocus
                               value="{{ old('uid') }}" placeholder="Ej. A1B2C3D4"
                               class="form-control form-control-lg @error('uid') is-invalid @enderror">
                        @error('uid') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-primary w-100">Leer tarjeta</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-light"><strong>Vehículos con tarjeta</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Placas</th><th>UID</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($vehiculos as $v)
                        <tr>
                            <td><strong>{{ $v->placas }}</strong></td>
                            <td class="small">{{ $v->uid_rfid ?? '—' }}</td>
                            <td>
                                @if ($v->entradaAbierta)
                                    <span class="badge bg-info text-dark">Adentro</span>
                                @else
                                    <span class="badge bg-secondary">Fuera</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($v->uid_rfid && \Illuminate\Support\Facades\Gate::allows('accesos.registrar'))
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-leer="{{ $v->uid_rfid }}">Leer</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
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

@push('scripts')
<script>
    document.querySelectorAll('[data-leer]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('uid').value = btn.dataset.leer;
            document.getElementById('formRfid').submit();
        });
    });
</script>
@endpush
@endsection
