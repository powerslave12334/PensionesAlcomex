@csrf

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Zona <span class="text-danger">*</span></label>
        <select name="zona_id" class="form-select @error('zona_id') is-invalid @enderror" required>
            <option value="">— Seleccione una zona —</option>
            @foreach ($zonas as $z)
                <option value="{{ $z->id }}" @selected(old('zona_id', $espacio->zona_id ?? '') == $z->id)>
                    {{ $z->nombre }}
                </option>
            @endforeach
        </select>
        @error('zona_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Código <span class="text-danger">*</span></label>
        <input name="codigo" value="{{ old('codigo', $espacio->codigo ?? '') }}" maxlength="30"
               class="form-control text-uppercase @error('codigo') is-invalid @enderror"
               placeholder="Ej. A-01" required>
        @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Único dentro de la zona.</small>
    </div>

    <div class="col-md-4">
        <label class="form-label">Tipo compatible <span class="text-danger">*</span></label>
        <select name="tipo_vehiculo_id" class="form-select @error('tipo_vehiculo_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected(old('tipo_vehiculo_id', $espacio->tipo_vehiculo_id ?? '') == $t->id)>
                    {{ $t->nombre }}
                </option>
            @endforeach
        </select>
        @error('tipo_vehiculo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Estado <span class="text-danger">*</span></label>
        <select name="estado" class="form-select @error('estado') is-invalid @enderror" required>
            @foreach (\App\Models\Espacio::ESTADOS as $op)
                <option value="{{ $op }}" @selected(old('estado', $espacio->estado ?? 'DISPONIBLE') === $op)>
                    {{ \App\Models\Espacio::ESTADO_LABELS[$op] }}
                </option>
            @endforeach
        </select>
        @error('estado')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Número</label>
        <input name="numero" value="{{ old('numero', $espacio->numero ?? '') }}" maxlength="20" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">Piso</label>
        <input name="piso" value="{{ old('piso', $espacio->piso ?? '') }}" maxlength="20"
               class="form-control" placeholder="PB, 1, 2…">
    </div>

    <div class="col-12">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" rows="2"
                  class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $espacio->observaciones ?? '') }}</textarea>
        @error('observaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 form-check ms-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" class="form-check-input" name="activo" value="1" id="activoE"
               @checked(old('activo', $espacio->activo ?? true))>
        <label class="form-check-label" for="activoE">Activo</label>
    </div>
</div>
