<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZonaRequest;
use App\Models\Zona;
use Illuminate\Http\Request;

class ZonaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('zonas.ver');

        $zonas = Zona::query()
            ->buscar($request->string('q')->toString())
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->withCount('espacios')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('zonas.index', compact('zonas'));
    }

    public function create()
    {
        $this->authorize('zonas.crear');

        return view('zonas.create');
    }

    public function store(ZonaRequest $request)
    {
        $this->authorize('zonas.crear');

        Zona::create($request->validated());

        return redirect()->route('zonas.index')->with('success', 'Zona registrada correctamente.');
    }

    public function edit(Zona $zona)
    {
        $this->authorize('zonas.editar');

        return view('zonas.edit', compact('zona'));
    }

    public function update(ZonaRequest $request, Zona $zona)
    {
        $this->authorize('zonas.editar');

        $zona->update($request->validated());

        return redirect()->route('zonas.index')->with('success', 'Zona actualizada correctamente.');
    }

    public function destroy(Zona $zona)
    {
        $this->authorize('zonas.eliminar');

        if ($zona->espacios()->exists()) {
            $zona->update(['activo' => false]);
            $mensaje = 'La zona tiene cajones asociados: se desactivó en lugar de eliminarla.';
        } else {
            $zona->delete();
            $mensaje = 'Zona eliminada.';
        }

        return redirect()->route('zonas.index')->with('success', $mensaje);
    }
}
