@extends('layouts.app')
@section('title', 'Editar cliente')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Editar: {{ $cliente->nombre_completo }}</h5></div>
    <div class="card-body">
        <form action="{{ route('clientes.update', $cliente) }}" method="POST">
            @method('PUT')
            @include('clientes._form')
            <div class="mt-4">
                <button class="btn btn-primary">Actualizar</button>
                <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
