@extends('layouts.app')
@section('title', 'Nueva entrada')

@section('content')
<h4 class="mb-3">Registrar entrada</h4>

@if ($prefill['contrato'])
    <div class="alert alert-info">
        <strong>QR leído:</strong> contrato {{ $prefill['contrato']->numero_contrato }}
        ({{ $prefill['contrato']->cliente?->nombre_completo }}) — vehículo
        {{ $prefill['contrato']->vehiculo?->placas }}.
        Método de acceso: <strong>Código QR</strong>.
    </div>
@endif

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('accesos.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="vehiculo_id" class="form-label">Vehículo <span class="text-danger">*</span></label>
                        <select name="vehiculo_id" id="vehiculo_id" class="form-select @error('vehiculo_id') is-invalid @enderror" required>
                            <option value="">— Selecciona un vehículo —</option>
                            @foreach ($vehiculos as $v)
                                <option value="{{ $v->id }}" @selected((string) old('vehiculo_id', $prefill['vehiculo_id']) === (string) $v->id)>
                                    {{ $v->placas }} · {{ $v->marca }} {{ $v->modelo }} ·
                                    {{ $v->cliente?->nombre_completo }}
                                </option>
                            @endforeach
                        </select>
                        @error('vehiculo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_hora" class="form-label">Fecha y hora <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_hora" id="fecha_hora"
                                   value="{{ old('fecha_hora', $prefill['fecha_hora']) }}"
                                   class="form-control @error('fecha_hora') is-invalid @enderror" required>
                            @error('fecha_hora') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="metodo_acceso" class="form-label">Método de acceso <span class="text-danger">*</span></label>
                            <select name="metodo_acceso" id="metodo_acceso" class="form-select @error('metodo_acceso') is-invalid @enderror" required>
                                @foreach (\App\Models\MovimientoAcceso::METODOS as $m)
                                    <option value="{{ $m }}" @selected(old('metodo_acceso', $prefill['metodo_acceso']) === $m)>
                                        {{ \App\Models\MovimientoAcceso::METODO_LABELS[$m] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('metodo_acceso') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="espacio_id" class="form-label">Cajón</label>
                            <select name="espacio_id" id="espacio_id" class="form-select @error('espacio_id') is-invalid @enderror">
                                <option value="">Sin cajón (solo tránsito)</option>
                                @foreach ($espaciosContrato as $e)
                                    <option value="{{ $e->id }}"
                                        @selected((string) old('espacio_id') === (string) $e->id)>
                                        {{ $e->codigo }} — cajón de la pensión ({{ $e->zona?->nombre }})
                                    </option>
                                @endforeach
                                @foreach ($espacios as $e)
                                    <option value="{{ $e->id }}" @selected((string) old('espacio_id') === (string) $e->id)>
                                        {{ $e->codigo }} · {{ $e->zona?->nombre }} · {{ $e->estado_label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('espacio_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Los cajones ocupados/reservados solo se eligen si son el de la pensión.</div>
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

                    <div class="mb-3">
                        <label for="foto_entrada" class="form-label">Foto de evidencia (URL)</label>
                        <input type="url" name="foto_entrada" id="foto_entrada" maxlength="500"
                               value="{{ old('foto_entrada') }}" placeholder="https://…"
                               class="form-control @error('foto_entrada') is-invalid @enderror">
                        @error('foto_entrada') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" rows="2" maxlength="1000"
                                  class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones') }}</textarea>
                        @error('observaciones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Registrar entrada</button>
                        <a href="{{ route('accesos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
