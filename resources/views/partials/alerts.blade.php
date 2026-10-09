@if (session('success'))
<script>
    document.addEventListener('DOMContentLoaded', () => Swal.fire({
        icon: 'success', title: '¡Listo!', text: @json(session('success')),
        confirmButtonColor: '#0d6efd', timer: 2500, timerProgressBar: true
    }));
</script>
@endif

@if (session('error'))
<script>
    document.addEventListener('DOMContentLoaded', () => Swal.fire({
        icon: 'error', title: 'Error', text: @json(session('error')), confirmButtonColor: '#dc3545'
    }));
</script>
@endif
