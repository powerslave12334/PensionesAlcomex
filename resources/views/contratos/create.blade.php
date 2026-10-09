@extends('layouts.app')
@section('title', 'Nuevo contrato')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Nuevo contrato de pensión</h5></div>
    <div class="card-body">
        <form action="{{ route('contratos.store') }}" method="POST">
            @include('contratos._form')
            <div class="mt-4">
                <button class="btn btn-primary">Guardar contrato</button>
                <a href="{{ route('contratos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
