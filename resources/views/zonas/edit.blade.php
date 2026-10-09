@extends('layouts.app')
@section('title', 'Editar zona')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Editar: {{ $zona->nombre }}</h5></div>
    <div class="card-body">
        <form action="{{ route('zonas.update', $zona) }}" method="POST">
            @method('PUT')
            @include('zonas._form')
            <div class="mt-4">
                <button class="btn btn-primary">Actualizar</button>
                <a href="{{ route('zonas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
