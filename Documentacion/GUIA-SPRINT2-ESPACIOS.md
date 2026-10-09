# Guía paso a paso — Sprint 2 (Espacios / Cajones + catálogos)

Guía operativa para construir el **Módulo 4 — Espacios / Cajones** junto con los catálogos de **Zonas** y **Tipos de vehículo**, siguiendo el patrón Blade + Resource Controller ya establecido en `GUIA-LOGIN-MODULO1.md`.

- **Plan:** `PLAN-SCRUM.md` → Sprint 2 (Módulo 4)
- **Esquema:** `MIGRACIONES.md` §5.3 (`zonas`), §5.9 (`espacios`), §5.13 (`asignaciones_espacio`)
- **Estimación:** 34 pts (6 historias)

---

## 0. Alcance

| ID | Historia de usuario | Pts | Fase |
|---|---|---|---|
| US-0201 | Administrar zonas (Pensiones, Visitantes, Motos, Camiones) | 5 | Fase 1 |
| US-0202 | Administrar tipos de vehículo con su tarifa base | 3 | Fase 1 |
| US-0203 | Dar de alta cajones con código único por zona | 8 | Fase 2 |
| US-0204 | Cambiar el estado de un cajón (libre, ocupado, reservado, mantenimiento) | 5 | Fase 2 |
| US-0205 | Ver la lista/mapa de cajones por zona con su estado | 8 | Fase 2 |
| US-0206 | Ver el vehículo asignado actualmente a cada cajón | 5 | Fase 3 |

**Tablas:** `zonas`, `espacios`, `asignaciones_espacio` (solo lectura) + lectura de `contratos_pension`.

### Estado actual (tras Sprint 1)

| Elemento | Estado |
|---|---|
| Migraciones | 26 en `Ran` — `zonas`, `espacios`, `asignaciones_espacio` y `contratos_pension` **ya existen** ✅ |
| Migraciones nuevas | **ninguna necesaria** ✅ |
| Modelos | `Cliente`, `Vehiculo`, `TipoVehiculo`, `User` ✅ — faltan `Zona`, `Espacio`, `AsignacionEspacio`, `ContratoPension` ⚠️ |
| Controladores | `ClienteController`, `VehiculoController` ⚠️ |
| Permisos sembrados | solo `clientes.*` y `vehiculos.*` ⚠️ |
| Sidebar | `Espacios` aparece deshabilitado ⚠️ |

---

## 1. Decisiones del sprint

| Tema | Decisión |
|---|---|
| Migraciones               | **No se crea ninguna.** Las 3 tablas ya fueron migradas en Sprint 0/1. Solo seeders. |
| Estados del cajón         | ENUM de MySQL (valores exactos del Excel): `DISPONIBLE`, `OCUPADO`, `RESERVADO`, `MANTENIMIENTO`, `BLOQUEADO`. El plan llama "libre" a `DISPONIBLE`; `BLOQUEADO` es el quinto valor del modelo. |
| Etiquetas en UI           | Se muestran en español (`Libre`, `Ocupado`…) mediante constantes `ESTADO_LABELS` / `ESTADO_CLASSES` en el modelo; **el valor guardado siempre es el ENUM en inglés**. |
| Unicidad de cajón         | `UNIQUE(zona_id, codigo)` → la validación es por zona, no global. |
| Piso / número             | Informativos (no forman parte de la clave única). |
| Ocupación (US-0206)       | Se lee de `asignaciones_espacio` con `fecha_fin IS NULL`. Esa tabla exige `contrato_pension_id NOT NULL`, así que **el alta de asignaciones es del Sprint 3**; este sprint solo implementa la ruta de lectura (mostrará "—" hasta que existan contratos). |
| Modelo `ContratoPension`  | Se crea **mínimo** (tabla + relación `vehiculo`) solo para poder mostrar el vehículo del cajón; se amplía en Sprint 3. |
| Vista de cajones          | **Sin paginación**: se agrupa por zona como un "mapa" con resumen de estados. Escala esperada de un estacionamiento (decenas/cientos de cajones) ≠ escala de clientes. |
| Permiso nuevo             | `espacios.cambiar_estado` separado de `espacios.editar`, porque US-0204 es de **recepcionista** y US-0203 de **administrador**. |
| `migrate:fresh`           | **No ejecutar** (borraría los datos del Sprint 1). Solo `db:seed --class=...`. |

---

# FASE 1 — Catálogos: Zonas y Tipos de vehículo (US-0201, US-0202)

## 1.1 Generar artefactos

```powershell
php artisan make:model Zona
php artisan make:controller ZonaController --resource
php artisan make:request ZonaRequest

php artisan make:controller TipoVehiculoController --resource
php artisan make:request TipoVehiculoRequest

php artisan make:seeder ZonaSeeder
```

(Las vistas se crean directamente, igual que en el Sprint 1.)

## 1.2 Seeder de zonas — `database/seeders/ZonaSeeder.php`

Catálogo del Excel (comentario de `MIGRACIONES.md` §5.3):

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZonaSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['nombre' => 'A - Pensiones',    'descripcion' => 'Zona principal de pensiones mensuales'],
            ['nombre' => 'B - Visitantes',   'descripcion' => 'Visitantes y corta estancia'],
            ['nombre' => 'C - Motocicletas', 'descripcion' => 'Cajones para motocicletas'],
            ['nombre' => 'D - Camiones',     'descripcion' => 'Camiones y unidades grandes'],
        ] as $z) {
            DB::table('zonas')->updateOrInsert(
                ['nombre' => $z['nombre']],
                $z + ['activo' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
```

> `updateOrInsert` hace el seeder **idempotente**: se puede re-ejecutar sin duplicar.

## 1.3 Extender los permisos — `database/seeders/RolPermisoSeeder.php`

Añade 3 módulos nuevos y el permiso extra `espacios.cambiar_estado`:

```php
$modulos = [
    'clientes'        => ['ver', 'crear', 'editar', 'eliminar'],
    'vehiculos'       => ['ver', 'crear', 'editar', 'eliminar'],
    'zonas'           => ['ver', 'crear', 'editar', 'eliminar'],
    'tipos_vehiculo'  => ['ver', 'crear', 'editar', 'eliminar'],
    'espacios'        => ['ver', 'crear', 'editar', 'eliminar', 'cambiar_estado'],
];
```

Y actualiza el reparto por roles (después del bloque de roles):

```php
Role::findByName('ADMINISTRADOR')->syncPermissions(Permission::all());

Role::findByName('RECEPCION')->syncPermissions([
    // Sprint 1
    'clientes.ver', 'clientes.crear', 'clientes.editar',
    'vehiculos.ver', 'vehiculos.crear', 'vehiculos.editar',
    // Sprint 2
    'zonas.ver', 'tipos_vehiculo.ver',
    'espacios.ver', 'espacios.crear', 'espacios.editar', 'espacios.cambiar_estado',
]);

Role::findByName('SUPERVISOR')->syncPermissions([
    'clientes.ver', 'vehiculos.ver',
    'zonas.ver', 'tipos_vehiculo.ver', 'espacios.ver',
]);

Role::findByName('CAJERO')->syncPermissions([
    'espacios.ver',
]);

Role::findByName('SEGURIDAD')->syncPermissions([
    'espacios.ver',
]);
```

> `firstOrCreate` + `syncPermissions` hacen el seeder **seguro de re-ejecutar**: no duplica permisos ni roles.

## 1.4 Registrar el seeder — `database/seeders/DatabaseSeeder.php`

```php
public function run(): void
{
    $this->call([
        ZonaSeeder::class,          // nuevo
        TipoVehiculoSeeder::class,
        RolPermisoSeeder::class,
    ]);
}
```

## 1.5 Ejecutar los seeders (sin `migrate:fresh`)

```powershell
php artisan db:seed --class=ZonaSeeder
php artisan db:seed --class=RolPermisoSeeder
php artisan config:clear
```

## 1.6 Modelo `app/Models/Zona.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zona extends Model
{
    protected $table = 'zonas';

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function espacios(): HasMany
    {
        return $this->hasMany(Espacio::class);
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when($termino, function (Builder $q) use ($termino) {
            $t = "%{$termino}%";
            $q->where(fn (Builder $w) => $w
                ->where('nombre', 'like', $t)
                ->orWhere('descripcion', 'like', $t));
        });
    }
}
```

## 1.7 Validación `app/Http/Requests/ZonaRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $zonaId = $this->route('zona')?->id;

        return [
            'nombre'      => ['required', 'string', 'max:80',
                              Rule::unique('zonas', 'nombre')->ignore($zonaId)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la zona es obligatorio.',
            'nombre.unique'   => 'Ya existe una zona con ese nombre.',
        ];
    }
}
```

## 1.8 Controlador `app/Http/Controllers/ZonaController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZonaRequest;
use App\Models\Zona;
use Illuminate\Http\Request;

class ZonaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('zonas.ver');

        $zonas = Zona::query()
            ->buscar($request->string('q')->toString())
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->withCount('espacios')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('zonas.index', compact('zonas'));
    }

    public function create()
    {
        $this->authorize('zonas.crear');

        return view('zonas.create');
    }

    public function store(ZonaRequest $request)
    {
        $this->authorize('zonas.crear');

        Zona::create($request->validated());

        return redirect()->route('zonas.index')->with('success', 'Zona registrada correctamente.');
    }

    public function edit(Zona $zona)
    {
        $this->authorize('zonas.editar');

        return view('zonas.edit', compact('zona'));
    }

    public function update(ZonaRequest $request, Zona $zona)
    {
        $this->authorize('zonas.editar');

        $zona->update($request->validated());

        return redirect()->route('zonas.index')->with('success', 'Zona actualizada correctamente.');
    }

    public function destroy(Zona $zona)
    {
        $this->authorize('zonas.eliminar');

        if ($zona->espacios()->exists()) {
            $zona->update(['activo' => false]);
            $mensaje = 'La zona tiene cajones asociados: se desactivó en lugar de eliminarla.';
        } else {
            $zona->delete();
            $mensaje = 'Zona eliminada.';
        }

        return redirect()->route('zonas.index')->with('success', $mensaje);
    }
}
```

## 1.9 Validación `app/Http/Requests/TipoVehiculoRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoVehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipoId = $this->route('tipo')?->id;

        return [
            'nombre'      => ['required', 'string', 'max:80',
                              Rule::unique('tipos_vehiculo', 'nombre')->ignore($tipoId)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'tarifa_base' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'activo'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'      => 'El nombre del tipo es obligatorio.',
            'nombre.unique'        => 'Ya existe un tipo de vehículo con ese nombre.',
            'tarifa_base.required' => 'La tarifa base es obligatoria.',
            'tarifa_base.numeric'  => 'La tarifa base debe ser un importe.',
        ];
    }
}
```

## 1.10 Controlador `app/Http/Controllers/TipoVehiculoController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoVehiculoRequest;
use App\Models\TipoVehiculo;
use Illuminate\Http\Request;

class TipoVehiculoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('tipos_vehiculo.ver');

        $tipos = TipoVehiculo::query()
            ->buscar($request->string('q')->toString())
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->withCount('vehiculos', 'espacios')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('tipos_vehiculo.index', compact('tipos'));
    }

    public function create()
    {
        $this->authorize('tipos_vehiculo.crear');

        return view('tipos_vehiculo.create');
    }

    public function store(TipoVehiculoRequest $request)
    {
        $this->authorize('tipos_vehiculo.crear');

        TipoVehiculo::create($request->validated());

        return redirect()->route('tipos-vehiculo.index')->with('success', 'Tipo de vehículo registrado.');
    }

    public function edit(TipoVehiculo $tipo)
    {
        $this->authorize('tipos_vehiculo.editar');

        return view('tipos_vehiculo.edit', compact('tipo'));
    }

    public function update(TipoVehiculoRequest $request, TipoVehiculo $tipo)
    {
        $this->authorize('tipos_vehiculo.editar');

        $tipo->update($request->validated());

        return redirect()->route('tipos-vehiculo.index')->with('success', 'Tipo de vehículo actualizado.');
    }

    public function destroy(TipoVehiculo $tipo)
    {
        $this->authorize('tipos_vehiculo.eliminar');

        if ($tipo->vehiculos()->exists() || $tipo->espacios()->exists()) {
            $tipo->update(['activo' => false]);
            $mensaje = 'El tipo está en uso: se desactivó en lugar de eliminarlo.';
        } else {
            $tipo->delete();
            $mensaje = 'Tipo de vehículo eliminado.';
        }

        return redirect()->route('tipos-vehiculo.index')->with('success', $mensaje);
    }
}
```

> `TipoVehiculo::buscar()` y la relación `espacios()` se añaden al modelo existente (mismo patrón que `Cliente`):

```php
// en app/Models/TipoVehiculo.php
use Illuminate\Database\Eloquent\Builder;

public function scopeBuscar(Builder $query, ?string $termino): Builder
{
    return $query->when($termino, function (Builder $q) use ($termino) {
        $t = "%{$termino}%";
        $q->where(fn (Builder $w) => $w
            ->where('nombre', 'like', $t)
            ->orWhere('descripcion', 'like', $t));
    });
}

public function espacios(): HasMany
{
    return $this->hasMany(Espacio::class);
}
```

> `withCount('vehiculos', 'espacios')` del controlador depende de **ambas** relaciones (`vehiculos` ya existía). Añámelas antes de probar el listado.

---

# FASE 2 — Cajones (US-0203, US-0204, US-0205)

## 2.1 Generar artefactos

```powershell
php artisan make:model Espacio
php artisan make:controller EspacioController --resource
php artisan make:request EspacioRequest

php artisan make:view espacios.index
php artisan make:view espacios._form
php artisan make:view espacios.create
php artisan make:view espacios.edit
```

## 2.2 Modelo `app/Models/Espacio.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Espacio extends Model
{
    protected $table = 'espacios';

    /** Valores exactos del ENUM de la migración. */
    public const ESTADOS = [
        'DISPONIBLE', 'OCUPADO', 'RESERVADO', 'MANTENIMIENTO', 'BLOQUEADO',
    ];

    /** Texto que se muestra en la UI (el valor guardado sigue siendo el ENUM). */
    public const ESTADO_LABELS = [
        'DISPONIBLE'    => 'Libre',
        'OCUPADO'       => 'Ocupado',
        'RESERVADO'     => 'Reservado',
        'MANTENIMIENTO' => 'Mantenimiento',
        'BLOQUEADO'     => 'Bloqueado',
    ];

    public const ESTADO_CLASSES = [
        'DISPONIBLE'    => 'bg-success',
        'OCUPADO'       => 'bg-danger',
        'RESERVADO'     => 'bg-warning text-dark',
        'MANTENIMIENTO' => 'bg-secondary',
        'BLOQUEADO'     => 'bg-dark',
    ];

    protected $fillable = [
        'zona_id', 'codigo', 'tipo_vehiculo_id', 'estado',
        'numero', 'piso', 'observaciones', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionEspacio::class);
    }

    /**
     * Asignación vigente (fecha_fin IS NULL). La regla de negocio garantiza
     * una sola asignación vigente por cajón.
     */
    public function asignacionVigiente(): BelongsTo
    {
        return $this->belongsTo(AsignacionEspacio::class, 'espacio_id')
            ->whereNull('fecha_fin');
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADO_LABELS[$this->estado] ?? $this->estado;
    }

    public function getEstadoClaseAttribute(): string
    {
        return self::ESTADO_CLASSES[$this->estado] ?? 'bg-secondary';
    }

    public function scopeFiltrar(Builder $query, ?int $zonaId, ?string $estado): Builder
    {
        return $query
            ->when($zonaId, fn (Builder $q) => $q->where('zona_id', $zonaId))
            ->when($estado, fn (Builder $q) => $q->where('estado', $estado));
    }
}
```

> ⚠️ `belongsTo(AsignacionEspacio::class, 'espacio_id')`: la FK está en la tabla **hija** (`asignaciones_espacio.espacio_id`), por eso se pasa explícitamente.

Añade también la relación inversa en `app/Models/TipoVehiculo.php`:

```php
public function espacios(): HasMany
{
    return $this->hasMany(Espacio::class);
}
```

## 2.3 Validación `app/Http/Requests/EspacioRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\Espacio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EspacioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $espacioId = $this->route('espacio')?->id;
        $zonaId    = $this->input('zona_id');

        return [
            'zona_id'          => ['required', 'exists:zonas,id'],
            'codigo'           => ['required', 'string', 'max:30',
                                   Rule::unique('espacios', 'codigo')
                                       ->where('zona_id', $zonaId)
                                       ->ignore($espacioId)],
            'tipo_vehiculo_id' => ['required', 'exists:tipos_vehiculo,id'],
            'estado'           => ['required', Rule::in(Espacio::ESTADOS)],
            'numero'           => ['nullable', 'string', 'max:20'],
            'piso'             => ['nullable', 'string', 'max:20'],
            'observaciones'    => ['nullable', 'string'],
            'activo'           => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'zona_id.required'          => 'Seleccione una zona.',
            'zona_id.exists'            => 'La zona seleccionada no existe.',
            'codigo.required'           => 'El código del cajón es obligatorio.',
            'codigo.unique'             => 'Ya existe un cajón con ese código en esta zona.',
            'tipo_vehiculo_id.required' => 'Seleccione el tipo de vehículo compatible.',
            'tipo_vehiculo_id.exists'   => 'El tipo de vehículo no existe.',
            'estado.in'                 => 'Estado no válido.',
        ];
    }
}
```

> La unicidad por zona se resuelve con `Rule::unique(...)->where('zona_id', ...)`, que replica el `UNIQUE(zona_id, codigo)` de la migración.

## 2.4 Controlador `app/Http/Controllers/EspacioController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\EspacioRequest;
use App\Models\Espacio;
use App\Models\TipoVehiculo;
use App\Models\Zona;
use Illuminate\Http\Request;

class EspacioController extends Controller
{
    /** Mapa/lista de cajones agrupados por zona (US-0205) + resumen de estados. */
    public function index(Request $request)
    {
        $this->authorize('espacios.ver');

        $request->validate([
            'zona'   => ['nullable', 'integer', 'exists:zonas,id'],
            'estado' => ['nullable', 'in:' . implode(',', Espacio::ESTADOS)],
        ]);

        $zona   = $request->integer('zona') ?: null;
        $estado = $request->string('estado')->toString() ?: null;

        $espacios = Espacio::query()
            ->filtrar($zona, $estado)
            ->with(['zona', 'tipoVehiculo', 'asignacionVigiente.contrato.vehiculo'])
            ->where('activo', true)
            ->orderBy('zona_id')
            ->orderBy('codigo')
            ->get()
            ->groupBy(fn (Espacio $e) => $e->zona->nombre);

        $resumen = Espacio::where('activo', true)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $zonas = Zona::where('activo', true)->orderBy('nombre')->get();

        return view('espacios.index', compact('espacios', 'zonas', 'resumen', 'zona', 'estado'));
    }

    public function create()
    {
        $this->authorize('espacios.crear');

        return view('espacios.create', $this->combos());
    }

    public function store(EspacioRequest $request)
    {
        $this->authorize('espacios.crear');

        Espacio::create($request->validated());

        return redirect()->route('espacios.index')->with('success', 'Cajón registrado correctamente.');
    }

    public function edit(Espacio $espacio)
    {
        $this->authorize('espacios.editar');

        return view('espacios.edit', array_merge($this->combos(), compact('espacio')));
    }

    public function update(EspacioRequest $request, Espacio $espacio)
    {
        $this->authorize('espacios.editar');

        $espacio->update($request->validated());

        return redirect()->route('espacios.index')->with('success', 'Cajón actualizado correctamente.');
    }

    public function destroy(Espacio $espacio)
    {
        $this->authorize('espacios.eliminar');

        if ($espacio->asignaciones()->exists() || $espacio->estado === 'OCUPADO') {
            $espacio->update(['activo' => false]);
            $mensaje = 'El cajón está en uso: se desactivó en lugar de eliminarlo.';
        } else {
            $espacio->delete();
            $mensaje = 'Cajón eliminado.';
        }

        return redirect()->route('espacios.index')->with('success', $mensaje);
    }

    /** Cambio rápido de estado desde el mapa (US-0204). */
    public function cambiarEstado(Request $request, Espacio $espacio)
    {
        $this->authorize('espacios.cambiar_estado');

        $request->validate([
            'estado' => ['required', 'in:' . implode(',', Espacio::ESTADOS)],
        ]);

        $espacio->update(['estado' => $request->string('estado')->toString()]);

        return back()->with(
            'success',
            "Cajón {$espacio->codigo}: estado ahora en {$espacio->estado_label}."
        );
    }

    private function combos(): array
    {
        return [
            'zonas' => Zona::where('activo', true)->orderBy('nombre')->get(),
            'tipos' => TipoVehiculo::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}
```

## 2.5 Vistas

### `resources/views/espacios/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Espacios / Cajones')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Cajones</h4>
    @can('espacios.crear')
        <a href="{{ route('espacios.create') }}" class="btn btn-primary">+ Nuevo cajón</a>
    @endcan
</div>

{{-- Resumen de estados (US-0205) --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach (\App\Models\Espacio::ESTADOS as $est)
        <span class="badge {{ \App\Models\Espacio::ESTADO_CLASSES[$est] }} p-2">
            {{ \App\Models\Espacio::ESTADO_LABELS[$est] }}: {{ $resumen[$est] ?? 0 }}
        </span>
    @endforeach
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <select name="zona" class="form-select">
            <option value="">Todas las zonas</option>
            @foreach ($zonas as $z)
                <option value="{{ $z->id }}" @selected($zona == $z->id)>{{ $z->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="estado" class="form-select">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\Espacio::ESTADOS as $est)
                <option value="{{ $est }}" @selected($estado === $est)>{{ \App\Models\Espacio::ESTADO_LABELS[$est] }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
        @if ($zona || $estado)
            <a href="{{ route('espacios.index') }}" class="btn btn-outline-light">Limpiar</a>
        @endif
    </div>
</form>

@if ($espacios->isEmpty())
    <div class="alert alert-light border text-center py-5">
        No hay cajones{{ $zona || $estado ? ' que coincidan con el filtro' : '' }}.
        @can('espacios.crear')
            <a href="{{ route('espacios.create') }}">Da de alta el primero</a>.
        @endcan
    </div>
@endif

@foreach ($espacios as $nombreZona => $grupo)
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>{{ $nombreZona }}</strong>
        <span class="text-muted small">{{ $grupo->count() }} cajones</span>
    </div>
    <div class="card-body d-flex flex-wrap gap-2">
        @foreach ($grupo as $e)
        <div class="border rounded p-2 text-center bg-white" style="width: 195px;">
            <div class="fw-bold">{{ $e->codigo }}</div>
            <small class="text-muted d-block">
                {{ $e->tipoVehiculo?->nombre ?? '—' }}@if ($e->piso) · Piso {{ $e->piso }}@endif
            </small>

            <div class="my-1">
                <span class="badge {{ $e->estado_clase }}">{{ $e->estado_label }}</span>
            </div>

            @if ($e->asignacionVigiente?->contrato?->vehiculo)
                <small class="d-block text-success">
                    {{ $e->asignacionVigiente->contrato->vehiculo->placas }}
                </small>
                <small class="d-block text-muted">
                    {{ $e->asignacionVigiente->contrato->vehiculo->cliente?->nombre_completo ?? '' }}
                </small>
            @else
                <small class="d-block text-muted">Sin asignación vigente</small>
            @endif

            @can('espacios.cambiar_estado')
            <form action="{{ route('espacios.estado', $e) }}" method="POST" class="mt-1">
                @csrf @method('PATCH')
                <select name="estado" class="form-select form-select-sm"
                        onchange="this.form.submit()" aria-label="Cambiar estado del cajón {{ $e->codigo }}">
                    @foreach (\App\Models\Espacio::ESTADOS as $op)
                        <option value="{{ $op }}" @selected($e->estado === $op)>
                            {{ \App\Models\Espacio::ESTADO_LABELS[$op] }}
                        </option>
                    @endforeach
                </select>
            </form>
            @endcan

            @can('espacios.editar')
                <a href="{{ route('espacios.edit', $e) }}"
                   class="btn btn-sm btn-outline-primary mt-1 w-100">Editar</a>
            @endcan
        </div>
        @endforeach
    </div>
</div>
@endforeach
@endsection
```

> El `select` de estado envía el formulario solo (`onchange="this.form.submit()"`) — sin JavaScript adicional. Si el usuario no tiene `espacios.cambiar_estado`, ni siquiera se renderiza.

### `resources/views/espacios/_form.blade.php`

```blade
@csrf

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Zona <span class="text-danger">*</span></label>
        <select name="zona_id" class="form-select @error('zona_id') is-invalid @enderror" required>
            <option value="">— Seleccione una zona —</option>
            @foreach ($zonas as $z)
                <option value="{{ $z->id }}" @selected(old('zona_id', $espacio->zona_id ?? '') == $z->id)>
                    {{ $z->nombre }}
                </option>
            @endforeach
        </select>
        @error('zona_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Código <span class="text-danger">*</span></label>
        <input name="codigo" value="{{ old('codigo', $espacio->codigo ?? '') }}" maxlength="30"
               class="form-control text-uppercase @error('codigo') is-invalid @enderror"
               placeholder="Ej. A-01" required>
        @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Único dentro de la zona.</small>
    </div>

    <div class="col-md-4">
        <label class="form-label">Tipo compatible <span class="text-danger">*</span></label>
        <select name="tipo_vehiculo_id" class="form-select @error('tipo_vehiculo_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected(old('tipo_vehiculo_id', $espacio->tipo_vehiculo_id ?? '') == $t->id)>
                    {{ $t->nombre }}
                </option>
            @endforeach
        </select>
        @error('tipo_vehiculo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Estado <span class="text-danger">*</span></label>
        <select name="estado" class="form-select @error('estado') is-invalid @enderror" required>
            @foreach (\App\Models\Espacio::ESTADOS as $op)
                <option value="{{ $op }}" @selected(old('estado', $espacio->estado ?? 'DISPONIBLE') === $op)>
                    {{ \App\Models\Espacio::ESTADO_LABELS[$op] }}
                </option>
            @endforeach
        </select>
        @error('estado')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Número</label>
        <input name="numero" value="{{ old('numero', $espacio->numero ?? '') }}" maxlength="20" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">Piso</label>
        <input name="piso" value="{{ old('piso', $espacio->piso ?? '') }}" maxlength="20" class="form-control" placeholder="PB, 1, 2…">
    </div>

    <div class="col-12">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" rows="2" class="form-control">{{ old('observaciones', $espacio->observaciones ?? '') }}</textarea>
    </div>

    <div class="col-12 form-check ms-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" class="form-check-input" name="activo" value="1" id="activoE"
               @checked(old('activo', $espacio->activo ?? true))>
        <label class="form-check-label" for="activoE">Activo</label>
    </div>
</div>
```

### `resources/views/espacios/create.blade.php`

```blade
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
```

### `resources/views/espacios/edit.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Editar cajón')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Editar cajón: {{ $espacio->codigo }} ({{ $espacio->zona?->nombre }})</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('espacios.update', $espacio) }}" method="POST">
            @method('PUT')
            @include('espacios._form')
            <div class="mt-4">
                <button class="btn btn-primary">Actualizar</button>
                <a href="{{ route('espacios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
```

---

# FASE 3 — Ocupación: vehículo vigente del cajón (US-0206)

## 3.1 Modelos mínimos

**`app/Models/AsignacionEspacio.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionEspacio extends Model
{
    protected $table = 'asignaciones_espacio';

    protected $fillable = [
        'contrato_pension_id', 'espacio_id', 'fecha_inicio',
        'fecha_fin', 'motivo', 'activo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
        'activo'       => 'boolean',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoPension::class, 'contrato_pension_id');
    }

    public function espacio(): BelongsTo
    {
        return $this->belongsTo(Espacio::class);
    }
}
```

**`app/Models/ContratoPension.php`** *(mínimo — Sprint 3 lo amplía con tarifas, estados y renovaciones)*

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContratoPension extends Model
{
    protected $table = 'contratos_pension';

    protected $fillable = [
        'cliente_id', 'vehiculo_id', 'tarifa_id', 'numero_contrato',
        'fecha_inicio', 'fecha_fin', 'precio', 'periodicidad',
        'dia_cobro', 'estado', 'deposito', 'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
        'precio'       => 'decimal:2',
        'deposito'     => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionEspacio::class);
    }
}
```

> Se omiten deliberadamente `tarifa()` y los estados de contrato: se agregan en **Sprint 3**. No hace falta re-migrar.

## 3.2 Cómo se resuelve en la vista

`Espacio::asignacionVigiente()` (Fase 2.2) + `asignacionVigiente.contrato.vehiculo` en el `with()` del controlador:

```
espacios ──< asignaciones_espacio >── contratos_pension ──< vehiculos >── clientes
```

La vista muestra `placas` + propietario cuando hay asignación vigente; en caso contrario **"Sin asignación vigente"**.

> **Importante:** `asignaciones_espacio.contrato_pension_id` es `NOT NULL`, así que *no* se puede asignar un vehículo a un cajón sin contrato. El alta de asignaciones llega con el **Sprint 3** (US-0304). Hasta entonces la columna verá siempre "Sin asignación vigente" — la ruta de lectura ya queda probada.

---

# FASE 4 — Navegación y rutas

## 4.1 Rutas — `routes/web.php`

Añade al bloque `Route::middleware(['auth'])`:

```php
use App\Http\Controllers\EspacioController;
use App\Http\Controllers\TipoVehiculoController;
use App\Http\Controllers\ZonaController;

// ... dentro del grupo auth ...

Route::resource('zonas', ZonaController::class)->except(['show']);
Route::resource('tipos-vehiculo', TipoVehiculoController::class)
    ->parameters(['tipos-vehiculo' => 'tipo'])   // parámetro {tipo} en lugar de {tipos_vehiculo}
    ->except(['show']);
Route::resource('espacios', EspacioController::class)->except(['show']);
Route::patch('espacios/{espacio}/estado', [EspacioController::class, 'cambiarEstado'])
    ->name('espacios.estado');
```

> `->parameters(['tipos-vehiculo' => 'tipo'])` evita la firma incómoda `edit(TipoVehiculo $tipos_vehiculo)`; con esto basta `edit(TipoVehiculo $tipo)`.
> La ruta `espacios.estado` se declara **después** del `resource` para que `{espacio}/estado` no choque con `{espacio}/edit`.

Verifica:

```powershell
php artisan route:list
```

Esperado: `tipos-vehiculo.index`, `tipos-vehiculo.edit` (parámetro `{tipo}`), `espacios.estado`.

## 4.2 Sidebar — `resources/views/layouts/app.blade.php`

Reemplaza el ítem deshabilitado `Espacios` por un submenú colapsable con los 3 accesos:

```blade
<li class="nav-item">
    <a class="nav-link d-flex justify-content-between align-items-center
              {{ request()->routeIs('espacios.*', 'zonas.*', 'tipos-vehiculo.*') ? 'text-white fw-bold' : 'text-white-50' }}"
       data-bs-toggle="collapse" href="#menuEspacios" role="button"
       aria-expanded="{{ request()->routeIs('espacios.*', 'zonas.*', 'tipos-vehiculo.*') ? 'true' : 'false' }}">
        Espacios
        <span class="small">&#9662;</span>
    </a>
    <div class="collapse {{ request()->routeIs('espacios.*', 'zonas.*', 'tipos-vehiculo.*') ? 'show' : '' }}"
         id="menuEspacios">
        <ul class="nav flex-column ps-3">
            @can('espacios.ver')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('espacios.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('espacios.index') }}">Cajones</a>
            </li>
            @endcan
            @can('zonas.ver')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('zonas.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('zonas.index') }}">Zonas</a>
            </li>
            @endcan
            @can('tipos_vehiculo.ver')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('tipos-vehiculo.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('tipos-vehiculo.index') }}">Tipos de vehículo</a>
            </li>
            @endcan
        </ul>
    </div>
</li>
```

Y **borra** la línea:

```blade
<li class="nav-item"><span class="nav-link disabled text-white-50">Espacios</span></li>
```

> Los ítems `Tarifas`, `Pensiones`, `Pagos`, `Accesos`, `Incidencias` y `Reportes` se siguen mostrando deshabilitados hasta sus sprints.

---

# FASE 5 — Vistas de los catálogos

Patrón idéntico al de Clientes (`GUIA-LOGIN-MODULO1.md` §4.5). Solo cambian campos y rutas.

## 5.1 `resources/views/zonas/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Zonas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Zonas del predio</h4>
    @can('zonas.crear')
        <a href="{{ route('zonas.create') }}" class="btn btn-primary">+ Nueva zona</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar zona">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Buscar</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr><th>#</th><th>Nombre</th><th>Descripción</th><th class="text-center">Cajones</th>
                <th class="text-center">Estado</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($zonas as $z)
                <tr>
                    <td>{{ $z->id }}</td>
                    <td class="fw-bold">{{ $z->nombre }}</td>
                    <td>{{ $z->descripcion ?: '—' }}</td>
                    <td class="text-center">{{ $z->espacios_count }}</td>
                    <td class="text-center">
                        <span class="badge {{ $z->activo ? 'bg-success' : 'bg-secondary' }}">
                            {{ $z->activo ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        @can('zonas.editar')
                            <a href="{{ route('zonas.edit', $z) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('zonas.eliminar')
                            <form action="{{ route('zonas.destroy', $z) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar esta zona?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No hay zonas registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $zonas->links() }}</div>
@endsection
```

**`_form.blade.php`** (campos: `nombre`*, `descripcion`, `activo`), **`create.blade.php`** y **`edit.blade.php`** → copiar los de `resources/views/clientes/` y sustituir `clientes` por `zonas` y `$cliente` por `$zona`.

## 5.2 `resources/views/tipos_vehiculo/index.blade.php`

Misma tabla que zonas, con columnas: `#`, `Nombre`, `Descripción`, **`Tarifa base`** (`$t->tarifa_base`), `Vehículos` (`vehiculos_count`), `Cajones` (`espacios_count`), `Estado`, acciones.

- Rutas: `route('tipos-vehiculo.index')`, `route('tipos-vehiculo.create')`, `route('tipos-vehiculo.edit', $t)`, `route('tipos-vehiculo.destroy', $t)`.
- Permisos: `tipos_vehiculo.*`.
- `_form.blade.php`: `nombre`*, `descripcion`, `tarifa_base`* (`type="number" step="0.01" min="0"`), `activo`.

> `create.blade.php` / `edit.blade.php` de ambos catálogos: copiar los de `clientes/` y cambiar rutas + `@include`.

---

# FASE 6 — Ejecución y verificación

## 6.1 Orden de ejecución

```powershell
# 1. Artefactos
php artisan make:model Zona
php artisan make:controller ZonaController --resource
php artisan make:request ZonaRequest
php artisan make:controller TipoVehiculoController --resource
php artisan make:request TipoVehiculoRequest
php artisan make:seeder ZonaSeeder
php artisan make:model Espacio
php artisan make:controller EspacioController --resource
php artisan make:request EspacioRequest
php artisan make:model AsignacionEspacio
php artisan make:model ContratoPension

# 2. Código (modelos, requests, controladores, vistas)

# 3. Seeders  (NO usar migrate:fresh)
php artisan db:seed --class=ZonaSeeder
php artisan db:seed --class=RolPermisoSeeder
php artisan config:clear

# 4. Rutas y sidebar

# 5. Verificación
php artisan route:list
php artisan view:clear
npm run build
php artisan serve
```

## 6.2 Checklist

- [x] `php artisan db:seed --class=ZonaSeeder` → 4 zonas (`A - Pensiones`, `B - Visitantes`, `C - Motocicletas`, `D - Camiones`).
- [x] `php artisan db:seed --class=RolPermisoSeeder` → 21 permisos (8 previos + 4 `zonas` + 4 `tipos_vehiculo` + 5 `espacios`) y 5 roles.
- [x] Login con `admin@pension.mx` / `password` → sidebar despliega **Espacios ▾** con Cajones / Zonas / Tipos de vehículo.
- [x] **US-0201:** alta, edición y borrado de una zona; si la zona tiene cajones, el borrado la desactiva en vez de eliminarla.
- [x] **US-0202:** alta de tipo de vehículo con tarifa base; se refleja en el `<select>` del formulario de cajones.
- [x] **US-0203:** dar de alta 2 cajones con el **mismo código en zonas distintas** → permitido; el mismo código en la **misma zona** → error `Ya existe un cajón con ese código en esta zona.`
- [x] **US-0204:** cambiar el estado desde el `<select>` del mapa → SweetAlert de éxito y badge actualizado.
- [x] **US-0205:** el mapa agrupa por zona y la franja de resumen cuenta Libre/Ocupado/Reservado/Mantenimiento/Bloqueado.
- [x] **US-0206:** cada cajón muestra "Sin asignación vigente" (esperado hasta Sprint 3) y no lanza error.
- [x] Usuario con rol `SUPERVISOR` ve el menú y las listas, pero **no** el botón "+ Nuevo cajón" ni el `<select>` de estado.
- [x] Invitado → `/espacios` redirige a `/login`.
- [x] `php -l` sin errores en `app/` y `database/seeders/`.

## 6.3 Matriz de permisos resultante

| Permiso | ADMIN | RECEPCION | SUPERVISOR | CAJERO | SEGURIDAD |
|---|:-:|:-:|:-:|:-:|:-:|
| `zonas.ver` | ✅ | ✅ | ✅ | — | — |
| `zonas.crear/editar/eliminar` | ✅ | — | — | — | — |
| `tipos_vehiculo.ver` | ✅ | ✅ | ✅ | — | — |
| `tipos_vehiculo.crear/editar/eliminar` | ✅ | — | — | — | — |
| `espacios.ver` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `espacios.crear/editar/eliminar` | ✅ | ✅ | — | — | — |
| `espacios.cambiar_estado` | ✅ | ✅ | — | — | — |

---

# Apéndice A — Estados del cajón

| ENUM (BD) | Etiqueta UI | Clase badge | Significado |
|---|---|---|---|
| `DISPONIBLE` | Libre | `bg-success` | Cajón vacío, listo para usar |
| `OCUPADO` | Ocupado | `bg-danger` | Un vehículo lo usa ahora |
| `RESERVADO` | Reservado | `bg-warning text-dark` | Reservado para un contrato futuro |
| `MANTENIMIENTO` | Mantenimiento | `bg-secondary` | Fuera de servicio |
| `BLOQUEADO` | Bloqueado | `bg-dark` | No asignable (señalado, obstruido, etc.) |

> El plan (`PLAN-SCRUM.md` US-0204) menciona 4 estados; el ENUM del Excel tiene 5. La UI cubre los 5.

# Apéndice B — Problemas comunes

| Síntoma | Causa | Solución |
|---|---|---|
| `Column [espacio_id] does not exist` en `asignacionVigiente` | se olvidó el segundo argumento del `belongsTo` | `belongsTo(AsignacionEspacio::class, 'espacio_id')` |
| `Target class [TipoVehiculo] does not exist` | ruta con parámetro mal nombrado | `->parameters(['tipos-vehiculo' => 'tipo'])` (§4.1) |
| `SQLSTATE[23000] duplicate key ... espacios_zona_id_codigo_unique` | la validación no replica la unicidad por zona | `Rule::unique('espacios','codigo')->where('zona_id',...)` (§2.3) |
| `Cannot delete or update a parent row: ... fk_asignaciones_espacio` | se intentó borrar un cajón con historial | el `destroy` desactiva si hay asignaciones (§2.4) |
| El menú Espacios no aparece | permisos no sembrados | `php artisan db:seed --class=RolPermisoSeeder` |
| `Class "App\Models\ContratoPension" not found` | falta el modelo mínimo | §3.1 |
| El select de estado no aparece | el usuario no tiene `espacios.cambiar_estado` | es correcto: revisar la matriz §6.3 |
| `route [tipos-vehiculo.index] not defined` | no se añadieron las rutas | §4.1 |
| Aparecen las 4 zonas duplicadas | se corrió el seeder a mano con `insert` | usa `ZonaSeeder` (idempotente) |

# Apéndice C — Siguiente sprint

**Sprint 3 — Tarifas y Pensiones (Módulos 6 y 7):** `tarifas`, `contratos_pension`, y el **alta** de `asignaciones_espacio` que hace útil la columna "vehículo asignada" de este sprint. Ver `PLAN-SCRUM.md` US-0301…US-0307.

---

# Apéndice D — Registro de ejecución del Sprint 2

**Fecha:** 2026-10-07 · **Resultado:** ✅ Sprint 2 completo y verificado en navegador.

## D.1 Comandos ejecutados

```powershell
php artisan make:model Zona|Espacio|AsignacionEspacio|ContratoPension
php artisan make:controller ZonaController|TipoVehiculoController|EspacioController --resource
php artisan make:request ZonaRequest|TipoVehiculoRequest|EspacioRequest
php artisan make:seeder ZonaSeeder
php artisan db:seed --class=ZonaSeeder        # -> 4 zonas
php artisan db:seed --class=RolPermisoSeeder  # -> 21 permisos, 5 roles
php artisan config:clear
php artisan view:clear
php artisan route:list                        # -> 19 rutas nuevas
npm run build                                 # -> OK (7.57 s)
php -l  (app/ + database/seeders/ + routes/)  # -> sin errores
php artisan serve --port=8000                 # -> activo
```

## D.2 Verificación por historia de usuario

| Historia | Prueba realizada | Resultado |
|---|---|---|
| US-0201 | Alta `E - Temporal`, edición a `E - Temporal Editada`, borrado con 0 cajones → fila eliminada | ✅ |
| US-0201 | Borrado de `A - Pensiones` **con 1 cajón** → no se borra, queda `Inactiva` + flash "La zona tiene cajones asociados: se desactivó en lugar de eliminarla." | ✅ |
| US-0202 | Alta `Bicicleta` ($100) y `Deportivo` ($3,000) → aparecen en el `<select>` de cajones (`Automovil, Camion, Camioneta, Deportivo, Motocicleta, SUV`); ambos borrados después | ✅ |
| US-0203 | `A-01` en zona A **y** `A-01` en zona B → ambas guardadas; repetir `A-01` en zona A → error `Ya existe un cajón con ese código en esta zona.` | ✅ |
| US-0204 | `<select>` auto-submit `Ocupado → Libre` → flash `Cajón A-01: estado ahora en Libre.` + badges `Libre: 2` | ✅ |
| US-0205 | Mapa agrupado (`A - Pensiones` / `B - Visitantes`), franja `Libre/Ocupado/Reservado/Mantenimiento/Bloqueado`, filtros por zona+estado y estado vacío "No hay cajones que coincidan con el filtro." | ✅ |
| US-0206 | Ambos cajones muestran `Sin asignación vigente` sin errores (el alta de asignaciones es del Sprint 3) | ✅ |

## D.3 Verificación de permisos (§6.3)

| Usuario / rol | `/espacios` | `/zonas` | `/tipos-vehiculo` | Notas |
|---|---|---|---|---|
| `admin@pension.mx` (ADMINISTRADOR) | ✅ CRUD + select estado | ✅ CRUD | ✅ CRUD | Submenú Cajones / Zonas / Tipos |
| `supervisor@pension.mx` (SUPERVISOR) | ✅ listado y filtros, **sin** botón "+ Nuevo" ni `<select>` | ✅ listado sin botones; `/zonas/create` → **403** | ✅ listado | sidebar: Clientes, Vehículos, Espacios |
| `cajero@pension.mx` (CAJERO) | ✅ solo lectura, **sin** select ni Editar; `/espacios/create` → **403** | `/zonas` → **403** | `/tipos-vehiculo` → **403** | submenú solo muestra "Cajones" |
| invitado | `/espacios` → 302 a `/login` | 302 | 302 | sidebar sin ítem Espacios |

## D.4 Desviaciones respecto de la guía

1. **Sidebar (`layouts/app.blade.php`)** — el `<li>` padre de **Espacios** se envolvió con `@canany(['espacios.ver', 'zonas.ver', 'tipos_vehiculo.ver'])`: sin eso, invitados y roles sin permisos vean un "Espacios ▾" con el submenú vacío.
2. **`confirm()` de borrado de zona** — el mensaje sigue siendo `¿Eliminar esta zona?` aunque la zona tenga cajones (el controlador desactiva en vez de borrar). El detalle llega en el SweetAlert posterior; puede refinarse en un sprint posterior.
3. **Resumen de estados global** — la franja Libre/Ocupado/… cuenta todo el predio aunque haya un filtro activo (decisión: resumen de cabecera, no del filtro).
4. **Usuarios de prueba creados** — `cajero@pension.mx` y `supervisor@pension.mx` (password `password`) para comprobar §6.3; no se modificó `RolPermisoSeeder` con ellos.
5. **Datos de prueba** — quedan 2 cajones (`A-01` en zona A y `A-01` en zona B, ambos `DISPONIBLE`); los tipos `Bicicleta`/`Deportivo` y la zona `E - Temporal` se crearon y borraron durante la prueba; la zona A se desactivó y **reactivó**.
6. **`php artisan tinker --execute`** falla en PowerShell (`unexpected '='`, se pierden las comillas simples) → la verificación de conteos se hizo con un script PHP temporal que arranca el bootstrap de Laravel.
7. **`npm run build`** se ejecutó aunque solo cambió Blade (sin JS/SCSS), para dejar el manifest reconstruido.

## D.5 Estado final

- Migraciones: **26 en `Ran`** (ninguna nueva, ninguna eliminada).
- Datos: 4 zonas, 5 tipos de vehículo, 2 cajones, 21 permisos, 5 roles, 3 usuarios.
- Rutas: 37 (Sprint 1) + 19 (Sprint 2) = 56 activas; `/register` sigue deshabilitado.
- Servidor: `php artisan serve --port=8000`.
- **Siguiente:** `PLAN-SCRUM.md` Sprint 3 — Tarifas y Pensiones (ver Apéndice C).