import './bootstrap';
import Swal from 'sweetalert2';
import qrcode from 'qrcode-generator';

window.Swal = Swal;

/**
 * Dibuja el QR del `data-qr` dentro del elemento.
 * Si la librería no está instalada o el texto es demasiado largo para el
 * tipo de versión elegido, deja el texto plano (la vista sigue siendo usable).
 */
window.renderQr = function (el) {
    if (!el) return;

    const texto = el.dataset.qr || '';
    if (!texto) return;

    const dibujar = (tipo) => {
        const qr = qrcode(tipo, 'M');
        qr.addData(texto);
        qr.make();
        return qr.createSvgTag(4, 2);
    };

    try {
        el.innerHTML = dibujar(0);   // 0 = autodetección de versión
    } catch (e1) {
        try {
            el.innerHTML = dibujar(10); // versión fija (≈174 bytes en nivel M)
        } catch (e2) {
            el.textContent = texto;     // degradación: URL visible
        }
    }
};
