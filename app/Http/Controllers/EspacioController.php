<?php

namespace App\Http\Controllers;

use App\Http\Requests\EspacioRequest;
use App\Models\Espacio;
use App\Models\TipoVehiculo;
use App\Models\Zona;
use Illuminate\Http\Request;

class EspacioController extends Controller
{
    /** Mapa/lista de cajones agrupados por zona (US-0205) + resumen de estados. */
    public function index(Request $request)
    {
        $this->authorize('espacios.ver');

        $request->validate([
            'zona'   => ['nullable', 'integer', 'exists:zonas,id'],
            'estado' => ['nullable', 'in:' . implode(',', Espacio::ESTADOS)],
        ]);

        $zona   = $request->integer('zona') ?: null;
        $estado = $request->string('estado')->toString() ?: null;

        $espacios = Espacio::query()
            ->filtrar($zona, $estado)
            ->with(['zona', 'tipoVehiculo', 'asignacionVigiente.contrato.vehiculo'])
            ->where('activo', true)
            ->orderBy('zona_id')
            ->orderBy('codigo')
            ->get()
            ->groupBy(fn (Espacio $e) => $e->zona->nombre);

        $resumen = Espacio::where('activo', true)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $zonas = Zona::where('activo', true)->orderBy('nombre')->get();

        return view('espacios.index', compact('espacios', 'zonas', 'resumen', 'zona', 'estado'));
    }

    public function create()
    {
        $this->authorize('espacios.crear');

        return view('espacios.create', $this->combos());
    }

    public function store(EspacioRequest $request)
    {
        $this->authorize('espacios.crear');

        Espacio::create($request->validated());

        return redirect()->route('espacios.index')->with('success', 'Cajón registrado correctamente.');
    }

    public function edit(Espacio $espacio)
    {
        $this->authorize('espacios.editar');

        return view('espacios.edit', array_merge($this->combos(), compact('espacio')));
    }

    public function update(EspacioRequest $request, Espacio $espacio)
    {
        $this->authorize('espacios.editar');

        $espacio->update($request->validated());

        return redirect()->route('espacios.index')->with('success', 'Cajón actualizado correctamente.');
    }

    public function destroy(Espacio $espacio)
    {
        $this->authorize('espacios.eliminar');

        if ($espacio->asignaciones()->exists() || $espacio->estado === 'OCUPADO') {
            $espacio->update(['activo' => false]);
            $mensaje = 'El cajón está en uso: se desactivó en lugar de eliminarlo.';
        } else {
            $espacio->delete();
            $mensaje = 'Cajón eliminado.';
        }

        return redirect()->route('espacios.index')->with('success', $mensaje);
    }

    /** Cambio rápido de estado desde el mapa (US-0204). */
    public function cambiarEstado(Request $request, Espacio $espacio)
    {
        $this->authorize('espacios.cambiar_estado');

        $request->validate([
            'estado' => ['required', 'in:' . implode(',', Espacio::ESTADOS)],
        ]);

        $espacio->update(['estado' => $request->string('estado')->toString()]);

        return back()->with(
            'success',
            "Cajón {$espacio->codigo}: estado ahora en {$espacio->estado_label}."
        );
    }

    private function combos(): array
    {
        return [
            'zonas' => Zona::where('activo', true)->orderBy('nombre')->get(),
            'tipos' => TipoVehiculo::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}
