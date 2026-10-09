@extends('layouts.app')
@section('title', 'Editar tarifa')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Editar: {{ $tarifa->nombre }}</h5></div>
    <div class="card-body">
        <form action="{{ route('tarifas.update', $tarifa) }}" method="POST">
            @method('PUT')
            @include('tarifas._form')
            <div class="mt-4">
                <button class="btn btn-primary">Actualizar</button>
                <a href="{{ route('tarifas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
