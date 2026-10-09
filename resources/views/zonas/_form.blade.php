@csrf

<div class="row g-3">
    <div class="col-md-5">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input name="nombre" value="{{ old('nombre', $zona->nombre ?? '') }}"
               class="form-control @error('nombre') is-invalid @enderror" required maxlength="80"
               placeholder="Ej. A - Pensiones">
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-7">
        <label class="form-label">Descripción</label>
        <input name="descripcion" value="{{ old('descripcion', $zona->descripcion ?? '') }}"
               class="form-control @error('descripcion') is-invalid @enderror" maxlength="255">
        @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 form-check ms-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" class="form-check-input" name="activo" value="1" id="activoZ"
               @checked(old('activo', $zona->activo ?? true))>
        <label class="form-check-label" for="activoZ">Activa</label>
    </div>
</div>
