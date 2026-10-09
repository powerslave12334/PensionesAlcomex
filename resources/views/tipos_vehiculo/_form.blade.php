@csrf

<div class="row g-3">
    <div class="col-md-5">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input name="nombre" value="{{ old('nombre', $tipo->nombre ?? '') }}"
               class="form-control @error('nombre') is-invalid @enderror" required maxlength="80"
               placeholder="Ej. Automovil">
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Tarifa base <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="tarifa_base" step="0.01" min="0" max="9999999999.99"
                   value="{{ old('tarifa_base', $tipo->tarifa_base ?? '') }}"
                   class="form-control @error('tarifa_base') is-invalid @enderror" required>
            @error('tarifa_base')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <small class="text-muted">Referencia para tarifas (Sprint 3).</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">Descripción</label>
        <input name="descripcion" value="{{ old('descripcion', $tipo->descripcion ?? '') }}"
               class="form-control @error('descripcion') is-invalid @enderror" maxlength="255">
        @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 form-check ms-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" class="form-check-input" name="activo" value="1" id="activoT"
               @checked(old('activo', $tipo->activo ?? true))>
        <label class="form-check-label" for="activoT">Activo</label>
    </div>
</div>
