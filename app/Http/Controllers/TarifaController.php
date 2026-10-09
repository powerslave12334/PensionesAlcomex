<?php

namespace App\Http\Controllers;

use App\Http\Requests\TarifaRequest;
use App\Models\Tarifa;
use App\Models\TipoVehiculo;
use Illuminate\Http\Request;

class TarifaController extends Controller
{
    /** US-0301: listado con filtros. */
    public function index(Request $request)
    {
        $this->authorize('tarifas.ver');

        $request->validate([
            'q'       => ['nullable', 'string', 'max:100'],
            'tipo'    => ['nullable', 'integer', 'exists:tipos_vehiculo,id'],
            'periodo' => ['nullable', 'in:' . implode(',', Tarifa::TIPOS)],
            'vigentes'=> ['nullable', 'boolean'],
        ]);

        $q       = $request->string('q')->toString() ?: null;
        $tipo    = $request->integer('tipo') ?: null;
        $periodo = $request->string('periodo')->toString() ?: null;
        $vigentes = $request->boolean('vigentes');

        $tarifas = Tarifa::query()
            ->buscar($q)
            ->when($tipo, fn ($query) => $query->where('tipo_vehiculo_id', $tipo))
            ->when($periodo, fn ($query) => $query->where('tipo_tarifa', $periodo))
            ->when($vigentes, fn ($query) => $query->vigente())
            ->with('tipoVehiculo')
            ->orderBy('tipo_tarifa')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $tipos = TipoVehiculo::where('activo', true)->orderBy('nombre')->get();

        return view('tarifas.index', compact('tarifas', 'tipos', 'q', 'tipo', 'periodo', 'vigentes'));
    }

    public function create()
    {
        $this->authorize('tarifas.crear');

        return view('tarifas.create', $this->combos());
    }

    public function store(TarifaRequest $request)
    {
        $this->authorize('tarifas.crear');

        Tarifa::create($request->validated());

        return redirect()->route('tarifas.index')->with('success', 'Tarifa registrada correctamente.');
    }

    public function edit(Tarifa $tarifa)
    {
        $this->authorize('tarifas.editar');

        return view('tarifas.edit', array_merge($this->combos(), compact('tarifa')));
    }

    public function update(TarifaRequest $request, Tarifa $tarifa)
    {
        $this->authorize('tarifas.editar');

        $tarifa->update($request->validated());

        return redirect()->route('tarifas.index')->with('success', 'Tarifa actualizada correctamente.');
    }

    /** Desactiva en vez de borrar si ya se usó en contratos (la FK es restrict). */
    public function destroy(Tarifa $tarifa)
    {
        $this->authorize('tarifas.eliminar');

        if ($tarifa->contratos()->exists()) {
            $tarifa->update(['activo' => false]);
            $mensaje = 'La tarifa tiene contratos asociados: se desactivó en lugar de eliminarla.';
        } else {
            $tarifa->delete();
            $mensaje = 'Tarifa eliminada.';
        }

        return redirect()->route('tarifas.index')->with('success', $mensaje);
    }

    private function combos(): array
    {
        return [
            'tipos' => TipoVehiculo::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}
