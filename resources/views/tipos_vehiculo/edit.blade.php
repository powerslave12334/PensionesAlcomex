@extends('layouts.app')
@section('title', 'Editar tipo de vehículo')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Editar: {{ $tipo->nombre }}</h5></div>
    <div class="card-body">
        <form action="{{ route('tipos-vehiculo.update', $tipo) }}" method="POST">
            @method('PUT')
            @include('tipos_vehiculo._form')
            <div class="mt-4">
                <button class="btn btn-primary">Actualizar</button>
                <a href="{{ route('tipos-vehiculo.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
