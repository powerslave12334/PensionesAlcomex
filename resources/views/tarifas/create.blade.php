@extends('layouts.app')
@section('title', 'Nueva tarifa')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Nueva tarifa</h5></div>
    <div class="card-body">
        <form action="{{ route('tarifas.store') }}" method="POST">
            @include('tarifas._form')
            <div class="mt-4">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('tarifas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
