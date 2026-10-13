@extends('layouts.app')
@section('title', 'Nuevo cobro')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Nuevo cobro</h4>
    @if ($contrato)
        <span class="badge bg-secondary fs-6">
            {{ $contrato->numero_contrato }} · {{ $contrato->cliente?->nombre_completo }}
        </span>
    @endif
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('pagos.store') }}" method="POST" novalidate>
            @csrf
            @include('pagos._form')
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-success">Guardar cobro</button>
                <a href="{{ route('pagos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
