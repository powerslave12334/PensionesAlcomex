@php
    $f = old();
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="contrato_pension_id">Contrato <span class="text-danger">*</span></label>
        <select name="contrato_pension_id" id="contrato_pension_id" class="form-select" required
                @if ($contrato) data-fijo="1" @endif>
            <option value="">— Seleccione contrato —</option>
            @foreach ($contratos as $c)
                <option value="{{ $c->id }}"
                    @selected((int) ($f['contrato_pension_id'] ?? ($contrato->id ?? 0)) === $c->id)
                    data-precio="{{ $c->precio }}"
                    data-inicio="{{ $c->fecha_inicio->format('Y-m-d') }}"
                    data-meses="{{ \App\Models\ContratoPension::MESES[$c->periodicidad] ?? 1 }}">
                    {{ $c->numero_contrato }} — {{ $c->cliente?->nombre_completo ?? 's/n' }}
                </option>
            @endforeach
        </select>
        @error('contrato_pension_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <small class="text-muted">Sin contratos cancelados ni finalizados.</small>
    </div>

    <div class="col-md-3">
        <label class="form-label" for="fecha_pago">Fecha del pago <span class="text-danger">*</span></label>
        <input type="date" name="fecha_pago" id="fecha_pago" class="form-control"
               value="{{ $f['fecha_pago'] ?? now()->toDateString() }}" required>
        @error('fecha_pago') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="estado">Estado del cobro <span class="text-danger">*</span></label>
        <select name="estado" id="estado" class="form-select" required>
            <option value="PAGADO" @selected(($f['estado'] ?? 'PAGADO') === 'PAGADO')>Pagado (cobrado)</option>
            <option value="PENDIENTE" @selected(($f['estado'] ?? '') === 'PENDIENTE')>Pendiente (por cobrar)</option>
        </select>
        @error('estado') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="periodo_inicio">Periodo desde <span class="text-danger">*</span></label>
        <input type="date" name="periodo_inicio" id="periodo_inicio" class="form-control"
               value="{{ $f['periodo_inicio'] ?? $prefill['periodo_inicio'] }}" required>
        @error('periodo_inicio') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="periodo_fin">Periodo hasta <span class="text-danger">*</span></label>
        <input type="date" name="periodo_fin" id="periodo_fin" class="form-control"
               value="{{ $f['periodo_fin'] ?? $prefill['periodo_fin'] }}" required>
        @error('periodo_fin') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="subtotal">Subtotal <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" name="subtotal" id="subtotal" class="form-control"
               value="{{ $f['subtotal'] ?? $prefill['subtotal'] }}" required>
        @error('subtotal') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="descuento">Descuento</label>
        <input type="number" step="0.01" min="0" name="descuento" id="descuento" class="form-control"
               value="{{ $f['descuento'] ?? '0.00' }}">
        @error('descuento') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="impuesto">Impuesto</label>
        <input type="number" step="0.01" min="0" name="impuesto" id="impuesto" class="form-control"
               value="{{ $f['impuesto'] ?? '0.00' }}">
        @error('impuesto') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="total">Total <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="total" id="total" class="form-control"
               value="{{ $f['total'] ?? $prefill['subtotal'] ?? '0.00' }}" required>
        <small class="text-muted">Calculado: − descuento + impuesto</small>
        @error('total') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="metodo_pago_id">Método de pago <span class="text-danger">*</span></label>
        <select name="metodo_pago_id" id="metodo_pago_id" class="form-select" required>
            <option value="">— Seleccione —</option>
            @foreach ($metodos as $m)
                <option value="{{ $m->id }}"
                    @selected((int) ($f['metodo_pago_id'] ?? 0) === $m->id)
                    data-referencia="{{ (int) $m->requiere_referencia ? 1 : 0 }}">
                    {{ $m->nombre }}
                </option>
            @endforeach
        </select>
        @error('metodo_pago_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="referencia">Referencia / autorización</label>
        <input type="text" name="referencia" id="referencia" maxlength="100" class="form-control"
               value="{{ old('referencia') }}" placeholder="Obligatoria según el método">
        @error('referencia') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <small class="text-muted" id="refAyuda">El método elegido decide si es obligatoria.</small>
    </div>

    <div class="col-12">
        <label class="form-label" for="observaciones">Observaciones</label>
        <textarea name="observaciones" id="observaciones" rows="2" class="form-control"
                  maxlength="1000">{{ old('observaciones') }}</textarea>
        @error('observaciones') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar cobro</button>
        <a href="{{ route('pagos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const contrato = document.querySelector('#contrato_pension_id');
        const metodo   = document.querySelector('#metodo_pago_id');
        const ref      = document.querySelector('#referencia');
        const refAyuda = document.querySelector('#refAyuda');
        const inicio   = document.querySelector('#periodo_inicio');
        const fin      = document.querySelector('#periodo_fin');
        const subtotal = document.querySelector('#subtotal');
        const desc     = document.querySelector('#descuento');
        const imp      = document.querySelector('#impuesto');
        const total    = document.querySelector('#total');

        function calcular() {
            const s = parseFloat(subtotal.value || 0);
            const d = parseFloat(desc.value || 0);
            const i = parseFloat(imp.value || 0);
            total.value = (Math.round((s - d + i) * 100) / 100).toFixed(2);
        }

        function exigirReferencia() {
            const opcion = metodo.options[metodo.selectedIndex];
            const obliga = opcion && opcion.dataset.referencia === '1';
            ref.required = obliga;
            ref.placeholder = obliga ? 'Obligatoria' : 'Opcional';
            refAyuda.textContent = obliga
                ? 'Este método requiere número de referencia o autorización.'
                : 'Este método no requiere referencia.';
        }

        function desdeContrato() {
            const opcion = contrato.options[contrato.selectedIndex];
            if (!opcion || !opcion.dataset.precio) return;

            subtotal.value = parseFloat(opcion.dataset.precio).toFixed(2);

            const meses = parseInt(opcion.dataset.meses || 1, 10);
            const d = new Date(opcion.dataset.inicio + 'T00:00:00');
            if (isNaN(d)) return;

            inicio.value = opcion.dataset.inicio;

            const hasta = new Date(d);
            hasta.setMonth(hasta.getMonth() + meses);
            hasta.setDate(hasta.getDate() - 1);

            const rellenar = n => String(n).padStart(2, '0');
            fin.value = hasta.getFullYear() + '-' + rellenar(hasta.getMonth() + 1)
                + '-' + rellenar(hasta.getDate());

            calcular();
        }

        [subtotal, desc, imp].forEach(el => el.addEventListener('input', calcular));
        metodo.addEventListener('change', exigirReferencia);
        contrato.addEventListener('change', function () {
            if (!contrato.dataset.fijo) desdeContrato();
        });

        calcular();
        exigirReferencia();
    });
</script>
