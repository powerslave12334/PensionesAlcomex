<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContratoPensionRequest;
use App\Models\AsignacionEspacio;
use App\Models\Cliente;
use App\Models\ContratoPension;
use App\Models\Espacio;
use App\Models\Tarifa;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use App\Support\Barcode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratoPensionController extends Controller
{
    /** US-0306: listado con filtros. */
    public function index(Request $request)
    {
        $this->authorize('contratos.ver');

        $request->validate([
            'q'          => ['nullable', 'string', 'max:100'],
            'estado'     => ['nullable', 'in:' . implode(',', ContratoPension::ESTADOS)],
            'por_vencer' => ['nullable', 'boolean'],
        ]);

        $q              = $request->string('q')->toString() ?: null;
        $estado         = $request->string('estado')->toString() ?: null;
        $filtroPorVencer = $request->boolean('por_vencer');

        $contratos = ContratoPension::query()
            ->filtrar($q, $estado, $filtroPorVencer)
            ->with(['cliente', 'vehiculo', 'asignacionVigiente.espacio'])
            ->orderByDesc('fecha_inicio')
            ->paginate(10)
            ->withQueryString();

        $totalPorVencer = ContratoPension::porVencer()->count();

        // Combos para el modal de «Nueva pensión» (la vista pasa su propia
        // instancia vacía de $contrato: aquí la variable $contrato es el
        // elemento del listado).
        return view('contratos.index', array_merge($this->combos(), compact(
            'contratos', 'q', 'estado', 'filtroPorVencer', 'totalPorVencer'
        )));
    }

    public function create()
    {
        $this->authorize('contratos.crear');

        return view('contratos.create', $this->combos());
    }

    /** US-0303 + US-0304 (cajón opcional en el alta). */
    public function store(ContratoPensionRequest $request)
    {
        $this->authorize('contratos.crear');

        $datos      = $request->validated();
        $espacioId  = $datos['espacio_id'] ?? null;
        unset($datos['espacio_id']);

        $contrato = DB::transaction(function () use ($datos) {
            $cliente  = $this->resolverCliente($datos);
            $vehiculo = $this->resolverVehiculo($datos);

            return $this->crearContrato($this->datosDelContrato($datos, $cliente, $vehiculo));
        });

        // NUEVO: Al crear el contrato, si está PENDIENTE, pasa a ACTIVA automáticamente.
        if ($contrato->estado === 'PENDIENTE') {
            $contrato->update(['estado' => 'ACTIVA']);
        }

        $error = $espacioId
            ? $this->asignarCajon($contrato, (int) $espacioId, 'Asignación inicial')
            : null;

        if ($error) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'      => true,
                    'message' => "Contrato {$contrato->numero_contrato} creado, pero el cajón no se asignó: {$error}",
                ]);
            }

            return redirect()->route('contratos.show', $contrato)->with(
                'error',
                "Contrato {$contrato->numero_contrato} creado, pero el cajón no se asignó: {$error}"
            );
        }

        if ($request->expectsJson()) {
            // El modal de «Nueva pensión» imprime el ticket y sigue en el panel.
            return response()->json([
                'ok'         => true,
                'message'    => "Contrato {$contrato->numero_contrato} creado correctamente.",
                'ticket_url' => route('contratos.ticket', ['contrato' => $contrato, 'auto' => 1]),
                'redirect'   => route('contratos.show', $contrato),
            ]);
        }

        return redirect()->route('contratos.show', $contrato)
            ->with('success', "Contrato {$contrato->numero_contrato} creado correctamente.")
            ->with('ticket_nuevo', true); // el expediente abre el ticket para imprimir
    }

    /** Ticket térmico de 80 mm con el código de barras del folio (para cobro). */
    public function ticket(ContratoPension $contrato)
    {
        $this->authorize('contratos.ver');

        $contrato->load([
            'cliente', 'vehiculo.tipoVehiculo', 'tarifa',
            'asignacionVigiente.espacio.zona',
        ]);

        return view('contratos.ticket', [
            'contrato'   => $contrato,
            'barcodeSvg' => Barcode::code128($contrato->numero_contrato),
        ]);
    }

    public function edit(ContratoPension $contrato)
    {
        $this->authorize('contratos.editar');

        if (in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
            return redirect()->route('contratos.index')->with(
                'error',
                "El contrato {$contrato->numero_contrato} está cerrado: no se puede editar."
            );
        }

        return view('contratos.edit', array_merge($this->combos(), compact('contrato')));
    }

    public function update(ContratoPensionRequest $request, ContratoPension $contrato)
    {
        $this->authorize('contratos.editar');

        if (in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
            return redirect()->route('contratos.index')->with(
                'error',
                "El contrato {$contrato->numero_contrato} está cerrado: no se puede editar."
            );
        }

        $datos = $request->validated();
        unset($datos['espacio_id']); // la asignación se gestiona desde el expediente

        DB::transaction(function () use ($contrato, $datos) {
            $cliente  = $this->resolverCliente($datos);
            $vehiculo = $this->resolverVehiculo($datos);

            $contrato->update($this->datosDelContrato($datos, $cliente, $vehiculo));
        });

        return redirect()->route('contratos.show', $contrato)->with(
            'success',
            "Contrato {$contrato->numero_contrato} actualizado correctamente."
        );
    }

    /** US-0306: expediente completo. */
    public function show(ContratoPension $contrato)
    {
        $this->authorize('contratos.ver');

        $contrato->load([
            'cliente',
            'vehiculo.tipoVehiculo',
            'tarifa.tipoVehiculo',
            'asignaciones.espacio.zona',
            'asignacionVigiente.espacio.zona',
            'pagos',
        ]);

        $transiciones = $contrato->transicionesPermitidas();

        return view('contratos.show', compact('contrato', 'transiciones'));
    }

    /** Solo si no hay pagos (la FK de pagos es restrict); si no, se cancela. */
    public function destroy(ContratoPension $contrato)
    {
        $this->authorize('contratos.eliminar');

        if ($contrato->pagos()->exists()) {
            return back()->with(
                'error',
                'No se puede eliminar: el contrato tiene pagos registrados. Cancélalo en su lugar.'
            );
        }

        DB::transaction(function () use ($contrato) {
            $this->liberarCajones($contrato, 'Contrato eliminado');
            $contrato->delete(); // las asignaciones se eliminan en cascada
        });

        return redirect()->route('contratos.index')->with(
            'success',
            "Contrato {$contrato->numero_contrato} eliminado."
        );
    }

    /** US-0304: asigna un cajón libre al contrato desde el expediente. */
    public function asignarEspacio(Request $request, ContratoPension $contrato)
    {
        $this->authorize('contratos.asignar');

        if (in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
            return back()->with(
                'error',
                "El contrato {$contrato->numero_contrato} está cerrado: no admite cajón."
            );
        }

        $datos = $request->validate(
            ['espacio_id' => ['required', 'integer', 'exists:espacios,id']],
            ['espacio_id.required' => 'Seleccione un cajón.'],
            ['espacio_id' => 'Cajón']
        );

        $error = $this->asignarCajon($contrato, (int) $datos['espacio_id'], 'Asignación manual');

        if ($error) {
            return back()->with('error', "No se pudo asignar el cajón: {$error}.");
        }

        $codigo = $contrato->fresh()->asignacionVigiente?->espacio?->codigo;

        return back()->with('success', "Contrato {$contrato->numero_contrato}: cajón {$codigo} asignado.");
    }

    /** US-0304: libera el cajón vigente sin cerrar el contrato. */
    public function liberarEspacio(ContratoPension $contrato)
    {
        $this->authorize('contratos.asignar');

        if (! $contrato->asignacionVigiente) {
            return back()->with('error', 'Este contrato no tiene cajón asignado.');
        }

        $codigo = $contrato->asignacionVigiente?->espacio?->codigo;

        DB::transaction(fn () => $this->liberarCajones($contrato, 'Liberación manual'));

        return back()->with('success', "Contrato {$contrato->numero_contrato}: cajón {$codigo} liberado.");
    }

    /** US-0305: cambia el estado respetando la matriz de transiciones. */
    public function cambiarEstado(Request $request, ContratoPension $contrato)
    {
        $this->authorize('contratos.cambiar_estado');

        $permitidos = $contrato->transicionesPermitidas();

        $datos = $request->validate(
            ['estado' => ['required', 'in:' . implode(',', $permitidos ?: ['__ninguno__'])]],
            ['estado.in' => 'Ese cambio de estado no está permitido desde «'
                . ContratoPension::ESTADO_LABELS[$contrato->estado] . '».'],
            ['estado' => 'Estado']
        );

        DB::transaction(function () use ($contrato, $datos) {
            $contrato->update(['estado' => $datos['estado']]);

            if (in_array($datos['estado'], ['CANCELADA', 'FINALIZADA'], true)) {
                $this->liberarCajones($contrato, "Contrato {$datos['estado']}");
            } else {
                $this->sincronizarEspacio($contrato);
            }
        });

        return back()->with(
            'success',
            "Contrato {$contrato->numero_contrato}: estado ahora en «"
            . ContratoPension::ESTADO_LABELS[$datos['estado']] . '».'
        );
    }

    /**
     * US-0305: alarga la vigencia según la periodicidad elegida. Si se marca
     * «actualizar a tarifa vigente», el precio pasa al de la tarifa actual.
     */
    public function renovar(Request $request, ContratoPension $contrato)
    {
        $this->authorize('contratos.renovar');

        if (! in_array($contrato->estado, ['PENDIENTE', 'ACTIVA', 'VENCIDA'], true)) {
            return back()->with(
                'error',
                'Solo se renuevan contratos pendientes, activos o vencidos.'
            );
        }

        $datos = $request->validate(
            [
                'periodicidad'      => ['required', 'in:' . implode(',', ContratoPension::PERIODICIDADES)],
                'actualizar_precio' => ['nullable', 'boolean'],
            ],
            ['periodicidad.in' => 'La periodicidad seleccionada no es válida.'],
            ['periodicidad' => 'Periodicidad']
        );

        $meses = ContratoPension::MESES[$datos['periodicidad']];
        $base  = ($contrato->fecha_fin?->gt(now()) ? $contrato->fecha_fin->copy() : now()->startOfDay())
                    ->addMonthNoOverflow($meses);

        $contrato->update([
            'periodicidad' => $datos['periodicidad'],
            'fecha_fin'    => $base,
            'estado'       => $contrato->estado === 'VENCIDA' ? 'ACTIVA' : $contrato->estado,
            'precio'       => ! empty($datos['actualizar_precio']) && $contrato->tarifa
                ? $contrato->tarifa->precio
                : $contrato->precio,
        ]);

        $this->sincronizarEspacio($contrato);

        return back()->with(
            'success',
            "Contrato {$contrato->numero_contrato} renovado: vence el {$contrato->fresh()->fecha_fin->format('d/m/Y')}."
        );
    }

    /** Combos de los formularios: solo lo que debe verse (US-0302). */
    private function combos(): array
    {
        return [
            'tiposVehiculo' => TipoVehiculo::where('activo', true)->orderBy('nombre')->get(),
            'tarifas'   => Tarifa::vigente()->with('tipoVehiculo')
                            ->orderBy('tipo_tarifa')->orderBy('nombre')->get(),
            'espacios'  => Espacio::where('activo', true)->where('estado', 'DISPONIBLE')
                            ->with(['zona', 'tipoVehiculo'])->orderBy('codigo')->get(),
        ];
    }

    /**
     * Tres campos planos del cliente (nombre, apellidos y teléfono): busca
     * al cliente por nombre + apellidos; si existe se enlaza y solo se
     * actualiza el teléfono, y si no existe, se crea. Sin buscador ni
     * alta rápida en el formulario.
     */
    private function resolverCliente(array $datos): Cliente
    {
        $nombre        = trim($datos['cliente_nombre']);
        $apellidos     = trim($datos['cliente_apellido']);
        $apellidosNorm = $this->normalizarApellidos($apellidos);
        $telefono      = trim($datos['cliente_telefono'] ?? '');

        $cliente = Cliente::where('nombre', $nombre)
            ->get()
            ->first(fn (Cliente $c) => $this->normalizarApellidos(
                ($c->apellido_paterno ?? '') . ' ' . ($c->apellido_materno ?? '')
            ) === $apellidosNorm);

        if ($cliente) {
            if ($telefono !== '' && $telefono !== (string) $cliente->telefono) {
                $cliente->update(['telefono' => $telefono]);
            }

            return $cliente;
        }

        return Cliente::create([
            'nombre'           => $nombre,
            'apellido_paterno' => $apellidos !== '' ? $apellidos : '—',
            'telefono'         => $telefono !== '' ? $telefono : null,
        ]);
    }

    /** Minúsculas, espacios colapsados y «—» (vacío) fuera, para comparar. */
    private function normalizarApellidos(string $texto): string
    {
        $texto = str_replace('—', ' ', $texto);

        return preg_replace('/\s+/', ' ', mb_strtolower(trim($texto)));
    }

    /**
     * Unidad capturada en línea: si las placas ya existen se reutiliza el
     * vehículo; si no, se crea. (La relación cliente–vehículo aún no existe:
     * el cliente queda ligado por el contrato.)
     */
    private function resolverVehiculo(array $datos): Vehiculo
    {
        $placas   = strtoupper(trim($datos['placas']));
        $vehiculo = Vehiculo::where('placas', $placas)->first();

        $atributos = [
            'placas'           => $placas,
            'tipo_vehiculo_id' => (int) $datos['tipo_vehiculo_id'],
            'marca'            => $datos['marca'],
            'modelo'           => $datos['modelo'],
            'color'            => $datos['color'],
        ];

        if (! $vehiculo) {
            return Vehiculo::create($atributos);
        }

        $vehiculo->update($atributos);

        return $vehiculo;
    }

    /**
     * Deja únicamente los campos de la tabla contratos_pension: quita los
     * auxiliares de alta rápida y de captura de la unidad, enlaza cliente y
     * vehículo, y aplica la fecha de entrada automática.
     */
    private function datosDelContrato(array $datos, Cliente $cliente, Vehiculo $vehiculo): array
    {
        unset(
            $datos['cliente_nombre'], $datos['cliente_apellido'],
            $datos['cliente_telefono'], $datos['tipo_vehiculo_id'],
            $datos['placas'], $datos['marca'], $datos['modelo'], $datos['color'],
        );

        $datos['cliente_id']    = $cliente->id;
        $datos['vehiculo_id']   = $vehiculo->id;
        $datos['fecha_entrada'] = $datos['fecha_entrada'] ?? now();

        return $datos;
    }

    /**
     * Crea el contrato con folio autogenerado. Si dos altas simultáneas chocan
     * por el UNIQUE de `numero_contrato`, reintenta hasta 3 veces.
     */
    private function crearContrato(array $datos): ContratoPension
    {
        $intentos = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($datos) {
                    $datos['numero_contrato'] = $this->generarFolio();
                    $datos['estado']          = 'PENDIENTE';

                    return ContratoPension::create($datos);
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

    /** Folio consecutivo por año: PEN-2026-0001, PEN-2026-0002, ... */
    private function generarFolio(): string
    {
        $patron = 'PEN-' . now()->format('Y') . '-%';

        $ultima = ContratoPension::where('numero_contrato', 'like', $patron)
            ->orderByRaw('CAST(SUBSTRING(numero_contrato, 10) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->value('numero_contrato');

        $secuencia = $ultima ? ((int) substr($ultima, -4)) + 1 : 1;

        return sprintf('PEN-%s-%04d', now()->format('Y'), $secuencia);
    }

    /**
     * Asigna un cajón al contrato. Devuelve `null` si todo va bien o un mensaje
     * en español (minúsculas, sin punto final) si algo impide la asignación.
     *
     * Reglas: cajón activo y LIBRE, tipo de vehículo compatible, sin otra
     * asignación vigente; el estado del cajón se sincroniza con el contrato
     * (ACTIVA → OCUPADO; PENDIENTE/SUSPENDIDA/VENCIDA → RESERVADO).
     */
    private function asignarCajon(ContratoPension $contrato, int $espacioId, string $motivo): ?string
    {
        return DB::transaction(function () use ($contrato, $espacioId, $motivo) {
            $espacio = Espacio::whereKey($espacioId)->lockForUpdate()->first();

            if (! $espacio || ! $espacio->activo) {
                return 'el cajón no existe o está inactivo';
            }

            if ($espacio->estado !== 'DISPONIBLE') {
                return "el cajón {$espacio->codigo} no está libre (estado: {$espacio->estado_label})";
            }

            $tipoDelVehiculo = $contrato->vehiculo?->tipo_vehiculo_id;
            if ($tipoDelVehiculo && (int) $espacio->tipo_vehiculo_id !== (int) $tipoDelVehiculo) {
                return "el cajón {$espacio->codigo} no admite el tipo de vehículo de este contrato";
            }

            if ($espacio->asignaciones()->whereNull('fecha_fin')->exists()) {
                return "el cajón {$espacio->codigo} ya tiene una asignación vigente";
            }

            if ($contrato->asignacionVigiente) {
                if ($contrato->asignacionVigiente->espacio_id === $espacio->id) {
                    return "el contrato ya usa el cajón {$espacio->codigo}";
                }
                $this->liberarCajones($contrato, 'Cambio de cajón');
            }

            AsignacionEspacio::create([
                'contrato_pension_id' => $contrato->id,
                'espacio_id'          => $espacio->id,
                'fecha_inicio'        => now()->toDateString(),
                'fecha_fin'           => null,
                'motivo'              => $motivo,
                'activo'              => true,
            ]);

            $espacio->update([
                'estado' => $contrato->estado === 'ACTIVA' ? 'OCUPADO' : 'RESERVADO',
            ]);

            return null;
        });
    }

    /**
     * Cierra la(s) asignación(es) vigente(s) del contrato y devuelve sus
     * cajones a DISPONIBLE (salvo que estén en MANTENIMIENTO/BLOQUEADO).
     */
    private function liberarCajones(ContratoPension $contrato, string $motivo): void
    {
        $vigentes = $contrato->asignaciones()->whereNull('fecha_fin')->get();

        foreach ($vigentes as $asignacion) {
            $asignacion->update([
                'fecha_fin' => now()->toDateString(),
                'motivo'    => $motivo,
                'activo'    => false,
            ]);

            $espacio = $asignacion->espacio;
            if ($espacio && in_array($espacio->estado, ['OCUPADO', 'RESERVADO'], true)) {
                $espacio->update(['estado' => 'DISPONIBLE']);
            }
        }
    }

    /**
     * Mantiene sincronizado el estado del cajón cuando cambia el contrato
     * (se llama desde cambiarEstado y renovar).
     */
    private function sincronizarEspacio(ContratoPension $contrato): void
    {
        $espacio = $contrato->asignacionVigiente?->espacio;

        if (! $espacio) {
            return;
        }

        $estado = match ($contrato->estado) {
            'ACTIVA'                     => 'OCUPADO',
            'PENDIENTE', 'SUSPENDIDA', 'VENCIDA' => 'RESERVADO',
            default                      => 'DISPONIBLE',
        };

        if ($espacio->estado !== $estado) {
            $espacio->update(['estado' => $estado]);
        }
    }
}
