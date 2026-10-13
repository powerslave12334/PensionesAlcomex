{{--
    Modal de «Nueva pensión» (US-0303). Se renderiza del lado servidor porque
    el formulario (_form) trae su propio <script>: si se inyectara con AJAX /
    innerHTML esos scripts no se ejecutarían (auto-precio de tarifa y
    autocompletado de placas dejarían de funcionar).

    Requiere: $tiposVehiculo, $tarifas (combos) y $contrato (instancia vacía).
--}}
<div class="modal fade" id="modalNuevaPension" tabindex="-1"
     aria-labelledby="modalNuevaPensionLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form action="{{ route('contratos.store') }}" method="POST" id="formNuevaPension">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNuevaPensionLabel">Nueva pensión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @include('contratos._form')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" data-guardar>Guardar pensión</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const modalEl = document.getElementById('modalNuevaPension');
        const form    = document.getElementById('formNuevaPension');
        if (!modalEl || !form) return;

        const btnGuardar     = form.querySelector('[data-guardar]');
        const textoGuardar   = btnGuardar ? btnGuardar.innerHTML : '';

        // Nota: la apertura usa el data-api de Bootstrap
        // (data-bs-toggle="modal" en el botón) porque Vite importa Bootstrap
        // como módulo y no expone el global `bootstrap`.

        function limpiarErrores() {
            form.querySelectorAll('.invalid-feedback[data-dinamico]').forEach(function (el) {
                el.remove();
            });
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
            form.querySelectorAll('[aria-invalid]').forEach(function (el) {
                el.removeAttribute('aria-invalid');
            });
        }

        function pintarErrores(errores) {
            const campos = Object.keys(errores);

            campos.forEach(function (campo) {
                const control = form.querySelector('[name="' + campo + '"]');
                if (!control) return;

                // Radios y checkboxes: el error se marca en todo el grupo.
                if (control.type === 'radio' || control.type === 'checkbox') {
                    form.querySelectorAll('[name="' + campo + '"]').forEach(function (el) {
                        el.classList.add('is-invalid');
                    });
                } else {
                    control.classList.add('is-invalid');
                }
                control.setAttribute('aria-invalid', 'true');

                // El mensaje va debajo del control (o debajo de su .input-group).
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

        form.addEventListener('submit', async function (evento) {
            evento.preventDefault();
            limpiarErrores();

            if (btnGuardar) {
                btnGuardar.disabled = true;
                btnGuardar.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando…';
            }

            // La pestaña del ticket se abre ANTES del envío: después del await
            // el navegador podría bloquear window.open y no se imprimiría.
            const ventanaTicket = window.open('', '_blank');

            try {
                const respuesta = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const datos = await respuesta.json().catch(function () { return {}; });

                if (respuesta.status === 422) {
                    if (ventanaTicket) ventanaTicket.close();
                    pintarErrores(datos.errors || {});
                    return;
                }

                if (!respuesta.ok) {
                    if (ventanaTicket) ventanaTicket.close();
                    if (window.Swal) {
                        window.Swal.fire({
                            icon: 'error',
                            title: 'No se pudo guardar',
                            text: datos.message || 'Ocurrió un error inesperado.'
                        });
                    }
                    return;
                }

                // Éxito: se imprime el ticket y el panel queda limpio para cobrar.
                if (datos.ticket_url) {
                    if (ventanaTicket) {
                        ventanaTicket.location.href = datos.ticket_url;
                    } else {
                        window.open(datos.ticket_url, '_blank');
                    }
                } else if (ventanaTicket) {
                    ventanaTicket.close();
                }

                // Se cierra con el data-api (click en el botón de cerrar).
                const btnCerrar = modalEl.querySelector('[data-bs-dismiss="modal"]');
                if (btnCerrar) btnCerrar.click();

                limpiarErrores();
                form.reset();
                window.location.assign(window.location.pathname);
            } catch (error) {
                if (ventanaTicket) ventanaTicket.close();
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'error',
                        title: 'Sin conexión',
                        text: 'No se pudo enviar el formulario. Revisa tu conexión e inténtalo de nuevo.'
                    });
                }
            } finally {
                if (btnGuardar) {
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = textoGuardar;
                }
            }
        });
    })();
</script>
@endpush
