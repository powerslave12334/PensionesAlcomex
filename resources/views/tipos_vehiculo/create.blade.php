@extends('layouts.app')
@section('title', 'Nuevo tipo de vehículo')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Nuevo tipo de vehículo</h5></div>
    <div class="card-body">
        <form action="{{ route('tipos-vehiculo.store') }}" method="POST">
            @include('tipos_vehiculo._form')
            <div class="mt-4">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('tipos-vehiculo.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
