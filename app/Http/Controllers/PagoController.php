<?php

namespace App\Http\Controllers;

use App\Http\Requests\PagoRequest;
use App\Models\ContratoPension;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Tarifa;
use App\Models\TipoVehiculo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    /** US-0404: historial con filtros y resumen del mismo filtro. */
    public function index(Request $request)
    {
        $this->authorize('pagos.ver');

        $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'estado'   => ['nullable', 'in:' . implode(',', Pago::ESTADOS)],
            'metodo'   => ['nullable', 'integer', 'exists:metodos_pago,id'],
            'contrato' => ['nullable', 'integer', 'exists:contratos_pension,id'],
            'cliente'  => ['nullable', 'integer', 'exists:clientes,id'],
            'desde'    => ['nullable', 'date'],
            'hasta'    => ['nullable', 'date', 'after_or_equal:desde'],
        ], [], [
            'q' => 'búsqueda', 'estado' => 'estado', 'metodo' => 'método',
            'contrato' => 'contrato', 'cliente' => 'cliente',
            'desde' => 'desde', 'hasta' => 'hasta',
        ]);

        $q         = $request->string('q')->toString() ?: null;
        $estado    = $request->string('estado')->toString() ?: null;
        $metodo    = $request->filled('metodo') ? (int) $request->metodo : null;
        $contrato  = $request->filled('contrato') ? (int) $request->contrato : null;
        $cliente   = $request->filled('cliente') ? (int) $request->cliente : null;
        $desde     = $request->string('desde')->toString() ?: null;
        $hasta     = $request->string('hasta')->toString() ?: null;

        $consulta = Pago::filtrar($q, $estado, $metodo, $contrato, $cliente, $desde, $hasta);

        $pagos = (clone $consulta)
            ->with(['contrato', 'cliente', 'metodoPago'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Resumen con exactamente los mismos filtros (solo lo cobrado)
        $resumen = (clone $consulta)
            ->where('estado', 'PAGADO')
            ->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total')
            ->first();

        $filtros = compact('q', 'estado', 'metodo', 'contrato', 'cliente', 'desde', 'hasta');

        return view('pagos.index', [
            'pagos'    => $pagos,
            'resumen'  => $resumen,
            'filtros'  => $filtros,
            'tieneFiltro' => array_filter($filtros, fn ($v) => $v !== null && $v !== '') !== [],
            'contratos' => ContratoPension::orderBy('numero_contrato')->get(['id', 'numero_contrato']),
            'clientes'  => \App\Models\Cliente::orderBy('nombre')->get(['id', 'nombre', 'apellido_paterno']),
            'metodos'   => MetodoPago::activos()->get(),
        ]);
    }

    /** US-0401: alta de cobro (con precarga desde el expediente, US-0407). */
    public function create(Request $request)
    {
        $this->authorize('pagos.crear');

        $contrato = null;
        $prefill  = ['periodo_inicio' => now()->toDateString(), 'periodo_fin' => '', 'subtotal' => ''];

        if ($request->filled('contrato_id')) {
            $contrato = ContratoPension::find((int) $request->contrato_id);

            if (! $contrato) {
                return redirect()->route('pagos.create')->with('error', 'Ese contrato no existe.');
            }

            if (in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
                return redirect()->route('pagos.create')->with(
                    'error',
                    "El contrato {$contrato->numero_contrato} está cerrado: no se le puede cobrar."
                );
            }

            [$prefill['periodo_inicio'], $prefill['periodo_fin']] = $this->periodoSugerido($contrato);

            // Subtotal = mensualidad × meses naturales del periodo sugerido.
            $mensualidad = (float) $contrato->precio
                / (ContratoPension::MESES[$contrato->periodicidad] ?? 1);
            $meses = Pago::calcularMeses(
                Carbon::parse($prefill['periodo_inicio']),
                Carbon::parse($prefill['periodo_fin'])
            );
            $prefill['subtotal'] = number_format(round($mensualidad * $meses, 2), 2, '.', '');
        }

        return view('pagos.create', array_merge($this->combos(), compact('contrato', 'prefill')));
    }

    /** Formulario de cobro ya llenado para un contrato (modal vía AJAX). */
    public function createModal(Request $request)
    {
        $this->authorize('pagos.crear');

        $contrato = ContratoPension::with(['cliente', 'vehiculo'])
            ->find((int) $request->query('contrato_id'));

        if (! $contrato) {
            return response()->json(
                ['error' => 'Ese contrato no existe.'],
                404
            );
        }

        if (in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
            return response()->json(
                ['error' => "El contrato {$contrato->numero_contrato} está cerrado: no se le puede cobrar."],
                422
            );
        }

        // El periodo lo define el servidor: desde el inicio de la pensión
        // hasta el día del pago (hoy).
        $inicio     = $contrato->fecha_inicio;
        $mensualidad = (float) $contrato->precio
            / (ContratoPension::MESES[$contrato->periodicidad] ?? 1);
        $meses      = Pago::calcularMeses($inicio, now());
        $prefill    = [
            'periodo_inicio' => $inicio->toDateString(),
            'subtotal'       => number_format(round($mensualidad * $meses, 2), 2, '.', ''),
        ];

        return view('pagos._modal_cobro', array_merge(
            $this->combos(),
            compact('contrato', 'prefill')
        ));
    }

    /** US-0401 + US-0402: guarda el cobro con folio único y totales del servidor. */
    public function store(PagoRequest $request)
    {
        $this->authorize('pagos.crear');

        $datos = $request->validated();

        $contrato = ContratoPension::findOrFail($datos['contrato_pension_id']);

        $datos['cliente_id']  = $contrato->cliente_id;
        $datos['usuario_id']  = Auth::id();
        $datos['fecha_pago']  = Carbon::parse($datos['fecha_pago']);
        $datos['periodo_fin'] = $datos['fecha_pago']; // el periodo cierra el día del pago
        $datos['estado']      = 'PAGADO'; // el cobro del modal siempre es «Pagado»
        $datos['descuento']   = (float) ($datos['descuento'] ?? 0);
        $datos['impuesto']    = (float) ($datos['impuesto'] ?? 0);
        $datos['subtotal']    = (float) $datos['subtotal'];
        $datos['total']       = round($datos['subtotal'] - $datos['descuento'] + $datos['impuesto'], 2);
        $datos['referencia']  = trim((string) ($datos['referencia'] ?? '')) ?: null;
        $datos['observaciones'] = $request->string('observaciones')->toString() ?: null;

        if ($datos['total'] < 0) {
            return back()->withInput()->withErrors(['descuento' => 'El descuento no puede superar el subtotal.']);
        }

        $pago = $this->crearPago($datos);

        // Al cobrar, la pensión pasa de PENDIENTE a ACTIVA (y el cajón a OCUPADO).
        if ($contrato->estado === 'PENDIENTE') {
            $contrato->update(['estado' => 'ACTIVA']);
            $this->sincronizarEspacio($contrato);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'message'  => 'Recibo ' . $pago->numero_recibo . ' registrado por $'
                    . number_format((float) $pago->total, 2) . '.',
                'redirect' => route('pagos.show', $pago),
            ]);
        }

        return redirect()->route('pagos.show', $pago)->with(
            'success',
            'Recibo ' . $pago->numero_recibo . ' registrado por $'
                . number_format((float) $pago->total, 2) . '.'
        );
    }

    /**
     * Búsqueda del ticket a cobrar por folio o por placa.
     * Por defecto solo muestra contratos «no cobrados» (sin pagos en estado
     * PAGADO) y que no estén cancelados/finalizados. Al escanear el código de
     * barras del ticket, el folio llega a este buscador.
     */
    public function tickets(Request $request)
    {
        $this->authorize('pagos.ver');

        $request->validate([
            'q'     => ['nullable', 'string', 'max:100'],
            'todas' => ['nullable', 'boolean'],
        ], [], ['q' => 'búsqueda', 'todas' => 'incluir cobrados']);

        $q     = trim($request->string('q')->toString());
        $todas = $request->boolean('todas');

        $resultados = collect();
        $exacto     = null;

        if ($q !== '') {
            $consulta = ContratoPension::query()
                ->whereNotIn('estado', ['CANCELADA', 'FINALIZADA'])
                ->where(function ($w) use ($q) {
                    $w->where('numero_contrato', 'like', "%{$q}%")
                        ->orWhereHas('vehiculo', fn ($v) => $v->where('placas', 'like', "%{$q}%"));
                });

            if (! $todas) {
                // «no cobrada»: sin ningún pago en estado PAGADO.
                $consulta->whereDoesntHave('pagos', fn ($p) => $p->where('estado', 'PAGADO'));
            }

            $resultados = $consulta
                ->with(['cliente', 'vehiculo.tipoVehiculo', 'pagos'])
                ->orderByDesc('fecha_inicio')
                ->limit(25)
                ->get();

            // Coincidencia exacta por folio o placa → cobro directo.
            $texto = mb_strtoupper($q);
            $exacto = $resultados->first(fn ($c) => mb_strtoupper($c->numero_contrato) === $texto)
                ?? $resultados->first(fn ($c) => mb_strtoupper((string) $c->vehiculo?->placas) === $texto);
        }

        // Combos para el modal de «Nueva pensión» (mismos criterios del
        // formulario: solo tipos activos y tarifas vigentes).
        return view('pagos.tickets', compact('q', 'todas', 'resultados', 'exacto') + [
            'tiposVehiculo' => TipoVehiculo::where('activo', true)->orderBy('nombre')->get(),
            'tarifas'       => Tarifa::vigente()->with('tipoVehiculo')
                            ->orderBy('tipo_tarifa')->orderBy('nombre')->get(),
            'contrato'      => new ContratoPension(),
        ]);
    }

    /** US-0403: recibo imprimible («Guardar como PDF» desde el diálogo de impresión). */
    public function show(Pago $pago)
    {
        $this->authorize('pagos.ver');

        $pago->load(['contrato.vehiculo', 'cliente', 'metodoPago', 'usuario']);

        return view('pagos.show', compact('pago'));
    }

    /** US-0405: corte de caja del día (informe, sin tabla nueva). */
    public function corte(Request $request)
    {
        $this->authorize('pagos.corte');

        $request->validate(['fecha' => ['nullable', 'date']], [
            'fecha.date' => 'La fecha del corte no es válida.',
        ]);

        $fecha = $request->date('fecha') ?? now();

        $pagos = Pago::delDia($fecha->toDateString())
            ->with(['metodoPago', 'cliente', 'contrato'])
            ->orderBy('id')
            ->get();

        $cobrados   = $pagos->where('estado', 'PAGADO');
        $porMetodo  = $cobrados
            ->groupBy('metodo_pago_id')
            ->map(fn ($grupo) => [
                'metodo'    => $grupo->first()->metodoPago?->nombre ?? '—',
                'cantidad'  => $grupo->count(),
                'subtotal'  => round($grupo->sum('subtotal'), 2),
                'descuento' => round($grupo->sum('descuento'), 2),
                'impuesto'  => round($grupo->sum('impuesto'), 2),
                'total'     => round($grupo->sum('total'), 2),
            ])
            ->sortBy('metodo')
            ->values();

        $totales = [
            'cantidad'  => $cobrados->count(),
            'subtotal'  => round($cobrados->sum('subtotal'), 2),
            'descuento' => round($cobrados->sum('descuento'), 2),
            'impuesto'  => round($cobrados->sum('impuesto'), 2),
            'total'     => round($cobrados->sum('total'), 2),
        ];

        $excluidos = $pagos->where('estado', '!=', 'PAGADO');

        return view('pagos.corte', compact('fecha', 'porMetodo', 'totales', 'cobrados', 'excluidos'));
    }

    /** US-0406: cancelar o reembolsar con motivo (el registro nunca se borra). */
    public function estado(Request $request, Pago $pago)
    {
        $this->authorize('pagos.cancelar');

        $permitidas = $pago->transicionesPermitidas();

        $datos = $request->validate([
            'accion' => ['required', 'in:' . implode(',', $permitidas ?: ['__ninguno__'])],
            'motivo' => ['required', 'string', 'max:300'],
        ], [
            'accion.in'   => 'Ese movimiento no está permitido desde «'
                . Pago::ESTADO_LABELS[$pago->estado] . '».',
            'motivo.required' => 'Escribe el motivo del movimiento.',
            'motivo.string'   => 'El motivo debe ser texto.',
        ], [
            'accion' => 'movimiento',
            'motivo' => 'motivo',
        ]);

        DB::transaction(function () use ($pago, $datos) {
            $nota = '[' . now()->format('d/m/Y H:i') . '] '
                . Pago::ESTADO_LABELS[$datos['accion']] . ': ' . $datos['motivo'];

            $pago->update([
                'estado'        => $datos['accion'],
                'observaciones' => trim(($pago->observaciones ? $pago->observaciones . "\n" : '') . $nota),
            ]);
        });

        return redirect()->route('pagos.show', $pago)->with(
            'success',
            "Recibo {$pago->numero_recibo}: " . strtolower(Pago::ESTADO_LABELS[$datos['accion']]) . '.'
        );
    }

    /** Combos de los formularios. */
    private function combos(): array
    {
        return [
            'contratos' => ContratoPension::with(['cliente', 'vehiculo'])
                ->whereNotIn('estado', ['CANCELADA', 'FINALIZADA'])
                ->orderBy('numero_contrato')
                ->get(),
            'metodos' => MetodoPago::activos()->get(),
        ];
    }

    /**
     * Periodo que sigue al último cobro (o el del contrato si es el primero),
     * acotado a la vigencia. Ej.: PEN-2026-0002 MENSUAL, sin pagos, inicia
     * 07/10/2026 → 07/10/2026 – 06/11/2026.
     */
    private function periodoSugerido(ContratoPension $contrato): array
    {
        $ultima = $contrato->pagos()
            ->whereNotNull('periodo_fin')
            ->orderByDesc('periodo_fin')
            ->first();

        $inicio = $ultima ? $ultima->periodo_fin->copy()->addDay() : $contrato->fecha_inicio->copy();
        $meses  = ContratoPension::MESES[$contrato->periodicidad] ?? 1;
        $fin    = $inicio->copy()->addMonthsNoOverflow($meses)->subDay();

        if ($contrato->fecha_fin && $fin->greaterThan($contrato->fecha_fin)) {
            $fin = $contrato->fecha_fin->copy();
        }

        if ($contrato->fecha_fin && $inicio->greaterThan($contrato->fecha_fin)) {
            $inicio = $contrato->fecha_fin->copy();
            $fin    = $contrato->fecha_fin->copy();
        }

        return [$inicio->toDateString(), $fin->toDateString()];
    }

    /**
     * Crea el recibo con folio autogenerado. Si dos cobros simultáneos chocan
     * por el UNIQUE de `numero_recibo`, reintenta hasta 3 veces.
     */
    private function crearPago(array $datos): Pago
    {
        $intentos = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($datos) {
                    $datos['numero_recibo'] = $this->generarFolio();
                    $datos['estado']        = $datos['estado'] ?? 'PAGADO';

                    return Pago::create($datos);
                });
            } catch (QueryException $e) {
                $intentos++;
                $choque = str_contains($e->getMessage(), 'Duplicate')
                    || (int) ($e->errorInfo[1] ?? 0) === 1062;

                if (! $choque || $intentos >= 3) {
                    throw $e;
                }
            }
        }
    }

    /** Folio consecutivo por año: REC-2026-0001, REC-2026-0002, ... */
    private function generarFolio(): string
    {
        $patron = 'REC-' . now()->format('Y') . '-%';

        $ultima = Pago::where('numero_recibo', 'like', $patron)
            ->orderByRaw('CAST(SUBSTRING(numero_recibo, 10) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->value('numero_recibo');

        $secuencia = $ultima ? ((int) substr($ultima, -4)) + 1 : 1;

        return sprintf('REC-%s-%04d', now()->format('Y'), $secuencia);
    }

    /**
     * Sincroniza el estado del cajón con el contrato
     * (mismo match que ContratoPensionController::sincronizarEspacio).
     */
    private function sincronizarEspacio(ContratoPension $contrato): void
    {
        $contrato->loadMissing('asignacionVigiente.espacio');
        $espacio = $contrato->asignacionVigiente?->espacio;

        if (! $espacio) {
            return;
        }

        $estado = match ($contrato->estado) {
            'ACTIVA'                               => 'OCUPADO',
            'PENDIENTE', 'SUSPENDIDA', 'VENCIDA'   => 'RESERVADO',
            default                                => 'DISPONIBLE',
        };

        if ($espacio->estado !== $estado) {
            $espacio->update(['estado' => $estado]);
        }
    }
}
