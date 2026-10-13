@csrf

<div class="row g-3">
    {{-- ===== Cliente: tres campos planos (sin buscador ni alta rápida) ===== --}}
    <div class="col-md-12">
        <div class="row g-3">
            <div class="col-4">
                <label for="cliente_nombre" class="form-label">Nombre del cliente <span class="text-danger">*</span></label>
                <input type="text" name="cliente_nombre" id="cliente_nombre" maxlength="100"
                       value="{{ old('cliente_nombre', $contrato->cliente?->nombre ?? '') }}"
                       class="form-control @error('cliente_nombre') is-invalid @enderror" required>
                @error('cliente_nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="cliente_apellido" class="form-label">Apellido(s) <span class="text-danger">*</span></label>
                <input type="text" name="cliente_apellido" id="cliente_apellido" maxlength="100"
                       value="{{ old('cliente_apellido', $contrato->cliente?->apellidos_completos ?? '') }}"
                       class="form-control @error('cliente_apellido') is-invalid @enderror" required>
                @error('cliente_apellido')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
             <div class="col-md-4">
                <label for="contacto_empresa" class="form-label">Número de la empresa o encargado de la unidad <span class="text-danger">*</span></label>
                <input type="text" name="contacto_empresa" id="contacto_empresa" maxlength="100"
                    value="{{ old('contacto_empresa', $contrato->contacto_empresa ?? '') }}"
                    class="form-control @error('contacto_empresa') is-invalid @enderror"
                    placeholder="Teléfono o persona de contacto" required>
                @error('contacto_empresa')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- ===== Empresa ===== --}}
    <div class="col-md-6">
        <label for="nombre_empresa" class="form-label">Nombre de la empresa <span class="text-danger">*</span></label>
        <input type="text" name="nombre_empresa" id="nombre_empresa" maxlength="150"
               value="{{ old('nombre_empresa', $contrato->nombre_empresa ?? '') }}"
               class="form-control @error('nombre_empresa') is-invalid @enderror" required>
        @error('nombre_empresa')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="direccion_empresa" class="form-label">Dirección <span class="text-danger">*</span></label>
        <input type="text" name="direccion_empresa" id="direccion_empresa" maxlength="255"
               value="{{ old('direccion_empresa', $contrato->direccion_empresa ?? '') }}"
               class="form-control @error('direccion_empresa') is-invalid @enderror"
               placeholder="Calle, número, colonia…" required>
        @error('direccion_empresa')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- ===== Entrada y unidad ===== --}}
    <div class="col-md-4">
        <label for="fecha_entrada" class="form-label">Fecha y hora de entrada</label>
        <input type="datetime-local" name="fecha_entrada" id="fecha_entrada"
               value="{{ old('fecha_entrada', optional($contrato->fecha_entrada ?? null)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}"
               class="form-control @error('fecha_entrada') is-invalid @enderror">
        @error('fecha_entrada')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Se llena con la hora actual; puedes ajustarla.</small>
    </div>

    <div class="col-md-4">
        <label for="tipo_vehiculo_id" class="form-label">Tipo de unidad <span class="text-danger">*</span></label>
        <select name="tipo_vehiculo_id" id="tipo_vehiculo_id"
                class="form-select @error('tipo_vehiculo_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($tiposVehiculo as $tipo)
                <option value="{{ $tipo->id }}"
                        @selected(old('tipo_vehiculo_id', $contrato->vehiculo?->tipo_vehiculo_id ?? '') == $tipo->id)>
                    {{ $tipo->nombre }}
                </option>
            @endforeach
        </select>
        @error('tipo_vehiculo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="placas" class="form-label">Placas <span class="text-danger">*</span></label>
        <input type="text" name="placas" id="placas" maxlength="20"
               value="{{ old('placas', $contrato->vehiculo?->placas ?? '') }}"
               class="form-control text-uppercase @error('placas') is-invalid @enderror"
               placeholder="ABC123" autocomplete="off" required>
        @error('placas')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div id="vehiculo_aviso" class="alert py-2 small mt-2 mb-0 d-none" role="status"></div>
    </div>

    <div class="col-md-4">
        <label for="marca" class="form-label">Marca <span class="text-danger">*</span></label>
        <input type="text" name="marca" id="marca" maxlength="80"
               value="{{ old('marca', $contrato->vehiculo?->marca ?? '') }}"
               class="form-control @error('marca') is-invalid @enderror" required>
        @error('marca')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="modelo" class="form-label">Modelo <span class="text-danger">*</span></label>
        <input type="text" name="modelo" id="modelo" maxlength="80"
               value="{{ old('modelo', $contrato->vehiculo?->modelo ?? '') }}"
               class="form-control @error('modelo') is-invalid @enderror" required>
        @error('modelo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="color" class="form-label">Color <span class="text-danger">*</span></label>
        <input type="text" name="color" id="color" maxlength="50"
               value="{{ old('color', $contrato->vehiculo?->color ?? '') }}"
               class="form-control @error('color') is-invalid @enderror" required>
        @error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- ===== Documentación: una sola de las tres ===== --}}
    <div class="col-md-4">
        <label class="form-label">Documentación <span class="text-danger">*</span></label>
        <div class="d-flex gap-4 flex-wrap pt-1">
            @foreach (\App\Models\ContratoPension::DOCUMENTACIONES as $doc)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="documentacion"
                           id="doc_{{ $doc }}" value="{{ $doc }}"
                           @checked(old('documentacion', $contrato->documentacion ?? '') === $doc) required>
                    <label class="form-check-label" for="doc_{{ $doc }}">
                        {{ \App\Models\ContratoPension::DOCUMENTACION_LABELS[$doc] }}
                    </label>
                </div>
            @endforeach
        </div>
        @error('documentacion')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    {{-- ===== Datos del contrato ===== --}}
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

    <div class="col-md-4">
        <label class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
        <input type="date" name="fecha_inicio"
               value="{{ old('fecha_inicio', optional($contrato->fecha_inicio ?? null)->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
               class="form-control @error('fecha_inicio') is-invalid @enderror" required>
        @error('fecha_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <small class="text-muted">Se llena con la tarifa elegida; puedes ajustarlo.</small>
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

    <div class="col-md-6">
        <label class="form-label">Depósito en garantía</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="deposito" step="0.01" min="0"
                   value="{{ old('deposito', $contrato->deposito ?? 0) }}"
                   class="form-control @error('deposito') is-invalid @enderror">
            @error('deposito')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

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

    {{-- Autocompletado de la unidad por placas: busca el vehículo guardado y
         rellena tipo/marca/modelo/color. Sin JS se captura a mano y el
         servidor reutiliza (o rechaza si las placas son de otro cliente). --}}
    (function () {
        const placas   = document.getElementById('placas');
        const aviso    = document.getElementById('vehiculo_aviso');
        const tipo     = document.getElementById('tipo_vehiculo_id');
        const marca    = document.getElementById('marca');
        const modelo   = document.getElementById('modelo');
        const color    = document.getElementById('color');
        if (!placas || !aviso) return;

        const ruta = '{{ route('vehiculos.buscar') }}';
        let tiempo   = null;
        let datos    = null;  // vehículo encontrado en el servidor
        let auto     = false; // los campos los llenó el autocompletado
        let editado  = false; // el usuario modificó un campo después

        function limpiarCampos() {
            if (tipo) tipo.value = '';
            if (marca) marca.value = '';
            if (modelo) modelo.value = '';
            if (color) color.value = '';
            auto = false;
        }

        function rellenar(v) {
            if (tipo) tipo.value = v.tipo_vehiculo_id ?? '';
            if (marca) marca.value = v.marca ?? '';
            if (modelo) modelo.value = v.modelo ?? '';
            if (color) color.value = v.color ?? '';
            auto = true;
            editado = false;
        }

        function pintarAviso() {
            if (!datos) {
                aviso.className = 'alert py-2 small mt-2 mb-0 d-none';
                aviso.innerHTML = '';
                return;
            }
            const ficha = [datos.tipo, datos.marca, datos.modelo, datos.color]
                .filter(Boolean).join(' · ');

            aviso.className = 'alert alert-info py-2 small mt-2 mb-0';
            aviso.textContent = ficha || datos.placas;
            aviso.classList.remove('d-none');
        }

        async function buscar() {
            const texto = placas.value.trim().toUpperCase();
            datos = null;

            if (texto.length < 3) {
                pintarAviso();
                if (auto && !editado) limpiarCampos();
                return;
            }

            try {
                const res = await fetch(ruta + '?placas=' + encodeURIComponent(texto), {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const json = await res.json();
                    datos = json.vehiculo || null;
                }
            } catch (e) { /* sin red: queda la captura manual */ }

            if (placas.value.trim().toUpperCase() !== texto) return; // la entrada cambió

            if (datos) {
                rellenar(datos);
            } else if (auto && !editado) {
                limpiarCampos(); // las placas cambiaron: el autocompletado anterior ya no aplica
            }
            pintarAviso();
        }

        placas.addEventListener('input', function () {
            aviso.classList.add('d-none');
            clearTimeout(tiempo);
            tiempo = setTimeout(buscar, 300);
        });

        [tipo, marca, modelo, color].forEach(function (campo) {
            if (campo) campo.addEventListener('input', function () { editado = true; });
        });

        // Edición o error de validación: placas precargadas → busca al cargar.
        if (placas.value.trim().length >= 3) buscar();
    })();
</script>
