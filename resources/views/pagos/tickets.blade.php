@extends('layouts.app')
@section('title', 'Buscar ticket para cobrar')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Buscar ticket para cobrar</h4>
    <div class="d-flex gap-2">
        @can('contratos.crear')
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modalNuevaPension">+ Nueva pensión</button>
        @endcan
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label for="q" class="form-label">Folio o placa</label>
                <input type="text" id="q" name="q" value="{{ $q }}"
                       class="form-control form-control-lg" autofocus autocomplete="off"
                       placeholder="PEN-2026-0001 o ABC1234">
                <small class="text-muted">Escanea el código de barras del ticket, o escribe el folio / las placas.</small>
            </div>
            <div class="col-md-4">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="todas" value="1" id="todas" @checked($todas)>
                    <label class="form-check-label" for="todas">Incluir contratos ya cobrados</label>
                </div>
                <button class="btn btn-primary w-100">Buscar</button>
            </div>
        </form>
    </div>
</div>

@if ($q !== '')
    @if ($exacto)
        @php $cobrado = $exacto->pagos->where('estado', 'PAGADO')->count(); @endphp
        <div class="card shadow-sm border-success mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <h5 class="mb-1">
                            {{ $exacto->numero_contrato }}
                            <span class="badge {{ $exacto->estado_clase }}">{{ $exacto->estado_label }}</span>
                        </h5>
                        <p class="mb-1">
                            {{ $exacto->cliente?->nombre_completo ?? '—' }} ·
                            <strong>{{ $exacto->vehiculo?->placas ?? '—' }}</strong>
                        </p>
                        <p class="mb-0 small text-muted">
                            {{ trim(($exacto->vehiculo?->marca ?? '').' '.($exacto->vehiculo?->modelo ?? '')) ?: '—' }}
                            · Precio ${{ number_format((float) $exacto->precio, 2) }}
                            · Cobros registrados: {{ $cobrado }}
                        </p>
                    </div>
                    <div class="text-end d-flex flex-column gap-2">
                        @can('pagos.crear')
                            <button type="button" class="btn btn-success" data-modal="cobro"
                                    data-contrato-id="{{ $exacto->id }}"
                                    data-folio="{{ $exacto->numero_contrato }}">Cobrar</button>
                        @endcan
                        <a href="{{ route('contratos.ticket', $exacto) }}" target="_blank"
                           class="btn btn-outline-secondary btn-sm">Ver ticket</a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Folio</th><th>Cliente</th><th>Placas</th>
                    <th class="text-center">Estado</th><th class="text-center">Cobros</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($resultados as $c)
                    <tr>
                        <td class="fw-semibold">{{ $c->numero_contrato }}</td>
                        <td>{{ $c->cliente?->nombre_completo ?? '—' }}</td>
                        <td>{{ $c->vehiculo?->placas ?? '—' }}</td>
                        <td class="text-center">
                            <span class="badge {{ $c->estado_clase }}">{{ $c->estado_label }}</span>
                        </td>
                        <td class="text-center">{{ $c->pagos->where('estado', 'PAGADO')->count() }}</td>
                        <td class="text-end text-nowrap">
                            @can('pagos.crear')
                                <button type="button" class="btn btn-sm btn-success" data-modal="cobro"
                                        data-contrato-id="{{ $c->id }}"
                                        data-folio="{{ $c->numero_contrato }}">Cobrar</button>
                            @endcan
                            <a href="{{ route('contratos.ticket', $c) }}" target="_blank"
                               class="btn btn-sm btn-outline-secondary">Ticket</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">
                            No hay contratos por cobrar para «{{ $q }}».
                            @unless ($todas)
                                <a href="{{ route('pagos.tickets', ['q' => $q, 'todas' => 1]) }}">Buscar también ya cobrados</a>.
                            @endunless
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

@can('contratos.crear')
    @include('contratos._modal_nuevo')
@endcan

@can('pagos.crear')
    <div class="modal fade" id="modalCobro" tabindex="-1"
         aria-labelledby="modalCobroTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCobroTitle">Cobrar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="modalCobroBody"></div>
            </div>
        </div>
    </div>
    <button type="button" id="abrirModalCobro" class="d-none"
            data-bs-toggle="modal" data-bs-target="#modalCobro" aria-hidden="true"></button>
@endcan

@push('scripts')
<script>
    (function () {
        const modalEl   = document.getElementById('modalCobro');
        const body      = document.getElementById('modalCobroBody');
        const titulo    = document.getElementById('modalCobroTitle');
        const rutaBase = '{{ route('pagos.create.modal') }}';
        const rutaFinal = '{{ route('pagos.tickets') }}';

        if (!modalEl || !body) return;

        function limpiarErrores(form) {
            form.querySelectorAll('.invalid-feedback[data-dinamico]').forEach(el => el.remove());
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
        }

        function pintarErrores(form, errores) {
            const campos = Object.keys(errores);

            campos.forEach(campo => {
                const control = form.querySelector('[name="' + campo + '"]');
                if (!control) return;

                if (control.type === 'radio' || control.type === 'checkbox') {
                    form.querySelectorAll('[name="' + campo + '"]')
                        .forEach(el => el.classList.add('is-invalid'));
                } else {
                    control.classList.add('is-invalid');
                }
                control.setAttribute('aria-invalid', 'true');

                let ancla = control.closest('.input-group') || control;
                if (control.type === 'radio') {
                    ancla = control.closest('.d-flex') || control.parentElement;
                }

                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback d-block';
                feedback.setAttribute('role', 'alert');
                feedback.dataset.dinamico = '1';
                feedback.textContent = errores[campo][0] || '';
                ancla.parentNode.insertBefore(feedback, ancla.nextSibling);
            });

            const primero = form.querySelector('[name="' + campos[0] + '"]');
            if (primero) primero.focus();
        }

        // Carga el formulario del cobro y abre el modal (data-api de Bootstrap).
        document.querySelectorAll('[data-modal="cobro"]').forEach(boton => {
            boton.addEventListener('click', async function () {
                titulo.textContent = 'Cobrar ' + (boton.dataset.folio || 'pensión');
                body.innerHTML = '<div class="text-center py-4">'
                    + '<span class="spinner-border" role="status" aria-hidden="true"></span>'
                    + '</div>';

                let respuesta;
                try {
                    respuesta = await fetch(
                        rutaBase + '?contrato_id=' + encodeURIComponent(boton.dataset.contratoId)
                    );
                } catch (e) {
                    if (window.Swal) {
                        window.Swal.fire({ icon: 'error', title: 'Sin conexión',
                            text: 'No se pudo cargar el formulario de cobro.' });
                    }
                    return;
                }

                if (respuesta.status === 404 || respuesta.status === 422) {
                    const datos = await respuesta.json().catch(() => ({}));
                    if (window.Swal) {
                        window.Swal.fire({ icon: 'warning', title: 'No se puede cobrar',
                            text: datos.error || 'Revisa el contrato.' });
                    }
                    return;
                }

                body.innerHTML = await respuesta.text();

                // innerHTML no ejecuta <script>: se reinsertan a mano.
                body.querySelectorAll('script').forEach(viejo => {
                    const nuevo = document.createElement('script');
                    nuevo.textContent = viejo.textContent;
                    viejo.replaceWith(nuevo);
                });

                // Abre con el data-api usando un disparador oculto: el botón
                // «Cobrar» tiene su propio listener y un click sobre él mismo
                // reiniciaría la carga en bucle (y alternaría abrir/cerrar).
                document.getElementById('abrirModalCobro').click();
            });
        });

        // Envío del cobro por AJAX (el formulario vive dentro del modal).
        body.addEventListener('submit', async function (evento) {
            evento.preventDefault();
            const form = evento.target;
            const btnGuardar = form.querySelector('[data-guardar]');
            const textoOriginal = btnGuardar ? btnGuardar.innerHTML : '';

            limpiarErrores(form);
            if (btnGuardar) {
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Registrando…';
            }

            try {
                const respuesta = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const datos = await respuesta.json().catch(() => ({}));

                if (respuesta.status === 422) {
                    pintarErrores(form, datos.errors || {});
                    return;
                }

                if (!respuesta.ok) {
                    if (window.Swal) {
                        window.Swal.fire({ icon: 'error', title: 'No se pudo guardar',
                            text: datos.message || 'Ocurrió un error inesperado.' });
                    }
                    return;
                }

                // Éxito: vuelve al panel de buscar ticket, limpio.
                const btnCerrar = form.querySelector('[data-bs-dismiss="modal"]');
                if (btnCerrar) btnCerrar.click();
                window.location.assign(rutaFinal);
            } catch (e) {
                if (window.Swal) {
                    window.Swal.fire({ icon: 'error', title: 'Sin conexión',
                        text: 'No se pudo enviar el cobro. Inténtalo de nuevo.' });
                }
            } finally {
                if (btnGuardar) {
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = textoOriginal;
                }
            }
        });
    })();
</script>
@endpush
@endsection
