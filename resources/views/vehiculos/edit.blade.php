@extends('layouts.app')
@section('title', 'Editar vehículo')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Editar: {{ $vehiculo->placas }}</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('vehiculos.update', $vehiculo) }}" method="POST" enctype="multipart/form-data">
            @method('PUT')
            @include('vehiculos._form')
            <div class="mt-4">
                <button class="btn btn-primary">Actualizar</button>
                <a href="{{ route('vehiculos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
