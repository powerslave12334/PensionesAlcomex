<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoVehiculoRequest;
use App\Models\TipoVehiculo;
use Illuminate\Http\Request;

class TipoVehiculoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('tipos_vehiculo.ver');

        $tipos = TipoVehiculo::query()
            ->buscar($request->string('q')->toString())
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->withCount('vehiculos', 'espacios')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('tipos_vehiculo.index', compact('tipos'));
    }

    public function create()
    {
        $this->authorize('tipos_vehiculo.crear');

        return view('tipos_vehiculo.create');
    }

    public function store(TipoVehiculoRequest $request)
    {
        $this->authorize('tipos_vehiculo.crear');

        TipoVehiculo::create($request->validated());

        return redirect()->route('tipos-vehiculo.index')->with('success', 'Tipo de vehículo registrado.');
    }

    public function edit(TipoVehiculo $tipo)
    {
        $this->authorize('tipos_vehiculo.editar');

        return view('tipos_vehiculo.edit', compact('tipo'));
    }

    public function update(TipoVehiculoRequest $request, TipoVehiculo $tipo)
    {
        $this->authorize('tipos_vehiculo.editar');

        $tipo->update($request->validated());

        return redirect()->route('tipos-vehiculo.index')->with('success', 'Tipo de vehículo actualizado.');
    }

    public function destroy(TipoVehiculo $tipo)
    {
        $this->authorize('tipos_vehiculo.eliminar');

        if ($tipo->vehiculos()->exists() || $tipo->espacios()->exists()) {
            $tipo->update(['activo' => false]);
            $mensaje = 'El tipo está en uso: se desactivó en lugar de eliminarlo.';
        } else {
            $tipo->delete();
            $mensaje = 'Tipo de vehículo eliminado.';
        }

        return redirect()->route('tipos-vehiculo.index')->with('success', $mensaje);
    }
}
