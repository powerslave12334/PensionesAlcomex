<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $contrato->numero_contrato }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        body {
            width: 80mm;
            margin: 0 auto;
            padding: 3mm 3mm 8mm;
            font-family: "Courier New", ui-monospace, Consolas, monospace;
            font-size: 11px;
            line-height: 1.4;
        }
        .center { text-align: center; }
        .title { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        td.k { width: 40%; white-space: nowrap; }
        td.v { text-align: right; font-weight: bold; word-break: break-word; }
        .folio { font-size: 14px; font-weight: bold; letter-spacing: 1px; }
        .barcode { margin: 4px auto 2px; text-align: center; }
        .barcode svg { max-width: 100%; height: auto; }
        .code { font-size: 12px; letter-spacing: 3px; }
        .small { font-size: 10px; }
        .noprint { margin-top: 12px; text-align: center; }
        .noprint button {
            padding: 8px 16px; font-size: 13px; cursor: pointer;
            border: 1px solid #333; border-radius: 4px; background: #f2f2f2;
        }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="center title">PENSIONES VEHICULARES</div>
    <div class="center small">Comprobante de alta de pensión</div>
    <div class="sep"></div>

    <table>
        <tr><td class="k">Folio</td><td class="v folio">{{ $contrato->numero_contrato }}</td></tr>
        <tr><td class="k">Estado</td><td class="v">{{ $contrato->estado_label }}</td></tr>
        <tr>
            <td class="k">Alta</td>
            <td class="v">{{ $contrato->fecha_entrada?->format('d/m/Y H:i') ?? $contrato->created_at?->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <div class="sep"></div>
    <div class="small"><b>CLIENTE</b></div>
    <div>{{ $contrato->cliente?->nombre_completo ?? '—' }}</div>
    <div class="small">Tel: {{ $contrato->cliente?->telefono ?: '—' }}</div>

    <div class="sep"></div>
    <div class="small"><b>VEHÍCULO</b></div>
    <table>
        <tr><td class="k">Placas</td><td class="v">{{ $contrato->vehiculo?->placas ?? '—' }}</td></tr>
        <tr>
            <td class="k">Unidad</td>
            <td class="v">{{ trim(($contrato->vehiculo?->marca ?? '').' '.($contrato->vehiculo?->modelo ?? '')) ?: '—' }}</td>
        </tr>
        <tr><td class="k">Tipo</td><td class="v">{{ $contrato->vehiculo?->tipoVehiculo?->nombre ?? '—' }}</td></tr>
    </table>

    <div class="sep"></div>
    <table>
        <tr>
            <td class="k">Tarifa</td>
            <td class="v">{{ $contrato->tarifa?->etiqueta ?? '—' }}</td>
        </tr>
        <tr><td class="k">Inicio</td><td class="v">{{ $contrato->fecha_inicio?->format('d/m/Y') }}</td></tr>
        <tr><td class="k">Precio</td><td class="v">${{ number_format((float) $contrato->precio, 2) }}</td></tr>
        <tr><td class="k">Depósito</td><td class="v">${{ number_format((float) $contrato->deposito, 2) }}</td></tr>
        @if ($contrato->asignacionVigiente?->espacio)
            <tr><td class="k">Cajón</td><td class="v">{{ $contrato->asignacionVigiente->espacio->codigo }}</td></tr>
        @endif
    </table>

    <div class="sep"></div>
    <div class="barcode center">{!! $barcodeSvg !!}</div>
    <div class="center code">{{ $contrato->numero_contrato }}</div>
    <div class="sep"></div>
    <div class="center small">Presenta este ticket para el cobro.</div>
    <div class="center small">Conserve su comprobante.</div>
    <div class="center small" style="margin-top:6px;">¡Gracias por su preferencia!</div>

    <div class="noprint">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    @if (request()->boolean('auto'))
        <script>
            // Solo imprime cuando se pide explícitamente (alta o botón «Imprimir ticket»).
            window.addEventListener('load', function () {
                setTimeout(function () { try { window.print(); } catch (e) {} }, 250);
            });
        </script>
    @endif
</body>
</html>
