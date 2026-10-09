<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('clientes.ver');

        $clientes = Cliente::query()
            ->buscar($request->string('q')->toString())
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->withCount('vehiculos')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    public function create()
    {
        $this->authorize('clientes.crear');

        return view('clientes.create');
    }

    public function store(ClienteRequest $request)
    {
        $this->authorize('clientes.crear');

        Cliente::create($request->validated());

        return redirect()->route('clientes.index')->with('success', 'Cliente registrado correctamente.');
    }

    public function edit(Cliente $cliente)
    {
        $this->authorize('clientes.editar');

        return view('clientes.edit', compact('cliente'));
    }

    public function update(ClienteRequest $request, Cliente $cliente)
    {
        $this->authorize('clientes.editar');

        $cliente->update($request->validated());

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente)
    {
        $this->authorize('clientes.eliminar');

        if ($cliente->vehiculos()->exists()) {
            $cliente->update(['activo' => false]);
            $mensaje = 'El cliente tiene vehículos asociados: se desactivó en lugar de eliminarlo.';
        } else {
            $cliente->delete();
            $mensaje = 'Cliente eliminado.';
        }

        return redirect()->route('clientes.index')->with('success', $mensaje);
    }
}
