@extends('layouts.app')
@section('title', 'Nuevo cajón')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Nuevo cajón</h5></div>
    <div class="card-body">
        <form action="{{ route('espacios.store') }}" method="POST">
            @include('espacios._form')
            <div class="mt-4">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('espacios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
