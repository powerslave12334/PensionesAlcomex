@extends('layouts.app')
@section('title', 'Editar contrato')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Editar: {{ $contrato->numero_contrato }}
            <span class="badge {{ $contrato->estado_clase }} ms-2">{{ $contrato->estado_label }}</span>
        </h5>
    </div>
    <div class="card-body">
        <form action="{{ route('contratos.update', $contrato) }}" method="POST">
            @method('PUT')
            @include('contratos._form')
            <div class="mt-4">
                <button class="btn btn-primary">Actualizar</button>
                <a href="{{ route('contratos.show', $contrato) }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
