@extends('layouts.app')
@section('title', 'Nuevo cliente')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Nuevo cliente</h5></div>
    <div class="card-body">
        <form action="{{ route('clientes.store') }}" method="POST">
            @include('clientes._form')
            <div class="mt-4">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
