<form action="{{ route('pagos.store') }}" method="POST" id="formCobro" class="row g-3">
    @csrf
    @include('pagos._form')
    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-success" data-guardar>Registrar cobro</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
    </div>
</form>
