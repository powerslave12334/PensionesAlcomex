@extends('layouts.app')
@section('title', 'Código QR de acceso')

@section('content')
<h4 class="mb-3">Código QR de acceso <span class="fs-6 text-muted">(US-0503)</span></h4>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Contrato activo</strong></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="contrato" class="form-label">Pensión</label>
                    <select id="contrato" class="form-select">
                        <option value="">— Selecciona —</option>
                        @foreach ($contratos as $c)
                            <option value="{{ $c->numero_contrato }}">
                                {{ $c->numero_contrato }} · {{ $c->cliente?->nombre_completo }} ·
                                {{ $c->vehiculo?->placas }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Al escanearlo se abre la alta de entrada con el vehículo y el
                        método QR precargados.
                    </div>
                </div>

                <div id="qrBox" class="text-center border rounded p-3 bg-white"
                     style="min-height: 220px;">
                    <span class="text-muted">Selecciona un contrato para generar el código.</span>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
                    <a href="{{ route('accesos.index') }}" class="btn btn-outline-secondary">Volver</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="alert alert-light border">
            <strong>Qué hace el QR:</strong>
            <ul class="mb-0">
                <li>El cliente lo lleva impreso o en el celular.</li>
                <li>La caseta lo escanea → <code>/accesos/create?qr=PEN-2026-0002</code>.</li>
                <li>El formulario trae vehículo y método <strong>QR</strong> precargados
                    (el operador solo confirma con «Registrar entrada»).</li>
            </ul>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const select = document.getElementById('contrato');
        const caja   = document.getElementById('qrBox');

        select.addEventListener('change', () => {
            const numero = select.value;
            if (!numero) {
                caja.innerHTML = '<span class="text-muted">Selecciona un contrato para generar el código.</span>';
                return;
            }
            const url = location.origin + '/accesos/create?qr=' + encodeURIComponent(numero);
            caja.innerHTML = '<div class="d-flex flex-column align-items-center gap-2">'
                + '<div class="qr-render" data-qr="' + url.replace(/"/g, '&quot;') + '"></div>'
                + '<small class="text-muted">' + numero + '</small></div>';
            if (window.renderQr) {
                window.renderQr(caja.querySelector('.qr-render'));
            }
        });
    })();
</script>
@endpush
@endsection
