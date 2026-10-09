@csrf

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input name="nombre" value="{{ old('nombre', $cliente->nombre ?? '') }}"
               class="form-control @error('nombre') is-invalid @enderror" required maxlength="100">
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Apellido paterno <span class="text-danger">*</span></label>
        <input name="apellido_paterno" value="{{ old('apellido_paterno', $cliente->apellido_paterno ?? '') }}"
               class="form-control @error('apellido_paterno') is-invalid @enderror" required maxlength="100">
        @error('apellido_paterno')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Apellido materno</label>
        <input name="apellido_materno" value="{{ old('apellido_materno', $cliente->apellido_materno ?? '') }}"
               class="form-control @error('apellido_materno') is-invalid @enderror" maxlength="100">
        @error('apellido_materno')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input name="telefono" value="{{ old('telefono', $cliente->telefono ?? '') }}"
               class="form-control @error('telefono') is-invalid @enderror" maxlength="30">
        @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Correo</label>
        <input type="email" name="email" value="{{ old('email', $cliente->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror" maxlength="150">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">RFC</label>
        <input name="rfc" value="{{ old('rfc', $cliente->rfc ?? '') }}"
               class="form-control @error('rfc') is-invalid @enderror" maxlength="20">
        @error('rfc')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Dirección</label>
        <input name="direccion" value="{{ old('direccion', $cliente->direccion ?? '') }}"
               class="form-control @error('direccion') is-invalid @enderror" maxlength="255">
        @error('direccion')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Ciudad</label>
        <input name="ciudad" value="{{ old('ciudad', $cliente->ciudad ?? '') }}"
               class="form-control @error('ciudad') is-invalid @enderror" maxlength="100">
        @error('ciudad')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Código postal</label>
        <input name="codigo_postal" value="{{ old('codigo_postal', $cliente->codigo_postal ?? '') }}"
               class="form-control @error('codigo_postal') is-invalid @enderror" maxlength="10">
        @error('codigo_postal')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Fecha de nacimiento</label>
        <input type="date" name="fecha_nacimiento"
               value="{{ old('fecha_nacimiento', isset($cliente) && $cliente->fecha_nacimiento ? $cliente->fecha_nacimiento->format('Y-m-d') : '') }}"
               class="form-control @error('fecha_nacimiento') is-invalid @enderror">
        @error('fecha_nacimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" rows="1"
                  class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $cliente->observaciones ?? '') }}</textarea>
        @error('observaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 form-check ms-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" class="form-check-input" name="activo" value="1" id="activo"
               @checked(old('activo', $cliente->activo ?? true))>
        <label class="form-check-label" for="activo">Activo</label>
    </div>
</div>
