@csrf

<div class="row g-3">
    <div class="col-md-5">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input name="nombre" value="{{ old('nombre', $tarifa->nombre ?? '') }}" maxlength="100"
               class="form-control @error('nombre') is-invalid @enderror"
               placeholder="Ej. Mensualidad automovil" required>
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Tipo de vehículo <span class="text-danger">*</span></label>
        <select name="tipo_vehiculo_id" class="form-select @error('tipo_vehiculo_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected(old('tipo_vehiculo_id', $tarifa->tipo_vehiculo_id ?? '') == $t->id)>
                    {{ $t->nombre }}
                </option>
            @endforeach
        </select>
        @error('tipo_vehiculo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Periodo <span class="text-danger">*</span></label>
        <select name="tipo_tarifa" class="form-select @error('tipo_tarifa') is-invalid @enderror" required>
            @foreach (\App\Models\Tarifa::TIPOS as $p)
                <option value="{{ $p }}" @selected(old('tipo_tarifa', $tarifa->tipo_tarifa ?? 'MENSUAL') === $p)>
                    {{ \App\Models\Tarifa::TIPO_LABELS[$p] }}
                </option>
            @endforeach
        </select>
        @error('tipo_tarifa')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Precio <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="precio" step="0.01" min="0" max="9999999999.99"
                   value="{{ old('precio', $tarifa->precio ?? '') }}"
                   class="form-control @error('precio') is-invalid @enderror" required>
            @error('precio')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Descripción</label>
        <input name="periodo" value="{{ old('periodo', $tarifa->periodo ?? '') }}" maxlength="30"
               class="form-control @error('periodo') is-invalid @enderror" placeholder="por mes">
        @error('periodo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Hora inicial</label>
        <input type="time" name="hora_inicio" value="{{ old('hora_inicio', $tarifa->hora_inicio ?? '') }}"
               class="form-control @error('hora_inicio') is-invalid @enderror">
        @error('hora_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Hora final</label>
        <input type="time" name="hora_fin" value="{{ old('hora_fin', $tarifa->hora_fin ?? '') }}"
               class="form-control @error('hora_fin') is-invalid @enderror">
        @error('hora_fin')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Vigencia desde</label>
        <input type="date" name="vigencia_desde" value="{{ old('vigencia_desde', optional($tarifa->vigencia_desde ?? null)->format('Y-m-d')) }}"
               class="form-control @error('vigencia_desde') is-invalid @enderror">
        @error('vigencia_desde')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Vigencia hasta</label>
        <input type="date" name="vigencia_hasta" value="{{ old('vigencia_hasta', optional($tarifa->vigencia_hasta ?? null)->format('Y-m-d')) }}"
               class="form-control @error('vigencia_hasta') is-invalid @enderror">
        @error('vigencia_hasta')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 form-check ms-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" class="form-check-input" name="activo" value="1" id="activoT"
               @checked(old('activo', $tarifa->activo ?? true))>
        <label class="form-check-label" for="activoT">Activa</label>
    </div>
</div>
