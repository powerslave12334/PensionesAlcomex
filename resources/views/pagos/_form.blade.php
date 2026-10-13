@php
    $f = old();
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="contrato_pension_id">Contrato <span class="text-danger">*</span></label>
        @if ($contrato)
            <input type="hidden" name="contrato_pension_id" value="{{ $contrato->id }}">
            <input type="text" id="contrato_pension_id" class="form-control" readonly tabindex="-1"
                   value="{{ $contrato->numero_contrato }} — {{ $contrato->cliente?->nombre_completo ?? 's/n' }}">
        @else
            <select name="contrato_pension_id" id="contrato_pension_id" class="form-select" required>
                <option value="">— Seleccione contrato —</option>
                @foreach ($contratos as $c)
                    <option value="{{ $c->id }}"
                        @selected((int) ($f['contrato_pension_id'] ?? 0) === $c->id)
                        data-precio-mes="{{ number_format((float) $c->precio / (\App\Models\ContratoPension::MESES[$c->periodicidad] ?? 1), 2, '.', '') }}"
                        data-inicio="{{ $c->fecha_inicio->format('Y-m-d') }}">
                        {{ $c->numero_contrato }} — {{ $c->cliente?->nombre_completo ?? 's/n' }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">Sin contratos cancelados ni finalizados.</small>
        @endif
        @error('contrato_pension_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    {{-- Estado del cobro: siempre PAGADO, oculto al usuario --}}
    <input type="hidden" name="estado" value="PAGADO">

    <div class="col-md-3">
        <label class="form-label" for="periodo_inicio">Periodo desde <span class="text-danger">*</span></label>
        <input type="date" name="periodo_inicio" id="periodo_inicio" class="form-control" readonly tabindex="-1"
               value="{{ $f['periodo_inicio'] ?? $prefill['periodo_inicio'] }}" required>
        <small class="text-muted">Fecha de inicio de la pensión.</small>
        @error('periodo_inicio') <div class="invalid-feedback d-block">{{ $message }}</div> @endif
    </div>

    <div class="col-md-3">
        <label class="form-label" for="fecha_pago">Fecha del pago <span class="text-danger">*</span></label>
        <input type="date" name="fecha_pago" id="fecha_pago" class="form-control" readonly tabindex="-1"
               value="{{ $f['fecha_pago'] ?? now()->toDateString() }}" required>
        <small class="text-muted">Se registra con la fecha de hoy.</small>
        @error('fecha_pago') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="subtotal">Subtotal <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" name="subtotal" id="subtotal" class="form-control"
               value="{{ $f['subtotal'] ?? $prefill['subtotal'] }}" required>
        <small class="text-muted" id="ayudaMeses">Meses × mensualidad, del inicio de la pensión al día del pago.</small>
        @error('subtotal') <div class="invalid-feedback d-block">{{ $message }}</div> @endif
    </div>

    <div class="col-md-3">
        <label class="form-label" for="descuento">Descuento</label>
        <input type="number" step="0.01" min="0" name="descuento" id="descuento" class="form-control"
               value="{{ $f['descuento'] ?? '0.00' }}">
        @error('descuento') <div class="invalid-feedback d-block">{{ $message }}</div> @endif
    </div>

    <div class="col-md-3">
        <label class="form-label" for="impuesto">Impuesto</label>
        <input type="number" step="0.01" min="0" name="impuesto" id="impuesto" class="form-control"
               value="{{ $f['impuesto'] ?? '0.00' }}">
        @error('impuesto') <div class="invalid-feedback d-block">{{ $message }}</div> @endif
    </div>

    <div class="col-md-3">
        <label class="form-label" for="total">Total <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="total" id="total" class="form-control"
               value="{{ $f['total'] ?? $prefill['subtotal'] ?? '0.00' }}" required>
        <small class="text-muted">Calculado: − descuento + impuesto</small>
        @error('total') <div class="invalid-feedback d-block">{{ $message }}</div> @endif
    </div>

    <div class="col-md-4">
        <label class="form-label" for="metodo_pago_id">Método de pago <span class="text-danger">*</span></label>
        <select name="metodo_pago_id" id="metodo_pago_id" class="form-select" required>
            <option value="">— Seleccione —</option>
            @foreach ($metodos as $m)
                <option value="{{ $m->id }}"
                    @selected((int) ($f['metodo_pago_id'] ?? 0) === $m->id)>
                    {{ $m->nombre }}
                </option>
            @endforeach
        </select>
        @error('metodo_pago_id') <div class="invalid-feedback d-block">{{ $message }}</div> @endif
    </div>

    <div class="col-12">
        <label class="form-label" for="observaciones">Observaciones</label>
        <textarea name="observaciones" id="observaciones" rows="2" class="form-control"
                  maxlength="1000">{{ old('observaciones') }}</textarea>
        @error('observaciones') <div class="invalid-feedback d-block">{{ $message }}</div> @endif
    </div>
</div>

<script>
    (function () {
        const contrato = document.querySelector('#contrato_pension_id');
        const fechaPago = document.querySelector('#fecha_pago');
        const inicio    = document.querySelector('#periodo_inicio');
        const subtotal  = document.querySelector('#subtotal');
        const ayuda    = document.querySelector('#ayudaMeses');
        const desc     = document.querySelector('#descuento');
        const imp      = document.querySelector('#impuesto');
        const total    = document.querySelector('#total');

        function calcular() {
            const s = parseFloat(subtotal.value || 0);
            const d = parseFloat(desc.value || 0);
            const i = parseFloat(imp.value || 0);
            total.value = (Math.round((s - d + i) * 100) / 100).toFixed(2);
        }

        // Meses completos con la convención del sistema: un periodo de N meses
        // va de `inicio` a `inicio + N meses − 1 día` (igual que el servidor).
        function mesesCobrados(desde, hasta) {
            if (!desde || !hasta) return null;
            const a = new Date(desde + 'T00:00:00');
            const b = new Date(hasta + 'T00:00:00');
            if (isNaN(a) || isNaN(b) || b < a) return null;

            let n = 0;
            while (n < 1200) {
                const finN = new Date(a);
                finN.setMonth(finN.getMonth() + n + 1);
                finN.setDate(finN.getDate() - 1);
                if (finN > b) break;
                n++;
            }
            return Math.max(1, n);
        }

        // Solo la página independiente (sin contrato fijo) tiene el select.
        // En el modal todo viene del servidor y estos campos son de solo lectura.
        function seleccionarContrato() {
            if (!contrato || contrato.tagName !== 'SELECT') return;
            const opcion = contrato.options[contrato.selectedIndex];
            if (!opcion || !opcion.dataset.precioMes) return;

            inicio.value = opcion.dataset.inicio;

            const meses = mesesCobrados(inicio.value, fechaPago.value);
            if (meses === null) {
                ayuda.textContent = 'Indica la fecha del pago para calcular el subtotal.';
                return;
            }

            const precioMes = parseFloat(opcion.dataset.precioMes);
            const s = Math.round(precioMes * meses * 100) / 100;
            subtotal.value = s.toFixed(2);
            ayuda.textContent = meses + (meses === 1 ? ' mes' : ' meses')
                + ' × $' + precioMes.toFixed(2) + ' = $' + s.toFixed(2);
            calcular();
        }

        [subtotal, desc, imp].forEach(el => el.addEventListener('input', calcular));
        if (contrato && contrato.tagName === 'SELECT') {
            contrato.addEventListener('change', seleccionarContrato);
            seleccionarContrato();
        }

        calcular();
    })();
</script>
