@csrf

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Placas <span class="text-danger">*</span></label>
        <input name="placas" value="{{ old('placas', $vehiculo->placas ?? '') }}" maxlength="20"
               class="form-control text-uppercase @error('placas') is-invalid @enderror" required>
        @error('placas')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Tipo <span class="text-danger">*</span></label>
        <select name="tipo_vehiculo_id" class="form-select @error('tipo_vehiculo_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected(old('tipo_vehiculo_id', $vehiculo->tipo_vehiculo_id ?? '') == $t->id)>
                    {{ $t->nombre }}
                </option>
            @endforeach
        </select>
        @error('tipo_vehiculo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Marca</label>
        <input name="marca" value="{{ old('marca', $vehiculo->marca ?? '') }}" maxlength="80" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">Modelo</label>
        <input name="modelo" value="{{ old('modelo', $vehiculo->modelo ?? '') }}" maxlength="80" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">Año</label>
        <input type="number" name="anio" min="1950" max="2100"
               value="{{ old('anio', $vehiculo->anio ?? '') }}" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">Color</label>
        <input name="color" value="{{ old('color', $vehiculo->color ?? '') }}" maxlength="50" class="form-control">
    </div>

    <div class="col-md-4">
        <label class="form-label">VIN / Número de serie</label>
        <input name="vin" value="{{ old('vin', $vehiculo->vin ?? '') }}" maxlength="50"
               class="form-control @error('vin') is-invalid @enderror">
        @error('vin')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Número de motor</label>
        <input name="numero_motor" value="{{ old('numero_motor', $vehiculo->numero_motor ?? '') }}" maxlength="100" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label">Fotografía (máx. 2 MB)</label>
        <input type="file" name="foto" accept="image/*"
               class="form-control @error('foto') is-invalid @enderror">
        @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" rows="2" class="form-control">{{ old('observaciones', $vehiculo->observaciones ?? '') }}</textarea>
    </div>

    <div class="col-12 form-check ms-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" class="form-check-input" name="activo" value="1" id="activoV"
               @checked(old('activo', $vehiculo->activo ?? true))>
        <label class="form-check-label" for="activoV">Activo</label>
    </div>
</div>
