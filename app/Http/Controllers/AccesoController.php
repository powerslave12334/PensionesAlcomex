<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntradaRequest;
use App\Http\Requests\SalidaRequest;
use App\Models\ContratoPension;
use App\Models\Dispositivo;
use App\Models\Espacio;
use App\Models\MetodoPago;
use App\Models\MovimientoAcceso;
use App\Models\Puerta;
use App\Models\Tarifa;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccesoController extends Controller
{
    /* ---------------------------------------------------------------- *
     * US-0507 / US-0501 — listado
     * ---------------------------------------------------------------- */

    public function index(Request $request)
    {
        $this->authorize('accesos.ver');

        $request->validate([
            'q'            => ['nullable', 'string', 'max:100'],
            'tipo'         => ['nullable', 'in:' . implode(',', MovimientoAcceso::TIPOS)],
            'metodo'       => ['nullable', 'in:' . implode(',', MovimientoAcceso::METODOS)],
            'estado_cobro' => ['nullable', 'in:' . implode(',', MovimientoAcceso::ESTADOS_COBRO)],
            'desde'        => ['nullable', 'date'],
            'hasta'        => ['nullable', 'date', 'after_or_equal:desde'],
        ], [
            'hasta.after_or_equal' => 'La fecha «hasta» no puede ser anterior a «desde».',
        ]);

        $q           = $request->string('q')->toString() ?: null;
        $tipo        = $request->string('tipo')->toString() ?: null;
        $metodo      = $request->string('metodo')->toString() ?: null;
        $estadoCobro = $request->string('estado_cobro')->toString() ?: null;
        $desde       = $request->string('desde')->toString() ?: null;
        $hasta       = $request->string('hasta')->toString() ?: null;

        $consulta = MovimientoAcceso::filtrar($q, $tipo, $metodo, $desde, $hasta, $estadoCobro);

        $movimientos = (clone $consulta)
            ->with(['vehiculo', 'cliente', 'espacio', 'puerta', 'usuario',
                'salida:id,entrada_id,fecha_hora,permanencia_minutos'])
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $resumen = [
            'adentro'   => MovimientoAcceso::abiertas()->count(),
            'entradas'  => (clone $consulta)->where('tipo', 'ENTRADA')->whereDate('fecha_hora', now())->count(),
            'salidas'   => (clone $consulta)->where('tipo', 'SALIDA')->whereDate('fecha_hora', now())->count(),
            'porCobrar' => (clone $consulta)->where('estado_cobro', 'PENDIENTE')->sum('cobro'),
        ];

        return view('accesos.index', [
            'movimientos' => $movimientos,
            'resumen'     => $resumen,
            'filtros'     => [
                'q' => $q, 'tipo' => $tipo, 'metodo' => $metodo,
                'estado_cobro' => $estadoCobro, 'desde' => $desde, 'hasta' => $hasta,
            ],
            'tieneFiltro' => array_filter([$q, $tipo, $metodo, $estadoCobro, $desde, $hasta], fn ($v) => $v)
                !== [],
        ]);
    }

    /* ---------------------------------------------------------------- *
     * US-0501 — alta manual de entrada (y precarga QR, US-0503)
     * ---------------------------------------------------------------- */

    public function create(Request $request)
    {
        $this->authorize('accesos.registrar');

        $prefill = [
            'vehiculo_id'    => $request->filled('vehiculo_id') ? (int) $request->vehiculo_id : '',
            'metodo_acceso'  => $request->filled('qr') ? 'QR' : 'MANUAL',
            'fecha_hora'     => now()->format('Y-m-d\TH:i'),
            'placas_preview' => null,
            'contrato'       => null,
        ];

        // US-0503: el QR lleva el número de contrato; si coincide con uno ACTIVO
        // el formulario ya trae vehículo y método QR precargados.
        if ($request->filled('qr')) {
            $contrato = ContratoPension::with('vehiculo')
                ->where('numero_contrato', $request->string('qr')->toString())
                ->where('estado', 'ACTIVA')
                ->first();

            if (! $contrato) {
                return redirect()->route('accesos.create')->with(
                    'error',
                    'El código QR no corresponde a una pensión activa.'
                );
            }

            if (! $contrato->vehiculo?->activo) {
                return redirect()->route('accesos.create')->with(
                    'error',
                    'La pensión ' . $contrato->numero_contrato . ' tiene el vehículo dado de baja.'
                );
            }

            $prefill['vehiculo_id']    = $contrato->vehiculo_id;
            $prefill['contrato']       = $contrato;
            $prefill['placas_preview'] = $contrato->vehiculo->placas;
        }

        return view('accesos.create', $this->formEntrada($prefill));
    }

    public function store(EntradaRequest $request)
    {
        $this->authorize('accesos.registrar');

        $datos    = $request->validated();
        $vehiculo = Vehiculo::with('cliente')->findOrFail($datos['vehiculo_id']);
        $contrato = $this->contratoActivo($vehiculo);
        $entrada  = $this->crearEntrada($vehiculo, $contrato, $datos);

        return redirect()->route('accesos.show', $entrada)->with(
            'success',
            "Entrada registrada para {$vehiculo->placas} a las {$entrada->fecha_hora->format('H:i')} h."
        );
    }

    /* ---------------------------------------------------------------- *
     * US-0502 — salida, permanencia y cobro
     * ---------------------------------------------------------------- */

    public function salida(MovimientoAcceso $acceso)
    {
        $this->authorize('accesos.registrar');

        if ($acceso->tipo !== 'ENTRADA' || $acceso->salida()->exists()) {
            return redirect()->route('accesos.show', $acceso)
                ->with('error', 'Ese movimiento no es una entrada abierta.');
        }

        $acceso->load(['vehiculo', 'cliente', 'contrato', 'espacio']);

        $contrato = $this->contratoActivo($acceso->vehiculo);

        return view('accesos.salida', [
            'acceso'         => $acceso,
            'contrato'       => $contrato,
            'permanenciaMin' => (int) $acceso->fecha_hora->diffInMinutes(now()),
            'tarifas'        => $contrato ? collect() : $this->tarifasPermanencia($acceso->vehiculo),
            'tarifaSugerida' => $contrato ? null : $this->tarifaSugerida($acceso->vehiculo),
            'puertas'        => Puerta::activas()->where('tipo', 'SALIDA')->get(),
            'metodos'        => MetodoPago::activos()->get(),
        ]);
    }

    public function cerrarSalida(SalidaRequest $request, MovimientoAcceso $acceso)
    {
        $this->authorize('accesos.registrar');

        if ($acceso->tipo !== 'ENTRADA' || $acceso->salida()->exists()) {
            return redirect()->route('accesos.show', $acceso)
                ->with('error', 'Ese movimiento ya está cerrado.');
        }

        $datos = $request->validated();

        if (($datos['estado_cobro'] ?? null) === 'COBRADO') {
            $this->authorize('accesos.cobrar');
        }

        [$salida, $cobro] = $this->cerrarSalidaInterna($acceso, $datos);

        $mensaje = "Salida registrada para {$acceso->vehiculo?->placas}: "
            . $salida->permanencia . ' de permanencia.';

        if ($cobro > 0) {
            $mensaje .= $salida->estado_cobro === 'COBRADO'
                ? ' Cobrado $' . number_format($cobro, 2) . '.'
                : ' Por cobrar $' . number_format($cobro, 2) . '.';
        }

        return redirect()->route('accesos.show', $acceso)->with('success', $mensaje);
    }

    /* ---------------------------------------------------------------- *
     * US-0503 — QR del contrato
     * ---------------------------------------------------------------- */

    public function qr()
    {
        $this->authorize('accesos.ver');

        return view('accesos.qr', [
            'contratos' => ContratoPension::with('vehiculo', 'cliente')
                ->where('estado', 'ACTIVA')
                ->orderBy('numero_contrato')
                ->get(),
        ]);
    }

    /* ---------------------------------------------------------------- *
     * US-0504 — RFID (mock: el lector "entra" el UID)
     * ---------------------------------------------------------------- */

    public function rfid()
    {
        $this->authorize('accesos.ver');

        return view('accesos.rfid', [
            'vehiculos'  => Vehiculo::with('cliente', 'entradaAbierta')
                ->where('activo', true)
                ->orderBy('placas')
                ->get(),
            'movimientos' => MovimientoAcceso::with(['vehiculo', 'usuario'])
                ->orderByDesc('fecha_hora')->limit(10)->get(),
        ]);
    }

    public function leerRfid(Request $request)
    {
        $this->authorize('accesos.registrar');

        $datos = $request->validate([
            'uid' => ['required', 'string', 'max:40'],
        ], ['uid.required' => 'Escribe o captura el UID de la tarjeta.'], ['uid' => 'UID']);

        $vehiculo = Vehiculo::where('uid_rfid', trim($datos['uid']))->where('activo', true)->first();

        if (! $vehiculo) {
            return back()->withInput()->withErrors([
                'uid' => 'No hay ninguna tarjeta activa registrada con ese UID.',
            ]);
        }

        return $this->conmutar($vehiculo, 'RFID');
    }

    /* ---------------------------------------------------------------- *
     * US-0505 — LPR (mock: se captura la placa que "leyó" la cámara)
     * ---------------------------------------------------------------- */

    public function lpr()
    {
        $this->authorize('accesos.ver');

        return view('accesos.lpr', [
            'vehiculos'  => Vehiculo::where('activo', true)->orderBy('placas')->get(),
            'movimientos' => MovimientoAcceso::with(['vehiculo', 'usuario'])
                ->orderByDesc('fecha_hora')->limit(10)->get(),
        ]);
    }

    public function leerLpr(Request $request)
    {
        $this->authorize('accesos.registrar');

        $datos = $request->validate([
            'placas' => ['required', 'string', 'max:20'],
            'foto'   => ['nullable', 'string', 'max:500'],
        ], [
            'placas.required' => 'Captura las placas que detectó la cámara.',
        ], ['placas' => 'placas', 'foto' => 'foto']);

        $placas  = strtoupper(trim($datos['placas']));
        $vehiculo = Vehiculo::whereRaw('UPPER(placas) = ?', [$placas])
            ->where('activo', true)->first();

        if (! $vehiculo) {
            return back()->withInput()->withErrors([
                'placas' => "La placa {$placas} no está registrada en el sistema.",
            ]);
        }

        return $this->conmutar($vehiculo, 'LPR', [
            'foto_entrada' => trim((string) ($datos['foto'] ?? '')) ?: null,
        ]);
    }

    /* ---------------------------------------------------------------- *
     * US-0506 — dispositivos y barreras (mock de hardware)
     * ---------------------------------------------------------------- */

    public function dispositivos()
    {
        $this->authorize('accesos.configurar');

        return view('accesos.dispositivos', [
            'dispositivos' => Dispositivo::orderBy('tipo')->orderBy('nombre')->get(),
            'puertas'      => Puerta::with('dispositivo')->orderBy('id')->get(),
        ]);
    }

    public function barrera(Request $request)
    {
        $this->authorize('accesos.configurar');

        $datos = $request->validate([
            'dispositivo_id' => ['required', 'integer', Rule::exists('dispositivos', 'id')->where('tipo', 'BARRERA')],
            'accion'         => ['required', 'in:ABRIR,CERRAR'],
        ], [
            'dispositivo_id.required' => 'Selecciona la barrera.',
            'dispositivo_id.exists'   => 'Ese dispositivo no es una barrera.',
            'accion.required'         => 'Indica la acción.',
            'accion.in'               => 'La acción solo puede ser abrir o cerrar.',
        ], ['dispositivo_id' => 'barrera', 'accion' => 'acción']);

        $barrera = Dispositivo::findOrFail($datos['dispositivo_id']);

        $barrera->update([
            'configuracion'   => ['barrera' => $datos['accion'] === 'ABRIR' ? 'ABIERTA' : 'CERRADA'],
            'ultimo_contacto' => now(),
        ]);

        return back()->with(
            'success',
            "Barrera «{$barrera->nombre}» "
                . strtolower($datos['accion'] === 'ABRIR' ? 'abierta' : 'cerrada') . '.'
        );
    }

    /* ---------------------------------------------------------------- *
     * Detalle (US-0501 / US-0507)
     * ---------------------------------------------------------------- */

    public function show(MovimientoAcceso $acceso)
    {
        $this->authorize('accesos.ver');

        $acceso->load([
            'vehiculo.tipoVehiculo', 'cliente', 'contrato', 'espacio',
            'usuario', 'puerta', 'tarifa', 'metodoPago',
            'entrada.vehiculo', 'salida',
        ]);

        return view('accesos.show', ['acceso' => $acceso]);
    }

    /* ================================================================ *
     * Núcleo compartido
     * ================================================================ */

    /**
     * Entrada (manual, QR, RFID o LPR). Si el cajón estaba DISPONIBLE pasa a
     * OCUPADO; si es el cajón asignado al contrato se usa sin mover el estado.
     */
    private function crearEntrada(Vehiculo $vehiculo, ?ContratoPension $contrato, array $datos): MovimientoAcceso
    {
        return DB::transaction(function () use ($vehiculo, $contrato, $datos) {
            $espacio = null;

            if (! empty($datos['espacio_id'])) {
                $espacio = Espacio::lockForUpdate()->findOrFail($datos['espacio_id']);

                if ($espacio->estado === 'DISPONIBLE') {
                    $espacio->update(['estado' => 'OCUPADO']);
                }
            }

            return MovimientoAcceso::create([
                'vehiculo_id'         => $vehiculo->id,
                'cliente_id'          => $vehiculo->cliente_id ?? $contrato?->cliente_id,
                'contrato_pension_id' => $contrato?->id,
                'espacio_id'          => $espacio?->id,
                'tipo'                => 'ENTRADA',
                'fecha_hora'          => Carbon::parse($datos['fecha_hora']),
                'placas_detectadas'   => $vehiculo->placas,
                'usuario_id'          => Auth::id(),
                'puerta_id'           => $datos['puerta_id'] ?? null,
                'metodo_acceso'       => $datos['metodo_acceso'],
                'foto_entrada'        => $datos['foto_entrada'] ?? null,
                'observaciones'       => $datos['observaciones'] ?? null,
                'estado_cobro'        => 'NO_APLICA',
                'cobro'               => 0,
            ]);
        });
    }

    /**
     * Crea la fila SALIDA, calcula permanencia y cobro, y libera el cajón.
     * Devuelve [salida, cobro].
     */
    private function cerrarSalidaInterna(MovimientoAcceso $entrada, array $datos): array
    {
        return DB::transaction(function () use ($entrada, $datos) {
            $salidaFecha = Carbon::parse($datos['fecha_hora']);
            $minutos     = max(0, (int) $entrada->fecha_hora->diffInMinutes($salidaFecha));

            $contrato  = $this->contratoActivo($entrada->vehiculo);
            $visitante = $contrato === null;

            $cobro    = 0.0;
            $tarifaId = null;
            $estado   = 'NO_APLICA';
            $metodoId = null;

            if ($visitante) {
                $estado  = $datos['estado_cobro'] ?? 'PENDIENTE';
                $tarifa  = ! empty($datos['tarifa_id'])
                    ? Tarifa::find($datos['tarifa_id'])
                    : $this->tarifaSugerida($entrada->vehiculo);

                if ($estado === 'NO_APLICA') {
                    $cobro    = 0.0;
                    $tarifaId = null;
                } elseif ($tarifa) {
                    $cobro    = $this->calcularCobro($minutos, $tarifa);
                    $tarifaId = $tarifa->id;
                    $metodoId = $estado === 'COBRADO' ? ($datos['metodo_pago_id'] ?? null) : null;

                    if ($estado === 'COBRADO' && ! $metodoId) {
                        throw ValidationException::withMessages([
                            'metodo_pago_id' => 'Selecciona con qué método se cobró.',
                        ]);
                    }
                } else {
                    $estado = 'PENDIENTE';
                }
            }

            $salida = MovimientoAcceso::create([
                'entrada_id'          => $entrada->id,
                'vehiculo_id'         => $entrada->vehiculo_id,
                'cliente_id'          => $entrada->cliente_id,
                'contrato_pension_id' => $entrada->contrato_pension_id,
                'espacio_id'          => $entrada->espacio_id,
                'tipo'                => 'SALIDA',
                'fecha_hora'          => $salidaFecha,
                'placas_detectadas'   => $entrada->placas_detectadas,
                'usuario_id'          => Auth::id(),
                'puerta_id'           => $datos['puerta_id'] ?? null,
                'metodo_acceso'       => $datos['metodo_acceso'] ?? 'MANUAL',
                'permanencia_minutos' => $minutos,
                'tarifa_id'           => $tarifaId,
                'cobro'               => $cobro,
                'estado_cobro'        => $estado,
                'metodo_pago_id'      => $metodoId,
                'foto_salida'         => $datos['foto_salida'] ?? null,
                'observaciones'       => $datos['observaciones'] ?? null,
            ]);

            $this->liberarCajon($entrada);

            return [$salida, $cobro];
        });
    }

    /**
     * RFID/LPR: si el vehículo está fuera entra, si está adentro sale.
     * Es el comportamiento de un torniquete real (mock del sprint).
     */
    private function conmutar(Vehiculo $vehiculo, string $metodo, array $extra = [])
    {
        $abierta = MovimientoAcceso::abiertas()
            ->where('vehiculo_id', $vehiculo->id)
            ->orderByDesc('fecha_hora')
            ->first();

        if ($abierta) {
            $contrato = $this->contratoActivo($vehiculo);

            [$salida, $cobro] = $this->cerrarSalidaInterna($abierta, [
                'fecha_hora'    => now(),
                'metodo_acceso' => $metodo,
                'estado_cobro'  => $contrato ? 'NO_APLICA' : 'PENDIENTE',
                'puerta_id'     => Puerta::activas()->where('tipo', 'SALIDA')->value('id'),
                'foto_salida'   => $extra['foto_salida'] ?? $extra['foto_entrada'] ?? null,
            ]);

            $mensaje = "Salida {$metodo} de {$vehiculo->placas}: {$salida->permanencia}.";
            if ($cobro > 0) {
                $mensaje .= ' Por cobrar $' . number_format($cobro, 2) . '.';
            }

            return redirect()->route('accesos.show', $abierta)->with('success', $mensaje);
        }

        $entrada = $this->crearEntrada($vehiculo, $this->contratoActivo($vehiculo), [
            'fecha_hora'    => now(),
            'metodo_acceso' => $metodo,
            'puerta_id'     => Puerta::activas()->where('tipo', 'ENTRADA')->value('id'),
            'foto_entrada'  => $extra['foto_entrada'] ?? null,
        ]);

        return redirect()->route('accesos.show', $entrada)->with(
            'success',
            "Entrada {$metodo} de {$vehiculo->placas} a las {$entrada->fecha_hora->format('H:i')} h."
        );
    }

    /** Contrato ACTIVA vigente (fecha_inicio ≤ hoy ≤ fecha_fin). */
    private function contratoActivo(Vehiculo $vehiculo): ?ContratoPension
    {
        return $vehiculo->contratos()
            ->where('estado', 'ACTIVA')
            ->whereDate('fecha_inicio', '<=', now())
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', now()))
            ->first();
    }

    /** Tarifa por defecto para visitantes: HORA del tipo, si no, cualquier HORA vigente. */
    private function tarifaSugerida(Vehiculo $vehiculo): ?Tarifa
    {
        $base = Tarifa::vigente()->whereIn('tipo_tarifa', ['HORA', 'DIA', 'NOCHE'])
            ->orderByRaw("FIELD(tipo_tarifa, 'HORA', 'DIA', 'NOCHE')");

        return (clone $base)->where('tipo_vehiculo_id', $vehiculo->tipo_vehiculo_id)->first()
            ?? (clone $base)->first();
    }

    /** Tarifas de permanencia vigentes para el <select> de la salida. */
    private function tarifasPermanencia(Vehiculo $vehiculo): \Illuminate\Support\Collection
    {
        return Tarifa::vigente()
            ->whereIn('tipo_tarifa', ['HORA', 'DIA', 'NOCHE'])
            ->with('tipoVehiculo')
            ->orderByRaw("FIELD(tipo_tarifa, 'HORA', 'DIA', 'NOCHE')")
            ->orderBy('tipo_vehiculo_id')
            ->get()
            ->sortBy(fn ($t) => $t->tipo_vehiculo_id === $vehiculo->tipo_vehiculo_id ? 0 : 1)
            ->values();
    }

    /**
     * Bloques de cobro: 60 min (HORA), 720 min (NOCHE = 12 h), 1440 min (DIA).
     * Fracción incompleta se cobra completa, mínimo 1 unidad.
     */
    private function calcularCobro(int $minutos, Tarifa $tarifa): float
    {
        $bloque = ['HORA' => 60, 'NOCHE' => 720, 'DIA' => 1440][$tarifa->tipo_tarifa] ?? 60;

        $unidades = max(1, (int) ceil($minutos / $bloque));

        return round($unidades * (float) $tarifa->precio, 2);
    }

    /** Devuelve el cajón a DISPONIBLE si nadie más lo tiene tomado. */
    private function liberarCajon(MovimientoAcceso $entrada): void
    {
        if (! $entrada->espacio_id) {
            return;
        }

        $espacio = Espacio::lockForUpdate()->find($entrada->espacio_id);

        if (! $espacio || $espacio->estado !== 'OCUPADO') {
            return;
        }

        $asignado = $espacio->asignaciones()->whereNull('fecha_fin')->exists();
        $otroAdentro = MovimientoAcceso::abiertas()
            ->where('espacio_id', $espacio->id)
            ->where('id', '!=', $entrada->id)
            ->exists();

        if (! $asignado && ! $otroAdentro) {
            $espacio->update(['estado' => 'DISPONIBLE']);
        }
    }

    /** Datos comunes del formulario de entrada. */
    private function formEntrada(array $prefill = []): array
    {
        return [
            'vehiculos' => Vehiculo::with('cliente', 'tipoVehiculo')
                ->where('activo', true)
                ->orderBy('placas')
                ->get(),
            'espacios' => Espacio::where('activo', true)
                ->whereIn('estado', ['DISPONIBLE'])
                ->orderBy('codigo')
                ->get(),
            // Cajones tomados por una pensión vigente: se ofrecen aunque estén
            // OCUPADOS (la validación exige que sean del contrato del vehículo).
            'espaciosContrato' => Espacio::whereHas('asignaciones', function ($q) {
                $q->whereNull('fecha_fin')->whereHas('contrato', function ($qq) {
                    $qq->where('estado', 'ACTIVA');
                });
            })->with('zona')->orderBy('codigo')->get(),
            'puertas' => Puerta::activas()->where('tipo', 'ENTRADA')->get(),
            'prefill' => $prefill,
        ];
    }
}
