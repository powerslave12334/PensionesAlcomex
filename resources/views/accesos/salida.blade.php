@extends('layouts.app')
@section('title', 'Registrar salida')

@section('content')
<h4 class="mb-3">Registrar salida</h4>

<div class="row justify-content-center">
    <div class="col-lg-9">
        {{-- Ficha de la entrada que se cierra --}}
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">
                            {{ $acceso->vehiculo?->placas }}
                            <span class="badge bg-primary">Entrada</span>
                        </h5>
                        <div class="text-muted">
                            {{ trim(($acceso->vehiculo?->marca . ' ' . $acceso->vehiculo?->modelo) ?: '') }}
                            · {{ $acceso->cliente?->nombre_completo ?? 'Sin cliente' }}
                        </div>
                        <div class="small text-muted">
                            Entró {{ $acceso->fecha_hora->format('d/m/Y H:i') }}
                            · {{ $acceso->metodo_label }}
                            · Cajón {{ $acceso->espacio?->codigo ?? '—' }}
                            · Registró {{ $acceso->usuario?->name ?? '—' }}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-5 fw-bold" id="permanenciaVivo">
                            {{ (int) \Illuminate\Support\Carbon::parse($acceso->fecha_hora)->diffInMinutes(now()) }} min
                        </div>
                        <small class="text-muted">Permanencia al abrir esta página</small>
                    </div>
                </div>
            </div>
        </div>

        @if ($contrato)
            <div class="alert alert-success">
                <strong>Pensión activa ({{ $contrato->numero_contrato }}):</strong>
                la salida no genera cobro, el cliente ya paga la mensualidad.
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('accesos.salida.store', $acceso) }}" id="salidaForm">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_hora" class="form-label">Fecha y hora de salida <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_hora" id="fecha_hora"
                                   value="{{ old('fecha_hora', now()->format('Y-m-d\TH:i')) }}"
                                   class="form-control @error('fecha_hora') is-invalid @enderror"
                                   data-entrada="{{ $acceso->fecha_hora->format('Y-m-d H:i:s') }}" required>
                            @error('fecha_hora') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="puerta_id" class="form-label">Puerta / caseta</label>
                            <select name="puerta_id" id="puerta_id" class="form-select @error('puerta_id') is-invalid @enderror">
                                <option value="">— Sin registro de caseta —</option>
                                @foreach ($puertas as $p)
                                    <option value="{{ $p->id }}" @selected((string) old('puerta_id') === (string) $p->id)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                            @error('puerta_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if ($contrato)
                        <input type="hidden" name="estado_cobro" value="NO_APLICA">
                    @else
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tarifa_id" class="form-label">Tarifa de permanencia <span class="text-danger">*</span></label>
                                <select name="tarifa_id" id="tarifa_id" class="form-select @error('tarifa_id') is-invalid @enderror">
                                    @foreach ($tarifas as $t)
                                        <option value="{{ $t->id }}"
                                            data-precio="{{ $t->precio }}"
                                            data-bloque="{{ ['HORA' => 60, 'NOCHE' => 720, 'DIA' => 1440][$t->tipo_tarifa] ?? 60 }}"
                                            @selected((string) old('tarifa_id', $tarifaSugerida?->id) === (string) $t->id)>
                                            {{ $t->etiqueta }}{{ $t->id === $tarifaSugerida?->id ? ' (sugerida)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('tarifa_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado del cobro <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3 pt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="estado_cobro"
                                               id="cob_pendiente" value="PENDIENTE"
                                               @checked(old('estado_cobro', 'PENDIENTE') === 'PENDIENTE')>
                                        <label class="form-check-label" for="cob_pendiente">Dejar por cobrar</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="estado_cobro"
                                               id="cob_cobrado" value="COBRADO"
                                               @checked(old('estado_cobro') === 'COBRADO')>
                                        <label class="form-check-label" for="cob_cobrado">Cobrar ahora</label>
                                    </div>
                                </div>
                                @error('estado_cobro') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3" id="bloqueMetodo" hidden>
                            <label for="metodo_pago_id" class="form-label">Método de cobro <span class="text-danger">*</span></label>
                            <select name="metodo_pago_id" id="metodo_pago_id" class="form-select @error('metodo_pago_id') is-invalid @enderror">
                                <option value="">— Selecciona —</option>
                                @foreach ($metodos as $m)
                                    <option value="{{ $m->id }}" @selected((string) old('metodo_pago_id') === (string) $m->id)>{{ $m->nombre }}</option>
                                @endforeach
                            </select>
                            @error('metodo_pago_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="alert alert-light border mb-3">
                            <span class="text-muted">Permanencia:</span>
                            <strong id="minVivo">{{ $permanenciaMin }} min</strong>
                            <span class="text-muted ms-3">Cobro estimado:</span>
                            <strong id="estimado">$0.00</strong>
                            <span class="text-muted ms-3 d-none d-sm-inline">El servidor recalcula el importe definitivo.</span>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="foto_salida" class="form-label">Foto de evidencia (URL)</label>
                        <input type="url" name="foto_salida" id="foto_salida" maxlength="500"
                               value="{{ old('foto_salida') }}" placeholder="https://…"
                               class="form-control @error('foto_salida') is-invalid @enderror">
                        @error('foto_salida') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" rows="2" maxlength="1000"
                                  class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones') }}</textarea>
                        @error('observaciones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-warning">Registrar salida</button>
                        <a href="{{ route('accesos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('salidaForm');
    const entrada = document.querySelector('[name=fecha_hora]');
    const tarifa  = document.getElementById('tarifa_id');
    const minTxt  = document.getElementById('minVivo');
    const estTxt  = document.getElementById('estimado');
    const bloque  = document.getElementById('bloqueMetodo');
    if (!form || !entrada) return;

    function minutos() {
        const fin = entrada.value ? new Date(entrada.value.replace('T', ' ')) : null;
        const ini = new Date(entrada.dataset.entrada.replace(' ', ' '));
        if (!fin) return 0;
        return Math.max(0, Math.round((fin - ini) / 60000));
    }

    function pintar() {
        const min = minutos();
        if (minTxt) minTxt.textContent = min + ' min';
        if (tarifa && estTxt) {
            const opt  = tarifa.selectedOptions[0];
            const pre  = parseFloat(opt?.dataset.precio || 0);
            const blq  = parseInt(opt?.dataset.bloque || 60, 10);
            const und  = Math.max(1, Math.ceil(min / (blq || 60)));
            estTxt.textContent = '$' + (und * pre).toFixed(2);
        }
    }

    function pintarMetodo() {
        if (!bloque) return;
        const cobrado = document.querySelector('input[name=estado_cobro]:checked')?.value === 'COBRADO';
        bloque.hidden = !cobrado;
        document.getElementById('metodo_pago_id').required = cobrado;
    }

    form.addEventListener('change', pintar);
    form.addEventListener('input', pintar);
    document.querySelectorAll('input[name=estado_cobro]')
        .forEach((r) => r.addEventListener('change', pintarMetodo));

    pintar();
    pintarMetodo();
    setInterval(pintar, 30000);
})();
</script>
@endpush
@endsection
