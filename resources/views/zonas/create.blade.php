@extends('layouts.app')
@section('title', 'Nueva zona')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Nueva zona</h5></div>
    <div class="card-body">
        <form action="{{ route('zonas.store') }}" method="POST">
            @include('zonas._form')
            <div class="mt-4">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('zonas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
