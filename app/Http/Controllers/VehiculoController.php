<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehiculoRequest;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Http\Request;

class VehiculoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('vehiculos.ver');

        $vehiculos = Vehiculo::query()
            ->buscar($request->string('q')->toString())
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_vehiculo_id', $request->integer('tipo')))
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->with(['tipoVehiculo'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $tipos = TipoVehiculo::where('activo', true)->orderBy('nombre')->get();

        return view('vehiculos.index', compact('vehiculos', 'tipos'));
    }

    public function create()
    {
        $this->authorize('vehiculos.crear');

        return view('vehiculos.create', $this->combos());
    }

    /**
     * Autocompletado por placas del formulario de pensiones: devuelve el
     * vehículo guardado para rellenar tipo/marca/modelo/color.
     * Autoriza con vehiculos.ver O contratos.crear (el CAJERO no tiene
     * vehiculos.ver y necesita buscar al dar de alta una pensión).
     */
    public function buscar(Request $request)
    {
        abort_unless(
            $request->user()?->can('vehiculos.ver') || $request->user()?->can('contratos.crear'),
            403
        );

        $placas = strtoupper(trim($request->string('placas')->toString()));

        if ($placas === '') {
            return response()->json(['vehiculo' => null]);
        }

        $vehiculo = Vehiculo::query()
            ->where('placas', $placas)
            ->with(['tipoVehiculo:id,nombre'])
            ->first();

        return response()->json([
            'vehiculo' => $vehiculo ? [
                'id'               => $vehiculo->id,
                'placas'           => $vehiculo->placas,
                'tipo_vehiculo_id' => $vehiculo->tipo_vehiculo_id,
                'tipo'             => $vehiculo->tipoVehiculo?->nombre,
                'marca'            => $vehiculo->marca,
                'modelo'           => $vehiculo->modelo,
                'color'            => $vehiculo->color,
            ] : null,
        ]);
    }

    public function store(VehiculoRequest $request)
    {
        $this->authorize('vehiculos.crear');

        Vehiculo::create($this->guardarFoto($request));

        return redirect()->route('vehiculos.index')->with('success', 'Vehículo registrado correctamente.');
    }

    public function edit(Vehiculo $vehiculo)
    {
        $this->authorize('vehiculos.editar');

        return view('vehiculos.edit', array_merge($this->combos(), compact('vehiculo')));
    }

    public function update(VehiculoRequest $request, Vehiculo $vehiculo)
    {
        $this->authorize('vehiculos.editar');

        $vehiculo->update($this->guardarFoto($request));

        return redirect()->route('vehiculos.index')->with('success', 'Vehículo actualizado correctamente.');
    }

    public function destroy(Vehiculo $vehiculo)
    {
        $this->authorize('vehiculos.eliminar');

        $vehiculo->update(['activo' => false]);

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo desactivado (se conserva el histórico).');
    }

    /** Datos compartidos para los combos del formulario. */
    private function combos(): array
    {
        return [
            'tipos' => TipoVehiculo::where('activo', true)->orderBy('nombre')->get(),
        ];
    }

    /** Sube la foto a storage/app/public/vehiculos y devuelve los datos a guardar. */
    private function guardarFoto(VehiculoRequest $request): array
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $data['foto_url'] = $request->file('foto')->store('vehiculos', 'public');
        }

        unset($data['foto']);

        return $data;
    }
}
