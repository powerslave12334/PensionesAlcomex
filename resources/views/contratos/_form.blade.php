@csrf

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Cliente <span class="text-danger">*</span></label>
        <select name="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($clientes as $cliente)
                <option value="{{ $cliente->id }}" @selected(old('cliente_id', $contrato->cliente_id ?? '') == $cliente->id)>
                    {{ $cliente->nombre_completo }}
                </option>
            @endforeach
        </select>
        @error('cliente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Vehículo <span class="text-danger">*</span></label>
        <select name="vehiculo_id" class="form-select @error('vehiculo_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($vehiculos as $vehiculo)
                <option value="{{ $vehiculo->id }}" @selected(old('vehiculo_id', $contrato->vehiculo_id ?? '') == $vehiculo->id)>
                    {{ $vehiculo->placas }} — {{ $vehiculo->marca }} {{ $vehiculo->modelo }}
                    ({{ $vehiculo->tipoVehiculo?->nombre }})
                </option>
            @endforeach
        </select>
        @error('vehiculo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">El vehículo debe pertenecer al cliente seleccionado.</small>
    </div>

    <div class="col-md-4">
        <label class="form-label">Tarifa vigente <span class="text-danger">*</span></label>
        <select name="tarifa_id" class="form-select @error('tarifa_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($tarifas as $tarifa)
                <option value="{{ $tarifa->id }}" data-precio="{{ $tarifa->precio }}"
                        @selected(old('tarifa_id', $contrato->tarifa_id ?? '') == $tarifa->id)>
                    {{ $tarifa->etiqueta }}
                </option>
            @endforeach
        </select>
        @error('tarifa_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Solo aparecen las tarifas vigentes hoy (US-0302).</small>
    </div>

    <div class="col-md-3">
        <label class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
        <input type="date" name="fecha_inicio"
               value="{{ old('fecha_inicio', optional($contrato->fecha_inicio ?? null)->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
               class="form-control @error('fecha_inicio') is-invalid @enderror" required>
        @error('fecha_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Fecha de término</label>
        <input type="date" name="fecha_fin"
               value="{{ old('fecha_fin', optional($contrato->fecha_fin ?? null)->format('Y-m-d')) }}"
               class="form-control @error('fecha_fin') is-invalid @enderror">
        @error('fecha_fin')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Vacío = sin vencimiento fijo.</small>
    </div>

    <div class="col-md-3">
        <label class="form-label">Precio congelado <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="precio" step="0.01" min="0" max="9999999999.99"
                   value="{{ old('precio', $contrato->precio ?? '') }}"
                   class="form-control @error('precio') is-invalid @enderror" required>
            @error('precio')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <small class="text-muted">Se llena con la tarifa elegida; puedes ajustarlo.</small>
    </div>

    <div class="col-md-3">
        <label class="form-label">Periodicidad <span class="text-danger">*</span></label>
        <select name="periodicidad" class="form-select @error('periodicidad') is-invalid @enderror" required>
            @foreach (\App\Models\ContratoPension::PERIODICIDADES as $p)
                <option value="{{ $p }}" @selected(old('periodicidad', $contrato->periodicidad ?? 'MENSUAL') === $p)>
                    {{ \App\Models\ContratoPension::PERIODICIDAD_LABELS[$p] }}
                </option>
            @endforeach
        </select>
        @error('periodicidad')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Día de cobro</label>
        <input type="number" name="dia_cobro" min="1" max="31"
               value="{{ old('dia_cobro', $contrato->dia_cobro ?? '') }}"
               class="form-control @error('dia_cobro') is-invalid @enderror" placeholder="15">
        @error('dia_cobro')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Depósito en garantía</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="deposito" step="0.01" min="0"
                   value="{{ old('deposito', $contrato->deposito ?? 0) }}"
                   class="form-control @error('deposito') is-invalid @enderror">
            @error('deposito')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    @if (! isset($contrato))
    <div class="col-md-6">
        <label class="form-label">Cajón (opcional)</label>
        <select name="espacio_id" class="form-select @error('espacio_id') is-invalid @enderror">
            <option value="">— Asignar después desde el expediente —</option>
            @foreach ($espacios as $espacio)
                <option value="{{ $espacio->id }}" @selected(old('espacio_id') == $espacio->id)>
                    {{ $espacio->codigo }} · {{ $espacio->tipoVehiculo?->nombre }} · {{ $espacio->zona?->nombre }}
                </option>
            @endforeach
        </select>
        @error('espacio_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Solo cajones libres; la compatibilidad con el tipo de vehículo se valida en el servidor.</small>
    </div>
    @endif

    <div class="col-12">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" rows="2"
                  class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $contrato->observaciones ?? '') }}</textarea>
        @error('observaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

{{-- Copia el precio de la tarifa seleccionada; sin JS se captura a mano. --}}
<script>
    (function () {
        const select = document.querySelector('select[name="tarifa_id"]');
        const precio = document.querySelector('input[name="precio"]');
        if (!select || !precio) return;
        select.addEventListener('change', function () {
            const opcion = select.options[select.selectedIndex];
            if (opcion && opcion.dataset.precio) {
                precio.value = opcion.dataset.precio;
            }
        });
    })();
</script>
