# Guía paso a paso — Login + Sprint 1 (Clientes y Vehículos)

Guía operativa para levantar la autenticación con **laravel/ui** y construir los dos primeros módulos del plan (`PLAN-SCRUM.md` → Sprint 1).

- **Preset:** Bootstrap 5 (ya instalado en el proyecto)
- **Auth:** `laravel/ui` (`ui:auth`)
- **Estilo de módulos:** Blade + Resource Controller (el mismo patrón se repite en los siguientes módulos)

---

## 0. Estado actual del proyecto

| Elemento | Estado |
|---|---|
| `composer.json` | Laravel 10, `laravel/ui ^4.6`, `livewire ^4.4`, `spatie/laravel-permission ^6.25` ✅ |
| `package.json` / `vite.config.js` / `resources/sass/app.scss` | Bootstrap 5 + Sass + SweetAlert2 ya configurados ✅ |
| `node_modules` | instalados ✅ |
| Migraciones | 25 archivos creados, los nuevos **aún sin columnas** (esqueletos) ⚠️ |
| `.env` | `APP_KEY=` **vacía** ⚠️ · `DB_DATABASE=pensiones` |
| PHP | `pdo_mysql` **deshabilitado** en `C:\php\php.ini` ⚠️ |
| Middleware Spatie | **no registrado** en `app/Http/Kernel.php` ⚠️ |
| Vistas | solo `welcome.blade.php` |
| Rutas | solo `/` |

---

# FASE 1 — Base de datos mínima

## 1.1 Habilitar el driver de MySQL

`C:\php\php.ini` línea ~945: quitar el `;` inicial.

```ini
extension=pdo_mysql
```

```powershell
php -m | Select-String pdo_mysql     # debe imprimir "pdo_mysql"
```

> Si no aparece, revisa `php --ini` para confirmar qué `php.ini` está cargando el CLI.

## 1.2 Generar la clave de aplicación

`.env` tiene `APP_KEY=` vacía; sin ella nada funciona (cookies/sesión cifradas).

```powershell
php artisan key:generate
```

## 1.3 Completar la migración de `users`

**Archivo:** `database/migrations/2014_10_12_000000_create_users_table.php`
Agrega las columnas extra del Excel (`telefono`, `activo`, `ultimo_acceso`):

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('telefono', 30)->nullable();      // nuevo
    $table->boolean('activo')->default(true);        // nuevo
    $table->dateTime('ultimo_acceso')->nullable();   // nuevo
    $table->rememberToken();
    $table->timestamps();
});
```

## 1.4 Completar la migración de `clientes`

**Archivo:** `database/migrations/2026_10_06_210600_create_clientes_table.php`
Copia el código de `MIGRACIONES.md` → §5.7:

```php
public function up(): void
{
    Schema::create('clientes', function (Blueprint $table) {
        $table->id();
        $table->string('nombre', 100);
        $table->string('apellido_paterno', 100);
        $table->string('apellido_materno', 100)->nullable();
        $table->string('telefono', 30)->nullable();
        $table->string('email', 150)->nullable();
        $table->string('direccion', 255)->nullable();
        $table->string('ciudad', 100)->nullable();
        $table->string('estado', 100)->nullable();
        $table->string('codigo_postal', 10)->nullable();
        $table->string('rfc', 20)->nullable();
        $table->date('fecha_nacimiento')->nullable();
        $table->text('observaciones')->nullable();
        $table->boolean('activo')->default(true);
        $table->timestamps();
    });
}
```

## 1.5 Completar la migración de `vehiculos`

**Archivo:** `database/migrations/2026_10_06_210700_create_vehiculos_table.php`
Copia el código de `MIGRACIONES.md` → §5.8:

```php
public function up(): void
{
    Schema::create('vehiculos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
        $table->string('placas', 20)->unique();
        $table->string('marca', 80)->nullable();
        $table->string('modelo', 80)->nullable();
        $table->smallInteger('anio')->nullable();
        $table->string('color', 50)->nullable();
        $table->foreignId('tipo_vehiculo_id')->constrained('tipos_vehiculo')->restrictOnDelete();
        $table->string('vin', 50)->nullable()->index();
        $table->string('numero_motor', 100)->nullable();
        $table->string('foto_url', 500)->nullable();
        $table->text('observaciones')->nullable();
        $table->boolean('activo')->default(true);
        $table->timestamps();
    });
}
```

> `vehiculos` referencia a `tipos_vehiculo`, por eso esa migración (§5.2) **también** debe estar completa antes de migrar.

## 1.6 Completar `tipos_vehiculo` (catálogo mínimo)

**Archivo:** `database/migrations/2026_10_06_210100_create_tipos_vehiculo_table.php` → `MIGRACIONES.md` §5.2:

```php
Schema::create('tipos_vehiculo', function (Blueprint $table) {
    $table->id();
    $table->string('nombre', 80);
    $table->string('descripcion', 255)->nullable();
    $table->decimal('tarifa_base', 12, 2)->default(0);
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

## 1.7 Completar el ALTER de las tablas de Spatie

**Archivo:** `database/migrations/2026_10_06_210000_add_extra_columns_to_permission_tables.php`
→ `MIGRACIONES.md` §5.1. Sin esto, el seeder de la Fase 6 falla con *Unknown column 'descripcion' / 'modulo'*:

```php
public function up(): void
{
    Schema::table('roles', function (Blueprint $table) {
        $table->string('descripcion', 255)->nullable()->after('name');
    });

    Schema::table('permissions', function (Blueprint $table) {
        $table->string('modulo', 50)->nullable()->after('name')->index();
        $table->string('accion', 30)->nullable()->after('modulo');
    });
}
```

## 1.8 Migrar

```powershell
php artisan migrate:fresh
php artisan migrate:status      # todas en "Ran"
```

> Las otras 16 migraciones nuevas siguen en esqueleto: solo las necesarias para este sprint se rellenan aquí. Las demás se completan en su sprint correspondiente.

---

# FASE 2 — Login con laravel/ui

## 2.1 Generar el scaffolding de autenticación

```powershell
php artisan ui:auth
```

**Ejecuta `php artisan ui bootstrap --auth` NO**, porque `Bootstrap::install()` **borra `node_modules`** (`Preset::removeNodeModules()`) y sobrescribe `vite.config.js`, `resources/sass/app.scss`, `resources/sass/_variables.scss` y `resources/js/bootstrap.js`. El preset Bootstrap **ya está instalado** en este proyecto, así que solo necesitas la parte de auth.

> **Nota:** el comando puede preguntar si reemplazar `HomeController.php` y `Controller.php`. Responde **Sí** (o usa `php artisan ui:auth --force`): los stubs son equivalentes e incluyen los traits `AuthorizesRequests` y `ValidatesRequests` que se usan en esta guía.

### Archivos que genera `ui:auth`

| Tipo | Archivos |
|---|---|
| Vistas | `resources/views/auth/login.blade.php`, `auth/register.blade.php`, `auth/passwords/{email,reset,confirm}.blade.php`, `auth/verify.blade.php`, `home.blade.php`, `layouts/app.blade.php` |
| Controladores | `app/Http/Controllers/Auth/{Login,Register,ForgotPassword,ResetPassword,ConfirmPassword,Verification}Controller.php`, `HomeController.php` |
| Rutas | **agrega al final** de `routes/web.php`: `Auth::routes();` + ruta `/home` |
| Migración extra | `database/migrations/2014_10_12_100000_create_password_resets_table.php` ⚠️ |

## 2.2 Limpiar la migración redundante

Laravel 10 ya usa la tabla `password_reset_tokens` (migración `2014_10_12_100000_create_password_reset_tokens_table.php` y `config/auth.php → passwords.users.table`). La que acaba de copiar laravel/ui crea una tabla `password_resets` que **no se usa**:

```powershell
Remove-Item database\migrations\2014_10_12_100000_create_password_resets_table.php
```

## 2.3 Revisar `routes/web.php`

Quedaría al final:

```php
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
```

`Auth` funciona sin `use` porque está en las clases-alias de `config/app.php` (`Facade::defaultAliases()`).

### Ajuste recomendado: desactivar el registro público

Es un sistema interno; los usuarios los crea el administrador:

```php
Auth::routes(['register' => false]);
```

Y mover `/home` dentro del grupo `auth` (paso 3.1).

## 2.4 Personalizar el `.env`

```env
APP_NAME="Pensión Vehicular"
```

## 2.5 Compilar assets

```powershell
npm run dev        # desarrollo (vite --watch)
# o
npm run build      # producción
```

> Como **no** corriste `ui bootstrap`, `node_modules` sigue intacto y no necesitas `npm install`.

## 2.6 Crear el usuario administrador

```powershell
php artisan tinker
```
```php
>>> App\Models\User::create(['name' => 'Administrador', 'email' => 'admin@pension.mx', 'password' => bcrypt('password')])
>>> exit
```

## 2.7 Probar el login

```powershell
php artisan serve
```
1. Abre `http://127.0.0.1:8000/login`
2. Entra con `admin@pension.mx` / `password`
3. Debe redirigir a `/home` (publicada como `home.blade.php`)

---

# FASE 3 — Layout con sidebar y rutas base

## 3.1 Rutas del Sprint 1

**Archivo:** `routes/web.php` (reemplaza el contenido añadido por `ui:auth`):

```php
<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false]);

Route::middleware(['auth'])->group(function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

    Route::resource('clientes', ClienteController::class);
    Route::resource('vehiculos', VehiculoController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
});
```

## 3.2 Layout `resources/views/layouts/app.blade.php`

Reemplaza el archivo publicado por `ui:auth` con un layout de sidebar:

```blade
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
<div id="app" class="d-flex" style="min-height: 100vh;">

    {{-- Sidebar --}}
    <nav class="bg-dark text-white p-3" style="width: 250px;">
        <a href="{{ route('home') }}" class="text-white text-decoration-none fs-5 fw-bold d-block mb-3">
            {{ config('app.name') }}
        </a>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('home') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('home') }}">Dashboard</a>
            </li>

            @can('clientes.ver')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('clientes.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('clientes.index') }}">Clientes</a>
            </li>
            @endcan

            @can('vehiculos.ver')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('vehiculos.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('vehiculos.index') }}">Vehículos</a>
            </li>
            @endcan

            {{-- Los demás módulos se habilitan en sus sprints --}}
            <li class="nav-item"><span class="nav-link text-white-25 disabled">Espacios</span></li>
            <li class="nav-item"><span class="nav-link text-white-25 disabled">Tarifas</span></li>
            <li class="nav-item"><span class="nav-link text-white-25 disabled">Pensiones</span></li>
            <li class="nav-item"><span class="nav-link text-white-25 disabled">Pagos</span></li>
            <li class="nav-item"><span class="nav-link text-white-25 disabled">Accesos</span></li>
            <li class="nav-item"><span class="nav-link text-white-25 disabled">Incidencias</span></li>
            <li class="nav-item"><span class="nav-link text-white-25 disabled">Reportes</span></li>
        </ul>
    </nav>

    {{-- Contenido --}}
    <div class="flex-grow-1 d-flex flex-column">
        <nav class="navbar navbar-expand navbar-light bg-white shadow-sm px-4">
            <span class="navbar-text me-auto">@yield('title')</span>
            @auth
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        {{ Auth::user()->name }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button class="dropdown-item" type="submit">Cerrar sesión</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endauth
        </nav>

        <main class="p-4 flex-grow-1">
            @include('partials.alerts')
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
```

## 3.3 Alertas `resources/views/partials/alerts.blade.php`

```blade
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
```

## 3.4 Dashboard de relleno `resources/views/home.blade.php`

```blade
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <h6 class="text-muted">Vehículos dentro</h6>
                <h2 class="mb-0">—</h2>
                <small class="text-muted">Sprint 8</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <h6 class="text-muted">Espacios ocupados</h6>
                <h2 class="mb-0">—</h2>
                <small class="text-muted">Sprint 8</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <h6 class="text-muted">Ingresos del día</h6>
                <h2 class="mb-0">$0.00</h2>
                <small class="text-muted">Sprint 8</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <h6 class="text-muted">Ingresos del mes</h6>
                <h2 class="mb-0">$0.00</h2>
                <small class="text-muted">Sprint 8</small>
            </div></div>
        </div>
    </div>
</div>
@endsection
```

---

# FASE 4 — Módulo: Clientes

## 4.1 Generar artefactos

```powershell
php artisan make:model Cliente
php artisan make:controller ClienteController --resource
php artisan make:request ClienteRequest
```

## 4.2 Modelo `app/Models/Cliente.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre', 'apellido_paterno', 'apellido_materno', 'telefono', 'email',
        'direccion', 'ciudad', 'estado', 'codigo_postal', 'rfc',
        'fecha_nacimiento', 'observaciones', 'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido_paterno} {$this->apellido_materno}");
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when($termino, function (Builder $q) use ($termino) {
            $t = "%{$termino}%";
            $q->where(fn (Builder $w) => $w
                ->where('nombre', 'like', $t)
                ->orWhere('apellido_paterno', 'like', $t)
                ->orWhere('apellido_materno', 'like', $t)
                ->orWhere('email', 'like', $t)
                ->orWhere('telefono', 'like', $t)
                ->orWhere('rfc', 'like', $t));
        });
    }
}
```

## 4.3 Validación `app/Http/Requests/ClienteRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // la autorización se aplica en rutas/vistas con @can
    }

    public function rules(): array
    {
        $clienteId = $this->route('cliente')?->id;

        return [
            'nombre'           => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'telefono'         => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'email', 'max:150', 'unique:clientes,email' . ($clienteId ? ",{$clienteId}" : '')],
            'direccion'        => ['nullable', 'string', 'max:255'],
            'ciudad'           => ['nullable', 'string', 'max:100'],
            'estado'           => ['nullable', 'string', 'max:100'],
            'codigo_postal'    => ['nullable', 'string', 'max:10'],
            'rfc'              => ['nullable', 'string', 'max:20'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'observaciones'    => ['nullable', 'string'],
            'activo'           => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido_paterno.required' => 'El apellido paterno es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ya existe un cliente con ese correo.',
        ];
    }
}
```

## 4.4 Controlador `app/Http/Controllers/ClienteController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('clientes.ver');

        $clientes = Cliente::query()
            ->buscar($request->string('q')->toString())
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->withCount('vehiculos')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    public function create()
    {
        $this->authorize('clientes.crear');

        return view('clientes.create');
    }

    public function store(ClienteRequest $request)
    {
        $this->authorize('clientes.crear');

        Cliente::create($request->validated());

        return redirect()->route('clientes.index')->with('success', 'Cliente registrado correctamente.');
    }

    public function edit(Cliente $cliente)
    {
        $this->authorize('clientes.editar');

        return view('clientes.edit', compact('cliente'));
    }

    public function update(ClienteRequest $request, Cliente $cliente)
    {
        $this->authorize('clientes.editar');

        $cliente->update($request->validated());

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente)
    {
        $this->authorize('clientes.eliminar');

        if ($cliente->vehiculos()->exists()) {
            $cliente->update(['activo' => false]);
            $mensaje = 'El cliente tiene vehículos asociados: se desactivó en lugar de eliminarlo.';
        } else {
            $cliente->delete();
            $mensaje = 'Cliente eliminado.';
        }

        return redirect()->route('clientes.index')->with('success', $mensaje);
    }
}
```

> `authorize()` viene del trait `AuthorizesRequests` que ya usa la clase base `Controller`.

## 4.5 Vistas

```powershell
php artisan make:view clientes.index
php artisan make:view clientes.create
php artisan make:view clientes.edit
php artisan make:view clientes._form
```

### `resources/views/clientes/index.blade.php`

```blade
@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Clientes / Propietarios</h4>
    @can('clientes.crear')
        <a href="{{ route('clientes.create') }}" class="btn btn-primary">+ Nuevo cliente</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control"
               placeholder="Buscar por nombre, correo, teléfono o RFC">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Buscar</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th><th>Nombre completo</th><th>Teléfono</th><th>Correo</th>
                <th>RFC</th><th class="text-center">Vehículos</th><th class="text-center">Estado</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($clientes as $cliente)
                <tr>
                    <td>{{ $cliente->id }}</td>
                    <td>{{ $cliente->nombre_completo }}</td>
                    <td>{{ $cliente->telefono ?: '—' }}</td>
                    <td>{{ $cliente->email ?: '—' }}</td>
                    <td>{{ $cliente->rfc ?: '—' }}</td>
                    <td class="text-center">{{ $cliente->vehiculos_count }}</td>
                    <td class="text-center">
                        <span class="badge {{ $cliente->activo ? 'bg-success' : 'bg-secondary' }}">
                            {{ $cliente->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        @can('clientes.editar')
                            <a href="{{ route('clientes.edit', $cliente) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('clientes.eliminar')
                            <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar este cliente?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">
                    No hay clientes registrados{{ request('q') ? ' para «'.request('q').'»' : '' }}.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $clientes->links() }}</div>
@endsection
```

> Si los enlaces de paginación se ven sin estilo, publica las vistas de Laravel y activa Bootstrap 5 en `app/Providers/AppServiceProvider.php`:
> ```php
> use Illuminate\Pagination\Paginator;
>
> public function boot(): void
> {
>     Paginator::useBootstrapFive();
> }
> ```
> Si prefieres no publicar vistas, con `Paginator::useBootstrapFive()` basta (usa la vista por defecto del framework).

### `resources/views/clientes/_form.blade.php`

```blade
@csrf

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input name="nombre" value="{{ old('nombre', $cliente->nombre ?? '') }}"
               class="form-control @error('nombre') is-invalid @enderror" required maxlength="100">
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Apellido paterno <span class="text-danger">*</span></label>
        <input name="apellido_paterno" value="{{ old('apellido_paterno', $cliente->apellido_paterno ?? '') }}"
               class="form-control @error('apellido_paterno') is-invalid @enderror" required maxlength="100">
        @error('apellido_paterno')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Apellido materno</label>
        <input name="apellido_materno" value="{{ old('apellido_materno', $cliente->apellido_materno ?? '') }}"
               class="form-control @error('apellido_materno') is-invalid @enderror" maxlength="100">
        @error('apellido_materno')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input name="telefono" value="{{ old('telefono', $cliente->telefono ?? '') }}"
               class="form-control @error('telefono') is-invalid @enderror" maxlength="30">
        @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Correo</label>
        <input type="email" name="email" value="{{ old('email', $cliente->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror" maxlength="150">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">RFC</label>
        <input name="rfc" value="{{ old('rfc', $cliente->rfc ?? '') }}"
               class="form-control @error('rfc') is-invalid @enderror" maxlength="20">
        @error('rfc')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Dirección</label>
        <input name="direccion" value="{{ old('direccion', $cliente->direccion ?? '') }}"
               class="form-control @error('direccion') is-invalid @enderror" maxlength="255">
        @error('direccion')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Ciudad</label>
        <input name="ciudad" value="{{ old('ciudad', $cliente->ciudad ?? '') }}"
               class="form-control @error('ciudad') is-invalid @enderror" maxlength="100">
        @error('ciudad')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Código postal</label>
        <input name="codigo_postal" value="{{ old('codigo_postal', $cliente->codigo_postal ?? '') }}"
               class="form-control @error('codigo_postal') is-invalid @enderror" maxlength="10">
        @error('codigo_postal')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Fecha de nacimiento</label>
        <input type="date" name="fecha_nacimiento"
               value="{{ old('fecha_nacimiento', isset($cliente) && $cliente->fecha_nacimiento ? $cliente->fecha_nacimiento->format('Y-m-d') : '') }}"
               class="form-control @error('fecha_nacimiento') is-invalid @enderror">
        @error('fecha_nacimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" rows="1"
                  class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $cliente->observaciones ?? '') }}</textarea>
        @error('observaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 form-check ms-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" class="form-check-input" name="activo" value="1" id="activo"
               @checked(old('activo', $cliente->activo ?? true))>
        <label class="form-check-label" for="activo">Activo</label>
    </div>
</div>
```

### `resources/views/clientes/create.blade.php` y `edit.blade.php`

```blade
{{-- create.blade.php --}}
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
```

```blade
{{-- edit.blade.php --}}
@extends('layouts.app')
@section('title', 'Editar cliente')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Editar: {{ $cliente->nombre_completo }}</h5></div>
    <div class="card-body">
        <form action="{{ route('clientes.update', $cliente) }}" method="POST">
            @method('PUT')
            @include('clientes._form')
            <div class="mt-4">
                <button class="btn btn-primary">Actualizar</button>
                <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
```

---

# FASE 5 — Módulo: Vehículos

## 5.1 Generar artefactos

```powershell
php artisan make:model Vehiculo
php artisan make:controller VehiculoController --resource
php artisan make:request VehiculoRequest
php artisan make:view vehiculos.index
php artisan make:view vehiculos.create
php artisan make:view vehiculos.edit
php artisan make:view vehiculos._form
php artisan storage:link
```

## 5.2 Modelo `app/Models/Vehiculo.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehiculo extends Model
{
    use HasFactory;

    protected $table = 'vehiculos';

    protected $fillable = [
        'cliente_id', 'placas', 'marca', 'modelo', 'anio', 'color',
        'tipo_vehiculo_id', 'vin', 'numero_motor', 'foto_url',
        'observaciones', 'activo',
    ];

    protected $casts = [
        'anio' => 'integer',
        'activo' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class);
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when($termino, function (Builder $q) use ($termino) {
            $t = "%{$termino}%";
            $q->where(fn (Builder $w) => $w
                ->where('placas', 'like', $t)
                ->orWhere('marca', 'like', $t)
                ->orWhere('modelo', 'like', $t)
                ->orWhere('vin', 'like', $t));
        });
    }
}
```

**También** crea `app/Models/TipoVehiculo.php` (relación inversa mínima):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoVehiculo extends Model
{
    protected $table = 'tipos_vehiculo';

    protected $fillable = ['nombre', 'descripcion', 'tarifa_base', 'activo'];

    protected $casts = ['tarifa_base' => 'decimal:2', 'activo' => 'boolean'];

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }
}
```

## 5.3 Validación `app/Http/Requests/VehiculoRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vehiculoId = $this->route('vehiculo')?->id;
        $unico = fn (string $campo) => $vehiculoId ? "unique:vehiculos,{$campo},{$vehiculoId}" : "unique:vehiculos,{$campo}";

        return [
            'cliente_id'       => ['required', 'exists:clientes,id'],
            'tipo_vehiculo_id' => ['required', 'exists:tipos_vehiculo,id'],
            'placas'           => ['required', 'string', 'max:20', $unico('placas')],
            'marca'            => ['nullable', 'string', 'max:80'],
            'modelo'           => ['nullable', 'string', 'max:80'],
            'anio'             => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'color'            => ['nullable', 'string', 'max:50'],
            'vin'              => ['nullable', 'string', 'max:50', $unico('vin')],
            'numero_motor'     => ['nullable', 'string', 'max:100'],
            'foto'             => ['nullable', 'image', 'max:2048'],
            'observaciones'    => ['nullable', 'string'],
            'activo'           => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'placas.required'  => 'Las placas son obligatorias.',
            'placas.unique'    => 'Ya existe un vehículo con esas placas.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'tipo_vehiculo_id.exists' => 'El tipo de vehículo no existe.',
            'foto.image'       => 'La fotografía debe ser un archivo de imagen (jpg, png o webp).',
            'foto.max'         => 'La fotografía no puede superar 2 MB.',
        ];
    }
}
```

## 5.4 Controlador `app/Http/Controllers/VehiculoController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehiculoRequest;
use App\Models\Cliente;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Http\Request;

class VehiculoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('vehiculos.ver');

        $vehiculos = Vehiculo::query()
            ->buscar($request->string('q')->toString())
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_vehiculo_id', $request->integer('tipo')))
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->with(['cliente', 'tipoVehiculo'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $tipos = TipoVehiculo::where('activo', true)->orderBy('nombre')->get();

        return view('vehiculos.index', compact('vehiculos', 'tipos'));
    }

    public function create()
    {
        $this->authorize('vehiculos.crear');

        return view('vehiculos.create', $this->combos());
    }

    public function store(VehiculoRequest $request)
    {
        $this->authorize('vehiculos.crear');

        Vehiculo::create($this->guardarFoto($request));

        return redirect()->route('vehiculos.index')->with('success', 'Vehículo registrado correctamente.');
    }

    public function edit(Vehiculo $vehiculo)
    {
        $this->authorize('vehiculos.editar');

        return view('vehiculos.edit', array_merge($this->combos(), compact('vehiculo')));
    }

    public function update(VehiculoRequest $request, Vehiculo $vehiculo)
    {
        $this->authorize('vehiculos.editar');

        $vehiculo->update($this->guardarFoto($request));

        return redirect()->route('vehiculos.index')->with('success', 'Vehículo actualizado correctamente.');
    }

    public function destroy(Vehiculo $vehiculo)
    {
        $this->authorize('vehiculos.eliminar');

        $vehiculo->update(['activo' => false]);

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo desactivado (se conserva el histórico).');
    }

    /** Datos compartidos para los combos del formulario. */
    private function combos(): array
    {
        return [
            'clientes' => Cliente::where('activo', true)->orderBy('apellido_paterno')->get(),
            'tipos'    => TipoVehiculo::where('activo', true)->orderBy('nombre')->get(),
        ];
    }

    /** Sube la foto a storage/app/public/vehiculos y devuelve los datos a guardar. */
    private function guardarFoto(VehiculoRequest $request): array
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $data['foto_url'] = $request->file('foto')->store('vehiculos', 'public');
        }

        unset($data['foto']);

        return $data;
    }
}
```

## 5.5 Vista `vehiculos/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Vehículos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Vehículos</h4>
    @can('vehiculos.crear')
        <a href="{{ route('vehiculos.create') }}" class="btn btn-primary">+ Nuevo vehículo</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control"
               placeholder="Buscar por placa, marca, modelo o VIN">
    </div>
    <div class="col-md-3">
        <select name="tipo" class="form-select">
            <option value="">Todos los tipos</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected(request('tipo') == $t->id)>{{ $t->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Foto</th><th>Placas</th><th>Vehículo</th><th>Tipo</th>
                <th>Propietario</th><th>VIN</th><th class="text-center">Estado</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($vehiculos as $v)
                <tr>
                    <td>
                        @if ($v->foto_url)
                            <img src="{{ asset('storage/'.$v->foto_url) }}" alt="Foto"
                                 class="rounded" style="width:60px;height:45px;object-fit:cover;">
                        @else
                            <span class="text-muted small">Sin foto</span>
                        @endif
                    </td>
                    <td class="fw-bold">{{ $v->placas }}</td>
                    <td>{{ trim(($v->marca ?? '').' '.($v->modelo ?? '')) ?: '—' }} @if($v->anio)<small class="text-muted">({{ $v->anio }})</small>@endif</td>
                    <td>{{ $v->tipoVehiculo?->nombre ?? '—' }}</td>
                    <td>{{ $v->cliente?->nombre_completo ?? '—' }}</td>
                    <td><small>{{ $v->vin ?: '—' }}</small></td>
                    <td class="text-center">
                        <span class="badge {{ $v->activo ? 'bg-success' : 'bg-secondary' }}">
                            {{ $v->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        @can('vehiculos.editar')
                            <a href="{{ route('vehiculos.edit', $v) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('vehiculos.eliminar')
                            <form action="{{ route('vehiculos.destroy', $v) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Desactivar este vehículo?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Desactivar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay vehículos registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $vehiculos->links() }}</div>
@endsection
```

## 5.6 Formulario `vehiculos/_form.blade.php` (campos clave)

```blade
@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Propietario <span class="text-danger">*</span></label>
        <select name="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror" required>
            <option value="">— Seleccione un cliente —</option>
            @foreach ($clientes as $c)
                <option value="{{ $c->id }}" @selected(old('cliente_id', $vehiculo->cliente_id ?? '') == $c->id)>
                    {{ $c->nombre_completo }}
                </option>
            @endforeach
        </select>
        @error('cliente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Placas <span class="text-danger">*</span></label>
        <input name="placas" value="{{ old('placas', $vehiculo->placas ?? '') }}" maxlength="20"
               class="form-control text-uppercase @error('placas') is-invalid @enderror" required>
        @error('placas')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Tipo <span class="text-danger">*</span></label>
        <select name="tipo_vehiculo_id" class="form-select @error('tipo_vehiculo_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected(old('tipo_vehiculo_id', $vehiculo->tipo_vehiculo_id ?? '') == $t->id)>
                    {{ $t->nombre }}
                </option>
            @endforeach
        </select>
        @error('tipo_vehiculo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Marca</label>
        <input name="marca" value="{{ old('marca', $vehiculo->marca ?? '') }}" maxlength="80" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">Modelo</label>
        <input name="modelo" value="{{ old('modelo', $vehiculo->modelo ?? '') }}" maxlength="80" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">Año</label>
        <input type="number" name="anio" min="1950" max="2100"
               value="{{ old('anio', $vehiculo->anio ?? '') }}" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">Color</label>
        <input name="color" value="{{ old('color', $vehiculo->color ?? '') }}" maxlength="50" class="form-control">
    </div>

    <div class="col-md-4">
        <label class="form-label">VIN / Número de serie</label>
        <input name="vin" value="{{ old('vin', $vehiculo->vin ?? '') }}" maxlength="50"
               class="form-control @error('vin') is-invalid @enderror">
        @error('vin')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Número de motor</label>
        <input name="numero_motor" value="{{ old('numero_motor', $vehiculo->numero_motor ?? '') }}" maxlength="100" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label">Fotografía (máx. 2 MB)</label>
        <input type="file" name="foto" accept="image/*"
               class="form-control @error('foto') is-invalid @enderror">
        @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" rows="2" class="form-control">{{ old('observaciones', $vehiculo->observaciones ?? '') }}</textarea>
    </div>

    <div class="col-12 form-check ms-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" class="form-check-input" name="activo" value="1" id="activoV"
               @checked(old('activo', $vehiculo->activo ?? true))>
        <label class="form-check-label" for="activoV">Activo</label>
    </div>
</div>
```

> `create.blade.php` y `edit.blade.php` son idénticos a los de Clientes (§4.5), solo cambian `route('vehiculos.store')` / `route('vehiculos.update', $vehiculo)` y el `@include('vehiculos._form')`.

---

# FASE 6 — Permisos con Spatie (bases desde ya)

Evita rework en el Sprint 7.

## 6.1 Registrar los middlewares

**Archivo:** `app/Http/Kernel.php` → `$middlewareAliases`:

```php
protected $middlewareAliases = [
    // ... los existentes ...
    'role'             => \Spatie\Permission\Middleware\RoleMiddleware::class,
    'permission'       => \Spatie\Permission\Middleware\PermissionMiddleware::class,
    'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
];
```

```powershell
php artisan config:clear
```

## 6.2 Trait en `app/Models/User.php`

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = ['name', 'email', 'password', 'telefono', 'activo'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'activo' => 'boolean',
        'ultimo_acceso' => 'datetime',
    ];
}
```

## 6.3 Seeders

```powershell
php artisan make:seeder RolPermisoSeeder
php artisan make:seeder TipoVehiculoSeeder
```

**`database/seeders/RolPermisoSeeder.php`:**

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolPermisoSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permisos: modulo.accion  (hoja "permisos" del Excel)
        $modulos = [
            'clientes'  => ['ver', 'crear', 'editar', 'eliminar'],
            'vehiculos' => ['ver', 'crear', 'editar', 'eliminar'],
        ];

        foreach ($modulos as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                Permission::firstOrCreate(
                    ['name' => "{$modulo}.{$accion}", 'guard_name' => 'web'],
                    ['modulo' => $modulo, 'accion' => $accion]
                );
            }
        }

        // Roles del Excel
        $roles = [
            'ADMINISTRADOR' => 'Acceso total al sistema',
            'SUPERVISOR'    => 'Supervisa operación y reportes',
            'CAJERO'        => 'Registra cobros y cortes de caja',
            'RECEPCION'     => 'Alta de clientes, vehículos y contratos',
            'SEGURIDAD'     => 'Registra entradas, salidas e incidencias',
        ];

        foreach ($roles as $nombre => $descripcion) {
            Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web'], ['descripcion' => $descripcion]);
        }

        // Reparto de permisos (primer sprint)
        Role::findByName('ADMINISTRADOR')->syncPermissions(Permission::all());

        Role::findByName('RECEPCION')->syncPermissions([
            'clientes.ver', 'clientes.crear', 'clientes.editar',
            'vehiculos.ver', 'vehiculos.crear', 'vehiculos.editar',
        ]);

        Role::findByName('SUPERVISOR')->syncPermissions([
            'clientes.ver', 'vehiculos.ver',
        ]);

        // Usuario admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@pension.mx'],
            ['name' => 'Administrador', 'password' => bcrypt('password'), 'activo' => true]
        );
        $admin->syncRoles(['ADMINISTRADOR']);
    }
}
```

**`database/seeders/TipoVehiculoSeeder.php`:**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoVehiculoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['nombre' => 'Automovil',  'tarifa_base' => 1500],
            ['nombre' => 'Motocicleta','tarifa_base' => 800],
            ['nombre' => 'Camioneta',  'tarifa_base' => 1800],
            ['nombre' => 'SUV',        'tarifa_base' => 1800],
            ['nombre' => 'Camion',     'tarifa_base' => 2500],
        ] as $t) {
            DB::table('tipos_vehiculo')->insert([
                'nombre'       => $t['nombre'],
                'descripcion'  => null,
                'tarifa_base'  => $t['tarifa_base'],
                'activo'       => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }
}
```

**`database/seeders/DatabaseSeeder.php`:**

```php
public function run(): void
{
    $this->call([
        TipoVehiculoSeeder::class,
        RolPermisoSeeder::class,
    ]);
}
```

## 6.4 Ejecutar

```powershell
php artisan migrate:fresh --seed
```

---

# FASE 7 — Verificación final

## Checklist

| # | Verificación | Comando / acción | Esperado |
|---|---|---|---|
| 1 | Driver MySQL | `php -m \| Select-String pdo_mysql` | aparece |
| 2 | Clave de app | revisar `.env` | `APP_KEY=base64:...` |
| 3 | Migraciones | `php artisan migrate:status` | todas en verde |
| 4 | Rutas | `php artisan route:list` | `login`, `logout`, `home`, `clientes.*`, `vehiculos.*` |
| 5 | Login | entrar a `/login` con `admin@pension.mx` | redirige a `/home` |
| 6 | Ruta protegida sin sesión | abre `/clientes` en incógnito | redirige a `/login` |
| 7 | Alta de cliente | crea uno con correo duplicado | muestra error de validación |
| 8 | Listado | busca por nombre | filtra y pagina |
| 9 | Alta de vehículo | placa repetida | error «Ya existe un vehículo con esas placas» |
| 10 | Foto | sube un JPG | aparece en el listado (`storage/vehiculos/...`) |
| 11 | Permisos | entra con un usuario sin `clientes.crear` | no ve el botón y la ruta da 403 |
| 12 | Assets | `npm run build` | sin errores de compilación |

## Orden de ejecución (resumen)

```powershell
# 1. Preparación
#    - editar C:\php\php.ini  ->  extension=pdo_mysql
php artisan key:generate

# 2. Migraciones de este sprint (editar 5 archivos con el codigo de arriba)
php artisan migrate:fresh

# 3. Auth
php artisan ui:auth
Remove-Item database\migrations\2014_10_12_100000_create_password_resets_table.php

# 4. Codigo de los modulos (secciones 3 a 6)
#    routes, layout, modelos, requests, controllers, vistas, seeders

# 5. Base de datos con datos
php artisan migrate:fresh --seed

# 6. Assets y servidor
npm run dev            # en una terminal
php artisan serve      # en otra
```

---

# Apéndice A — Alternativa con Livewire

Si en lugar de controladores + Blade prefieres Livewire (instalado, `v4.4.7`):

```powershell
php artisan make:livewire ClienteIndex
php artisan make:livewire ClienteForm
php artisan make:livewire VehiculoIndex
php artisan make:livewire VehiculoForm
```

- La clase va en `app/Livewire/` y la vista en `resources/views/livewire/`.
- El formulario se vuelve `wire:model` + `#[Validate]` en lugar de `FormRequest`.
- Las rutas quedan simples: `Route::get('clientes', ClienteIndex::class)->name('clientes.index')`.
- **Recomendación:** termina este sprint con Blade (menos piezas móviles) y migra módulo por módulo a Livewire cuando lo necesites.

# Apéndice B — Problemas comunes

| Síntoma | Causa | Solución |
|---|---|---|
| `could not find driver` | `pdo_mysql` deshabilitado | `C:\php\php.ini` → `extension=pdo_mysql` y reinicia la terminal |
| `No application encryption key` | `APP_KEY` vacía | `php artisan key:generate` |
| `Class 'Auth' not found` en rutas | alias no cargado | `php artisan config:clear` |
| `route [home] not defined` | falta la ruta `/home` | revisa `routes/web.php` |
| Imagen no se muestra | falta link de storage | `php artisan storage:link` |
| `storage/...` 404 | link apunta a otra ruta | `php artisan storage:link --force` |
| Estilos de paginación rotos | paginator no configurado | `Paginator::useBootstrapFive()` en `AppServiceProvider::boot()` |
| `Column not found: users.telefono` | migración de users sin editar | §1.3 y `php artisan migrate:fresh` |
| `Unknown column 'modulo' in 'field list'` | falta el ALTER de Spatie | `MIGRACIONES.md` §5.1 |
| `Neither the property ... nor the methods` en `@can` | permisos sin sembrar | `php artisan db:seed --class=RolPermisoSeeder` |
| El botón se ve pero da 403 | solo se ocultó la vista | es correcto: la autorización real está en el controlador |
| `The environment file is invalid! unexpected whitespace` | `APP_NAME` con espacios sin comillas | `APP_NAME="Pension Vehicular"` (§2.4) |
| `vite no se reconoce como comando` | `node_modules` vacío | `npm install` y luego `npm run build` |

---

# Apéndice C — Registro de ejecución (2026-10-07)

Las 7 fases de esta guía **ya fueron ejecutadas** en el proyecto. Desviaciones respecto al texto:

1. **§1.1** `pdo_mysql` ya estaba habilitado en `C:\php\php.ini` (línea 945) al momento de ejecutar.
2. **§1.4–1.7** Las migraciones de `tipos_vehiculo`, `clientes`, `vehiculos` y el ALTER de Spatie ya venían escritas; se corrigieron dos typos: `description`→`descripcion` y `dafault(0)`→`default(0)`. También se arregló el `down()` del ALTER (hacía el mismo `ADD` que el `up()`; ahora hace `dropColumn`/`dropIndex`).
3. **§2.4** `APP_NAME` **debe ir entre comillas** si contiene espacios; dotenv no lo acepta sin ellas.
4. **§3.1** Se añadió `Route::get('/', fn () => redirect(...))` para evitar un 404 en la raíz (no venía en el texto).
5. **§4.5 / §5.5** `Paginator::useBootstrapFive()` se puso directo en `AppServiceProvider` sin publicar vistas de paginación.
6. **§5.6** `vehiculos/create.blade.php` y `vehiculos/edit.blade.php` sí se crearon (el texto solo indicaba que eran iguales a los de Clientes); ambos con `enctype="multipart/form-data"`.
7. **Assets** `node_modules` estaba vacío: fue necesario `npm install` antes de `npm run build`.

### Verificación realizada

| Comprobación | Resultado |
|---|---|
| `php artisan migrate:fresh --seed` | 26 migraciones OK; 5 tipos, 5 roles, 8 permisos, 1 admin |
| `php artisan route:list` | 37 rutas, sin duplicados, `/register` no existe |
| `php artisan storage:link` | creado |
| `npm run build` | OK (solo deprecaciones de Sass de Bootstrap) |
| `GET /` con invitado | 302 → `/login` |
| Login `admin@pension.mx` / `password` | entra a `/home` |
| Alta de cliente + vehículo | OK, relaciones e `@can` funcionan |
| `php -l` en `app/`, `database/seeders/`, `routes/` | sin errores |

Usuario de prueba: **admin@pension.mx** / **password** (rol `ADMINISTRADOR`, todos los permisos).
