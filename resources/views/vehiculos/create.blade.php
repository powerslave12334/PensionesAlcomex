@extends('layouts.app')
@section('title', 'Nuevo vehículo')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Nuevo vehículo</h5></div>
    <div class="card-body">
        <form action="{{ route('vehiculos.store') }}" method="POST" enctype="multipart/form-data">
            @include('vehiculos._form')
            <div class="mt-4">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('vehiculos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
