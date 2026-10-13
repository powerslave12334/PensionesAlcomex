# Guía paso a paso — Sprint 3 (Tarifas y Pensiones)

Guía operativa para construir el **Módulo 6 — Tarifas** y el **Módulo 7 — Contratos de pensión** (alta, asignación de cajón, estados, expediente y alertas), siguiendo el patrón Blade + Resource Controller de `GUIA-LOGIN-MODULO1.md`, `GUIA-SPRINT2-ESPACIOS.md`.

- **Plan:** `PLAN-SCRUM.md` → Sprint 3 (Módulos 6 y 7)
- **Esquema:** `MIGRACIONES.md` §5.10 (`tarifas`), §5.12 (`contratos_pension`), §5.13 (`asignaciones_espacio`), §5.14 (`pagos`, solo lectura)
- **Estimación:** 48 pts (7 historias) → comprometer **40**

---

## 0. Alcance

| ID | Historia de usuario | Pts | Fase |
|---|---|---|---|
| US-0301 | Administrar tarifas por tipo de vehículo y periodo (hora, día, noche, mensual, anual, evento) con vigencia desde/hasta | 8 | Fase 1 |
| US-0302 | Al crear un contrato solo se muestra la **tarifa vigente** | 3 | Fase 1 + Fase 2 |
| US-0303 | Generar contrato con folio único, cliente, vehículo, fechas, periodicidad y **precio congelado** | 13 | Fase 2 |
| US-0304 | Asignar un cajón al contrato con historial de asignaciones | 8 | Fase 3 |
| US-0305 | Gestionar estados del contrato y **renovarlo** | 8 | Fase 4 |
| US-0306 | Listar/filtrar contratos y ver su expediente (pagos + asignaciones) | 5 | Fase 2 |
| US-0307 | Alertar contratos por vencer (15 días antes) | 3 | Fase 5 |

**Tablas:** `tarifas`, `contratos_pension`, `asignaciones_espacio` (**alta** de asignaciones por fin), lectura de `pagos` (aún vacía, se llena en Sprint 4).

### Estado actual (tras Sprint 2)

| Elemento | Estado |
|---|---|
| Migraciones           | 26 en `Ran` — `tarifas`, `contratos_pension`, `asignaciones_espacio`, `pagos` **ya existen** ✅ |
| Migraciones nuevas    | **ninguna necesaria** ✅ |
| Modelos               | `Cliente`, `Vehiculo`, `TipoVehiculo`, `Zona`, `Espacio`, `AsignacionEspacio` ✅ — `ContratoPension` está **mínimo** (relaciones básicas) ⚠️, falta `Tarifa` ❌ |
| Controladores         | `Cliente`, `Vehiculo`, `Zona`, `TipoVehiculo`, `Espacio` ✅ — faltan `Tarifa`, `ContratoPension` ❌ |
| Permisos sembrados    | 21 (`clientes`, `vehiculos`, `zonas`, `tipos_vehiculo`, `espacios`) ⚠️ — faltan `tarifas` y `contratos` |
| Sidebar               | `Tarifas` y `Pensiones` aparecen deshabilitados ⚠️ |
| Cajones               | 2 cajones de prueba en `DISPONIBLE` (zona A y B) — el módulo de asignaciones ya lee `asignacionVigiente` ✅ |

---

## 1. Decisiones del sprint

| Tema | Decisión |
|---|---|
| Migraciones           | **No se crea ninguna.** Las tablas ya migraron en Sprint 0. Solo seeders. |
| Folio del contrato    | Se **autogenera**: `PEN-AAAA-NNNN` (ej. `PEN-2026-0001`), dentro de una transacción con `lockForUpdate()` y reintento (máx. 3) ante un choque del `UNIQUE`. |
| Estado inicial        | Todo contrato nace en `PENDIENTE`. El cajón asignado mientras tanto queda en `RESERVADO`; al pasar a `ACTIVA` pasa a `OCUPADO`. |
| Precio congelado      | `contratos_pension.precio` guarda el precio al firmar. Cambiar la tarifa después **no** altera contratos existentes (US-0303). |
| Tarifa vigente        | `Tarifa::vigente()` = `activo = 1` **y** (`vigencia_desde` nula o ≤ hoy) **y** (`vigencia_hasta` nula o ≥ hoy). El select del contrato solo lista vigentes y la validación usa `Rule::in(...)` (US-0302). |
| Cajón compatible      | El cajón debe ser `tipo_vehiculo_id` = el del vehículo del contrato y estar `activo`. |
| Liberación del cajón  | Solo `CANCELADA` y `FINALIZADA` cierran la asignación (`fecha_fin`) y devuelven el cajón a `DISPONIBLE`. `VENCIDA` y `SUSPENDIDA` **conservan** el cajón. |
| Asignación            | Alta **opcional** en el formulario de creación (lista solo cajones `DISPONIBLE` etiquetados con su tipo) + gestión completa desde el expediente (`show`). Sin JavaScript: la compatibilidad se valida en el servidor. |
| Estados               | ENUM de MySQL (valores del Excel): `PENDIENTE`, `ACTIVA`, `VENCIDA`, `CANCELADA`, `SUSPENDIDA`, `FINALIZADA`, con matriz de transiciones en el modelo. |
| Renovación            | Extiende `fecha_fin` desde `max(hoy, fecha_fin)` + meses de la periodicidad. Opción opcional de **actualizar precio** a la tarifa vigente del mismo `tipo_tarifa` y tipo de vehículo. |
| Expediente (`show`)   | A diferencia de los demás módulos, contratos **sí** tiene `show`: es el expediente (US-0306) con pagos y asignaciones. |
| `pagos`               | Tabla preparada, **sin altas** en este sprint: la vista muestra "Sin pagos registrados (Sprint 4)". |
| Alerta 15 días        | Sin cron: se calcula en cada carga del Dashboard (`HomeController`) + badge en el index (US-0307). El paso automático a `VENCIDA` queda para reportes/Sprint 8. |
| `migrate:fresh`       | **No ejecutar.** Solo `db:seed --class=...`. |

---

# FASE 1 — Tarifas (US-0301, US-0302)

## 1.1 Generar artefactos

```powershell
php artisan make:model Tarifa
php artisan make:request TarifaRequest
php artisan make:controller TarifaController --resource
php artisan make:seeder TarifaSeeder
```

## 1.2 Seeder de tarifas — `database/seeders/TarifaSeeder.php`

Idempotente (`updateOrInsert`) y **dependiente de `TipoVehiculoSeeder`** (los precios salen de los comentarios de `MIGRACIONES.md` §5.10: auto $1500, moto $800, hora $25, día $180).

```php
<?php

namespace Database\Seeders;

use App\Models\Tarifa;
use App\Models\TipoVehiculo;
use Illuminate\Database\Seeder;

class TarifaSeeder extends Seeder
{
    /** @var array<int, array{nombre:string, tipo:string, periodo:string, precio:float, extra?:array}> */
    private array $tarifas = [
        ['nombre' => 'Mensualidad automovil',  'tipo' => 'Automovil',   'periodo' => 'por mes',      'precio' => 1500.00],
        ['nombre' => 'Mensualidad motocicleta','tipo' => 'Motocicleta', 'periodo' => 'por mes',      'precio' => 800.00],
        ['nombre' => 'Mensualidad SUV',        'tipo' => 'SUV',         'periodo' => 'por mes',      'precio' => 1800.00],
        ['nombre' => 'Mensualidad camioneta',  'tipo' => 'Camioneta',   'periodo' => 'por mes',      'precio' => 1800.00],
        ['nombre' => 'Mensualidad camion',     'tipo' => 'Camion',      'periodo' => 'por mes',      'precio' => 2500.00],
        ['nombre' => 'Hora automovil',         'tipo' => 'Automovil',   'periodo' => 'por hora',     'precio' => 25.00,
         'extra' => ['tipo_tarifa' => 'HORA']],
        ['nombre' => 'Dia automovil',          'tipo' => 'Automovil',   'periodo' => 'por dia',      'precio' => 180.00,
         'extra' => ['tipo_tarifa' => 'DIA']],
        ['nombre' => 'Noche automovil',        'tipo' => 'Automovil',   'periodo' => '22:00 a 06:00','precio' => 120.00,
         'extra' => ['tipo_tarifa' => 'NOCHE', 'hora_inicio' => '22:00', 'hora_fin' => '06:00']],
        ['nombre' => 'Anual automovil',        'tipo' => 'Automovil',   'periodo' => 'por anio',     'precio' => 15000.00,
         'extra' => ['tipo_tarifa' => 'ANUAL']],
        ['nombre' => 'Evento corta estancia',  'tipo' => 'Automovil',   'periodo' => 'por evento',   'precio' => 50.00,
         'extra' => ['tipo_tarifa' => 'EVENTO']],
    ];

    public function run(): void
    {
        foreach ($this->tarifas as $fila) {
            $tipo = TipoVehiculo::where('nombre', $fila['tipo'])->first();

            if (! $tipo) {
                $this->command?->warn("  [omitida] Falta el tipo de vehículo: {$fila['tipo']}");
                continue;
            }

            Tarifa::updateOrInsert(
                ['nombre' => $fila['nombre'], 'tipo_vehiculo_id' => $tipo->id],
                array_merge([
                    'tipo_tarifa'    => 'MENSUAL',
                    'precio'         => $fila['precio'],
                    'periodo'        => $fila['periodo'],
                    'vigencia_desde' => null,
                    'vigencia_hasta' => null,
                    'activo'         => true,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ], $fila['extra'] ?? [])
            );
        }

        $this->command?->info('  Tarifas: ' . Tarifa::count() . ' registradas.');
    }
}
```

## 1.3 Extender los permisos — `database/seeders/RolPermisoSeeder.php`

Añade los dos módulos nuevos al array `$modulos` (21 → **32 permisos**):

```php
        $modulos = [
            'clientes'        => ['ver', 'crear', 'editar', 'eliminar'],
            'vehiculos'       => ['ver', 'crear', 'editar', 'eliminar'],
            'zonas'           => ['ver', 'crear', 'editar', 'eliminar'],
            'tipos_vehiculo'  => ['ver', 'crear', 'editar', 'eliminar'],
            'espacios'        => ['ver', 'crear', 'editar', 'eliminar', 'cambiar_estado'],
            // Sprint 3
            'tarifas'         => ['ver', 'crear', 'editar', 'eliminar'],
            'contratos'       => ['ver', 'crear', 'editar', 'eliminar', 'asignar', 'cambiar_estado', 'renovar'],
        ];
```

Y el reparto por rol (usa `syncPermissions`, así que reemplaza la lista completa de cada rol):

```php
        Role::findByName('ADMINISTRADOR')->syncPermissions(Permission::all());

        Role::findByName('RECEPCION')->syncPermissions([
            // Sprint 1
            'clientes.ver', 'clientes.crear', 'clientes.editar',
            'vehiculos.ver', 'vehiculos.crear', 'vehiculos.editar',
            // Sprint 2
            'zonas.ver', 'tipos_vehiculo.ver',
            'espacios.ver', 'espacios.crear', 'espacios.editar', 'espacios.cambiar_estado',
            // Sprint 3 — genera y asigna cajones al contrato (US-0303, US-0304)
            'tarifas.ver',
            'contratos.ver', 'contratos.crear', 'contratos.editar', 'contratos.asignar',
        ]);

        Role::findByName('SUPERVISOR')->syncPermissions([
            'clientes.ver', 'vehiculos.ver',
            'zonas.ver', 'tipos_vehiculo.ver', 'espacios.ver',
            'tarifas.ver', 'contratos.ver',
        ]);

        Role::findByName('CAJERO')->syncPermissions([
            'espacios.ver', 'contratos.ver',   // Sprint 4 cobrará sobre el contrato
        ]);

        Role::findByName('SEGURIDAD')->syncPermissions([
            'espacios.ver', 'contratos.ver',   // verificar titular del cajón
        ]);
```

## 1.4 Registrar el seeder — `database/seeders/DatabaseSeeder.php`

```php
        $this->call([
            ZonaSeeder::class,
            TipoVehiculoSeeder::class,
            TarifaSeeder::class,
            RolPermisoSeeder::class,
        ]);
```

## 1.5 Ejecutar los seeders (sin `migrate:fresh`)

```powershell
php artisan db:seed --class=TarifaSeeder
php artisan db:seed --class=RolPermisoSeeder
php artisan config:clear
```

## 1.6 Modelo `app/Models/Tarifa.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tarifa extends Model
{
    /** Valores exactos del ENUM de la migración. */
    public const TIPOS = ['HORA', 'DIA', 'NOCHE', 'MENSUAL', 'ANUAL', 'EVENTO'];

    public const TIPO_LABELS = [
        'HORA'    => 'Por hora',
        'DIA'     => 'Por día',
        'NOCHE'   => 'Noche',
        'MENSUAL' => 'Mensual',
        'ANUAL'   => 'Anual',
        'EVENTO'  => 'Evento',
    ];

    protected $table = 'tarifas';

    protected $fillable = [
        'nombre', 'tipo_vehiculo_id', 'tipo_tarifa', 'precio', 'periodo',
        'hora_inicio', 'hora_fin', 'vigencia_desde', 'vigencia_hasta', 'activo',
    ];

    protected $casts = [
        'precio'         => 'decimal:2',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
        'activo'         => 'boolean',
    ];

    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(ContratoPension::class);
    }

    /**
     * US-0302: activa y cuya vigencia incluye a la fecha indicada (hoy por defecto).
     * `vigencia_desde`/`vigencia_hasta` nulas = sin límite por ese lado.
     */
    public function scopeVigente(Builder $query, ?string $fecha = null): Builder
    {
        $fecha = $fecha ?? now()->toDateString();

        return $query
            ->where('activo', true)
            ->where(fn (Builder $q) => $q
                ->whereNull('vigencia_desde')->orWhere('vigencia_desde', '<=', $fecha))
            ->where(fn (Builder $q) => $q
                ->whereNull('vigencia_hasta')->orWhere('vigencia_hasta', '>=', $fecha));
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when($termino, function (Builder $q) use ($termino) {
            $t = "%{$termino}%";
            $q->where(fn (Builder $w) => $w
                ->where('nombre', 'like', $t)
                ->orWhere('periodo', 'like', $t));
        });
    }

    /** ¿Vigente según los datos del propio modelo? (comparación lexicográfica de Y-m-d). */
    public function esVigente(?string $fecha = null): bool
    {
        $fecha = $fecha ?? now()->toDateString();

        if (! $this->activo) {
            return false;
        }
        if ($this->vigencia_desde && $this->vigencia_desde->toDateString() > $fecha) {
            return false;
        }
        if ($this->vigencia_hasta && $this->vigencia_hasta->toDateString() < $fecha) {
            return false;
        }

        return true;
    }

    /** Texto para los <select>: "Mensual · Automovil · $1,500.00". */
    public function getEtiquetaAttribute(): string
    {
        $tipo = self::TIPO_LABELS[$this->tipo_tarifa] ?? $this->tipo_tarifa;

        return sprintf(
            '%s · %s · $%s',
            $tipo,
            $this->tipoVehiculo?->nombre ?? '—',
            number_format((float) $this->precio, 2)
        );
    }
}
```

## 1.7 Validación `app/Http/Requests/TarifaRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\Tarifa;
use Illuminate\Foundation\Http\FormRequest;

class TarifaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el permiso se valida en el controlador
    }

    public function rules(): array
    {
        return [
            'nombre'           => ['required', 'string', 'max:100'],
            'tipo_vehiculo_id' => ['required', 'integer', 'exists:tipos_vehiculo,id'],
            'tipo_tarifa'      => ['required', 'in:' . implode(',', Tarifa::TIPOS)],
            'precio'           => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'periodo'          => ['nullable', 'string', 'max:30'],
            'hora_inicio'      => ['nullable', 'date_format:H:i'],
            'hora_fin'         => ['nullable', 'date_format:H:i', 'after_or_equal:hora_inicio'],
            'vigencia_desde'   => ['nullable', 'date'],
            'vigencia_hasta'   => ['nullable', 'date', 'after_or_equal:vigencia_desde'],
            'activo'           => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'            => 'El nombre de la tarifa es obligatorio.',
            'nombre.max'                 => 'El nombre no puede superar los 100 caracteres.',
            'tipo_vehiculo_id.required'  => 'Selecciona el tipo de vehículo.',
            'tipo_vehiculo_id.exists'    => 'El tipo de vehículo seleccionado no existe.',
            'tipo_tarifa.required'       => 'Selecciona el periodo de la tarifa.',
            'tipo_tarifa.in'             => 'El periodo seleccionado no es válido.',
            'precio.required'            => 'El precio es obligatorio.',
            'precio.numeric'             => 'El precio debe ser un número.',
            'precio.min'                 => 'El precio no puede ser negativo.',
            'hora_fin.after_or_equal'    => 'La hora final debe ser igual o posterior a la inicial.',
            'vigencia_hasta.after_or_equal' => 'La vigencia final debe ser igual o posterior al inicio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre'           => 'nombre de la tarifa',
            'tipo_vehiculo_id' => 'tipo de vehículo',
            'tipo_tarifa'      => 'periodo',
            'precio'           => 'precio',
            'periodo'          => 'descripción del período',
            'hora_inicio'      => 'hora inicial',
            'hora_fin'         => 'hora final',
            'vigencia_desde'   => 'vigencia desde',
            'vigencia_hasta'   => 'vigencia hasta',
        ];
    }
}
```

## 1.8 Controlador `app/Http/Controllers/TarifaController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\TarifaRequest;
use App\Models\Tarifa;
use App\Models\TipoVehiculo;
use Illuminate\Http\Request;

class TarifaController extends Controller
{
    /** US-0301: listado con filtros. */
    public function index(Request $request)
    {
        $this->authorize('tarifas.ver');

        $request->validate([
            'q'       => ['nullable', 'string', 'max:100'],
            'tipo'    => ['nullable', 'integer', 'exists:tipos_vehiculo,id'],
            'periodo' => ['nullable', 'in:' . implode(',', Tarifa::TIPOS)],
            'vigentes'=> ['nullable', 'boolean'],
        ]);

        $q       = $request->string('q')->toString() ?: null;
        $tipo    = $request->integer('tipo') ?: null;
        $periodo = $request->string('periodo')->toString() ?: null;
        $vigentes = $request->boolean('vigentes');

        $tarifas = Tarifa::query()
            ->buscar($q)
            ->when($tipo, fn ($query) => $query->where('tipo_vehiculo_id', $tipo))
            ->when($periodo, fn ($query) => $query->where('tipo_tarifa', $periodo))
            ->when($vigentes, fn ($query) => $query->vigente())
            ->with('tipoVehiculo')
            ->orderBy('tipo_tarifa')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $tipos = TipoVehiculo::where('activo', true)->orderBy('nombre')->get();

        return view('tarifas.index', compact('tarifas', 'tipos', 'q', 'tipo', 'periodo', 'vigentes'));
    }

    public function create()
    {
        $this->authorize('tarifas.crear');

        return view('tarifas.create', $this->combos());
    }

    public function store(TarifaRequest $request)
    {
        $this->authorize('tarifas.crear');

        Tarifa::create($request->validated());

        return redirect()->route('tarifas.index')->with('success', 'Tarifa registrada correctamente.');
    }

    public function edit(Tarifa $tarifa)
    {
        $this->authorize('tarifas.editar');

        return view('tarifas.edit', array_merge($this->combos(), compact('tarifa')));
    }

    public function update(TarifaRequest $request, Tarifa $tarifa)
    {
        $this->authorize('tarifas.editar');

        $tarifa->update($request->validated());

        return redirect()->route('tarifas.index')->with('success', 'Tarifa actualizada correctamente.');
    }

    /** Desactiva en vez de borrar si ya se usó en contratos (la FK es restrict). */
    public function destroy(Tarifa $tarifa)
    {
        $this->authorize('tarifas.eliminar');

        if ($tarifa->contratos()->exists()) {
            $tarifa->update(['activo' => false]);
            $mensaje = 'La tarifa tiene contratos asociados: se desactivó en lugar de eliminarla.';
        } else {
            $tarifa->delete();
            $mensaje = 'Tarifa eliminada.';
        }

        return redirect()->route('tarifas.index')->with('success', $mensaje);
    }

    private function combos(): array
    {
        return [
            'tipos' => TipoVehiculo::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}
```

## 1.9 Vistas de tarifas

### `resources/views/tarifas/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Tarifas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Tarifas</h4>
    @can('tarifas.crear')
        <a href="{{ route('tarifas.create') }}" class="btn btn-primary">+ Nueva tarifa</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por nombre">
    </div>
    <div class="col-md-3">
        <select name="tipo" class="form-select" aria-label="Tipo de vehículo">
            <option value="">Todos los tipos</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected($tipo == $t->id)>{{ $t->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="periodo" class="form-select" aria-label="Periodo">
            <option value="">Todos los periodos</option>
            @foreach (\App\Models\Tarifa::TIPOS as $p)
                <option value="{{ $p }}" @selected($periodo === $p)>{{ \App\Models\Tarifa::TIPO_LABELS[$p] }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="vigentes" value="1" id="vigentes"
                   @checked($vigentes)>
            <label class="form-check-label" for="vigentes">Solo vigentes</label>
        </div>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
        @if ($q || $tipo || $periodo || $vigentes)
            <a href="{{ route('tarifas.index') }}" class="btn btn-outline-light">Limpiar</a>
        @endif
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Nombre</th><th>Tipo vehículo</th><th>Periodo</th>
                <th class="text-end">Precio</th><th>Horario</th>
                <th>Vigencia</th><th class="text-center">Estado</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($tarifas as $tarifa)
                <tr>
                    <td class="fw-bold">{{ $tarifa->nombre }}</td>
                    <td>{{ $tarifa->tipoVehiculo?->nombre ?? '—' }}</td>
                    <td><span class="badge bg-light text-dark border">{{ \App\Models\Tarifa::TIPO_LABELS[$tarifa->tipo_tarifa] }}</span></td>
                    <td class="text-end">${{ number_format((float) $tarifa->precio, 2) }}
                        <small class="text-muted d-block">{{ $tarifa->periodo ?: '' }}</small></td>
                    <td>{{ $tarifa->hora_inicio ? $tarifa->hora_inicio . ' - ' . $tarifa->hora_fin : '—' }}</td>
                    <td class="small">
                        {{ $tarifa->vigencia_desde?->format('d/m/Y') ?? 'abierta' }}
                        —
                        {{ $tarifa->vigencia_hasta?->format('d/m/Y') ?? 'abierta' }}
                    </td>
                    <td class="text-center">
                        @if ($tarifa->esVigente())
                            <span class="badge bg-success">Vigente</span>
                        @elseif (! $tarifa->activo)
                            <span class="badge bg-secondary">Inactiva</span>
                        @else
                            <span class="badge bg-warning text-dark">No vigente</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @can('tarifas.editar')
                            <a href="{{ route('tarifas.edit', $tarifa) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('tarifas.eliminar')
                            <form action="{{ route('tarifas.destroy', $tarifa) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar esta tarifa?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay tarifas registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $tarifas->links() }}</div>
@endsection
```

### `resources/views/tarifas/_form.blade.php`

```blade
@csrf

<div class="row g-3">
    <div class="col-md-5">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input name="nombre" value="{{ old('nombre', $tarifa->nombre ?? '') }}" maxlength="100"
               class="form-control @error('nombre') is-invalid @enderror"
               placeholder="Ej. Mensualidad automovil" required>
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Tipo de vehículo <span class="text-danger">*</span></label>
        <select name="tipo_vehiculo_id" class="form-select @error('tipo_vehiculo_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected(old('tipo_vehiculo_id', $tarifa->tipo_vehiculo_id ?? '') == $t->id)>
                    {{ $t->nombre }}
                </option>
            @endforeach
        </select>
        @error('tipo_vehiculo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Periodo <span class="text-danger">*</span></label>
        <select name="tipo_tarifa" class="form-select @error('tipo_tarifa') is-invalid @enderror" required>
            @foreach (\App\Models\Tarifa::TIPOS as $p)
                <option value="{{ $p }}" @selected(old('tipo_tarifa', $tarifa->tipo_tarifa ?? 'MENSUAL') === $p)>
                    {{ \App\Models\Tarifa::TIPO_LABELS[$p] }}
                </option>
            @endforeach
        </select>
        @error('tipo_tarifa')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Precio <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="precio" step="0.01" min="0" max="9999999999.99"
                   value="{{ old('precio', $tarifa->precio ?? '') }}"
                   class="form-control @error('precio') is-invalid @enderror" required>
            @error('precio')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Descripción</label>
        <input name="periodo" value="{{ old('periodo', $tarifa->periodo ?? '') }}" maxlength="30"
               class="form-control @error('periodo') is-invalid @enderror" placeholder="por mes">
        @error('periodo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Hora inicial</label>
        <input type="time" name="hora_inicio" value="{{ old('hora_inicio', $tarifa->hora_inicio ?? '') }}"
               class="form-control @error('hora_inicio') is-invalid @enderror">
        @error('hora_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Hora final</label>
        <input type="time" name="hora_fin" value="{{ old('hora_fin', $tarifa->hora_fin ?? '') }}"
               class="form-control @error('hora_fin') is-invalid @enderror">
        @error('hora_fin')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Vigencia desde</label>
        <input type="date" name="vigencia_desde" value="{{ old('vigencia_desde', optional($tarifa->vigencia_desde ?? null)->format('Y-m-d')) }}"
               class="form-control @error('vigencia_desde') is-invalid @enderror">
        @error('vigencia_desde')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Vigencia hasta</label>
        <input type="date" name="vigencia_hasta" value="{{ old('vigencia_hasta', optional($tarifa->vigencia_hasta ?? null)->format('Y-m-d')) }}"
               class="form-control @error('vigencia_hasta') is-invalid @enderror">
        @error('vigencia_hasta')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 form-check ms-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" class="form-check-input" name="activo" value="1" id="activoT"
               @checked(old('activo', $tarifa->activo ?? true))>
        <label class="form-check-label" for="activoT">Activa</label>
    </div>
</div>
```

> `optional(null)->format(...)` devuelve `null` sin errores; cuando hay valor formatea `Y-m-d` para el `input[type=date]`.

### `resources/views/tarifas/create.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Nueva tarifa')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Nueva tarifa</h5></div>
    <div class="card-body">
        <form action="{{ route('tarifas.store') }}" method="POST">
            @include('tarifas._form')
            <div class="mt-4">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('tarifas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
```

### `resources/views/tarifas/edit.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Editar tarifa')
@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0">Editar: {{ $tarifa->nombre }}</h5></div>
    <div class="card-body">
        <form action="{{ route('tarifas.update', $tarifa) }}" method="POST">
            @method('PUT')
            @include('tarifas._form')
            <div class="mt-4">
                <button class="btn btn-primary">Actualizar</button>
                <a href="{{ route('tarifas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
```

<!-- SIGUE FASE 2 -->

# FASE 2 — Contratos: alta, listado y expediente (US-0302, US-0303, US-0306)

## 2.1 Generar artefactos

```powershell
php artisan make:request ContratoPensionRequest
php artisan make:controller ContratoPensionController --resource --model=ContratoPension
```

## 2.2 Modelo `app/Models/ContratoPension.php` (ampliar el mínimo del Sprint 2)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContratoPension extends Model
{
    /** Valores exactos del ENUM de la migración. */
    public const ESTADOS = [
        'PENDIENTE', 'ACTIVA', 'VENCIDA', 'CANCELADA', 'SUSPENDIDA', 'FINALIZADA',
    ];

    public const ESTADO_LABELS = [
        'PENDIENTE'  => 'Pendiente',
        'ACTIVA'     => 'Activa',
        'VENCIDA'    => 'Vencida',
        'CANCELADA'  => 'Cancelada',
        'SUSPENDIDA' => 'Suspendida',
        'FINALIZADA' => 'Finalizada',
    ];

    public const ESTADO_CLASSES = [
        'PENDIENTE'  => 'bg-secondary',
        'ACTIVA'     => 'bg-success',
        'VENCIDA'    => 'bg-danger',
        'CANCELADA'  => 'bg-dark',
        'SUSPENDIDA' => 'bg-warning text-dark',
        'FINALIZADA' => 'bg-info text-dark',
    ];

    public const PERIODICIDADES = ['MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL'];

    public const PERIODICIDAD_LABELS = [
        'MENSUAL'    => 'Mensual',
        'TRIMESTRAL' => 'Trimestral',
        'SEMESTRAL'  => 'Semestral',
        'ANUAL'      => 'Anual',
    ];

    /** Meses que agrega cada periodicidad (usado por la renovación, US-0305). */
    public const MESES = [
        'MENSUAL' => 1, 'TRIMESTRAL' => 3, 'SEMESTRAL' => 6, 'ANUAL' => 12,
    ];

    /** Días de aviso previos al vencimiento (US-0307). */
    public const DIAS_AVISO = 15;

    /** Matriz de transiciones permitidas (US-0305). Vacío = estado final. */
    public const TRANSICIONES = [
        'PENDIENTE'  => ['ACTIVA', 'CANCELADA'],
        'ACTIVA'     => ['VENCIDA', 'SUSPENDIDA', 'CANCELADA', 'FINALIZADA'],
        'VENCIDA'    => ['ACTIVA', 'CANCELADA', 'FINALIZADA'],
        'SUSPENDIDA' => ['ACTIVA', 'CANCELADA', 'FINALIZADA'],
        'CANCELADA'  => [],
        'FINALIZADA' => [],
    ];

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

    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'contrato_pension_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionEspacio::class, 'contrato_pension_id');
    }

    /** Asignación vigente (fecha_fin IS NULL). */
    public function asignacionVigiente(): BelongsTo
    {
        return $this->belongsTo(AsignacionEspacio::class, 'contrato_pension_id')
            ->whereNull('fecha_fin');
    }

    /** Folio, nombre del cliente o placas. */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when($termino, function (Builder $q) use ($termino) {
            $t = "%{$termino}%";
            $q->where(fn (Builder $w) => $w
                ->where('numero_contrato', 'like', $t)
                ->orWhereHas('cliente', fn (Builder $c) => $c
                    ->where(fn (Builder $cc) => $cc
                        ->where('nombre', 'like', $t)
                        ->orWhere('apellido_paterno', 'like', $t)
                        ->orWhere('email', 'like', $t)))
                ->orWhereHas('vehiculo', fn (Builder $v) => $v->where('placas', 'like', $t)));
        });
    }

    /** Filtros del listado (US-0306). */
    public function scopeFiltrar(Builder $query, ?string $q, ?string $estado, bool $porVencer = false): Builder
    {
        return $query
            ->buscar($q)
            ->when($estado, fn (Builder $w) => $w->where('estado', $estado))
            ->when($porVencer, fn (Builder $w) => $w->porVencer());
    }

    /** US-0307: activas que vencen entre hoy y hoy + 15 días. */
    public function scopePorVencer(Builder $query, int $dias = self::DIAS_AVISO): Builder
    {
        return $query
            ->where('estado', 'ACTIVA')
            ->whereNotNull('fecha_fin')
            ->whereBetween('fecha_fin', [now()->toDateString(), now()->addDays($dias)->toDateString()]);
    }

    /** Días restantes hasta fecha_fin (negativo = ya venció); null si no tiene fecha. */
    public function diasParaVencer(): ?int
    {
        if (! $this->fecha_fin) {
            return null;
        }

        return (int) now()->startOfDay()
            ->diffInDays($this->fecha_fin->copy()->startOfDay(), false);
    }

    /** Estados a los que se puede transicionar desde el actual (US-0305). */
    public function transicionesPermitidas(): array
    {
        return self::TRANSICIONES[$this->estado] ?? [];
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADO_LABELS[$this->estado] ?? $this->estado;
    }

    public function getEstadoClaseAttribute(): string
    {
        return self::ESTADO_CLASSES[$this->estado] ?? 'bg-secondary';
    }
}
```

> **Modelo `Pago`:** aún no existe; se crea en el Sprint 4 (`php artisan make:model Pago`). Si ejecutas el controlador antes de crearlo, la relación `pagos()` fallará solo al invocarse (en `show`/`destroy`). Puedes crearlo ya con `make:model Pago` y dejarlo vacío.

## 2.3 Validación `app/Http/Requests/ContratoPensionRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\ContratoPension;
use App\Models\Tarifa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContratoPensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el permiso se valida en el controlador
    }

    public function rules(): array
    {
        return [
            'cliente_id'   => ['required', 'integer', 'exists:clientes,id'],
            // el vehículo debe pertenecer al cliente elegido
            'vehiculo_id'  => [
                'required', 'integer',
                Rule::exists('vehiculos', 'id')->where('cliente_id', $this->input('cliente_id')),
            ],
            // US-0302: solo tarifas vigentes (si no hay, la lista queda vacía y falla)
            'tarifa_id'    => ['required', 'integer', Rule::in(Tarifa::vigente()->pluck('id')->all())],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'precio'       => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'periodicidad' => ['required', 'in:' . implode(',', ContratoPension::PERIODICIDADES)],
            'dia_cobro'    => ['nullable', 'integer', 'min:1', 'max:31'],
            'deposito'     => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'observaciones'=> ['nullable', 'string', 'max:1000'],
            'espacio_id'   => ['nullable', 'integer', 'exists:espacios,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required'   => 'Selecciona el cliente.',
            'cliente_id.exists'     => 'El cliente seleccionado no existe.',
            'vehiculo_id.required'  => 'Selecciona el vehículo.',
            'vehiculo_id.exists'    => 'El vehículo no pertenece al cliente seleccionado.',
            'tarifa_id.required'    => 'Selecciona la tarifa.',
            'tarifa_id.in'          => 'La tarifa seleccionada no está vigente: elige una tarifa vigente.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'     => 'La fecha de inicio no es válida.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'precio.required'       => 'El precio es obligatorio (es el precio congelado del contrato).',
            'precio.numeric'        => 'El precio debe ser un número.',
            'precio.min'            => 'El precio no puede ser negativo.',
            'periodicidad.required' => 'Selecciona la periodicidad.',
            'periodicidad.in'       => 'La periodicidad seleccionada no es válida.',
            'dia_cobro.between'     => 'El día de cobro debe estar entre 1 y 31.',
            'deposito.min'          => 'El depósito no puede ser negativo.',
            'espacio_id.exists'     => 'El cajón seleccionado no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_id'   => 'cliente',
            'vehiculo_id'  => 'vehículo',
            'tarifa_id'    => 'tarifa',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin'    => 'fecha final',
            'precio'       => 'precio',
            'periodicidad' => 'periodicidad',
            'dia_cobro'    => 'día de cobro',
            'deposito'     => 'depósito en garantía',
            'espacio_id'   => 'cajón',
        ];
    }
}
```

## 2.4 Controlador `app/Http/Controllers/ContratoPensionController.php` (base)

> Las acciones de **asignación de cajón** (Fase 3) y **estados/renovación** (Fase 4) se añaden al final de esta misma clase.

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContratoPensionRequest;
use App\Models\Cliente;
use App\Models\ContratoPension;
use App\Models\Espacio;
use App\Models\Tarifa;
use App\Models\Vehiculo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratoPensionController extends Controller
{
    /** US-0306: listado con filtros. */
    public function index(Request $request)
    {
        $this->authorize('contratos.ver');

        $request->validate([
            'q'          => ['nullable', 'string', 'max:100'],
            'estado'     => ['nullable', 'in:' . implode(',', ContratoPension::ESTADOS)],
            'por_vencer' => ['nullable', 'boolean'],
        ]);

        $q              = $request->string('q')->toString() ?: null;
        $estado         = $request->string('estado')->toString() ?: null;
        $filtroPorVencer = $request->boolean('por_vencer');

        $contratos = ContratoPension::query()
            ->filtrar($q, $estado, $filtroPorVencer)
            ->with(['cliente', 'vehiculo', 'asignacionVigiente.espacio'])
            ->orderByDesc('fecha_inicio')
            ->paginate(10)
            ->withQueryString();

        $totalPorVencer = ContratoPension::porVencer()->count();

        return view('contratos.index', compact(
            'contratos', 'q', 'estado', 'filtroPorVencer', 'totalPorVencer'
        ));
    }

    public function create()
    {
        $this->authorize('contratos.crear');

        return view('contratos.create', $this->combos());
    }

    /** US-0303 + US-0304 (cajón opcional en el alta). */
    public function store(ContratoPensionRequest $request)
    {
        $this->authorize('contratos.crear');

        $datos      = $request->validated();
        $espacioId  = $datos['espacio_id'] ?? null;
        unset($datos['espacio_id']);

        $contrato = $this->crearContrato($datos);

        $error = $espacioId
            ? $this->asignarCajon($contrato, (int) $espacioId, 'Asignación inicial')
            : null;

        if ($error) {
            return redirect()->route('contratos.show', $contrato)->with(
                'error',
                "Contrato {$contrato->numero_contrato} creado, pero el cajón no se asignó: {$error}"
            );
        }

        return redirect()->route('contratos.show', $contrato)->with(
            'success',
            "Contrato {$contrato->numero_contrato} creado correctamente."
        );
    }

    public function edit(ContratoPension $contrato)
    {
        $this->authorize('contratos.editar');

        if (in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
            return redirect()->route('contratos.index')->with(
                'error',
                "El contrato {$contrato->numero_contrato} está cerrado: no se puede editar."
            );
        }

        return view('contratos.edit', array_merge($this->combos(), compact('contrato')));
    }

    public function update(ContratoPensionRequest $request, ContratoPension $contrato)
    {
        $this->authorize('contratos.editar');

        if (in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
            return redirect()->route('contratos.index')->with(
                'error',
                "El contrato {$contrato->numero_contrato} está cerrado: no se puede editar."
            );
        }

        $datos = $request->validated();
        unset($datos['espacio_id']); // la asignación se gestiona desde el expediente

        $contrato->update($datos);

        return redirect()->route('contratos.show', $contrato)->with(
            'success',
            "Contrato {$contrato->numero_contrato} actualizado correctamente."
        );
    }

    /** US-0306: expediente completo. */
    public function show(ContratoPension $contrato)
    {
        $this->authorize('contratos.ver');

        $contrato->load([
            'cliente',
            'vehiculo.tipoVehiculo',
            'tarifa.tipoVehiculo',
            'asignaciones.espacio.zona',
            'asignacionVigiente.espacio.zona',
            'pagos',
        ]);

        $transiciones = $contrato->transicionesPermitidas();

        return view('contratos.show', compact('contrato', 'transiciones'));
    }

    /** Solo si no hay pagos (la FK de pagos es restrict); si no, se cancela. */
    public function destroy(ContratoPension $contrato)
    {
        $this->authorize('contratos.eliminar');

        if ($contrato->pagos()->exists()) {
            return back()->with(
                'error',
                'No se puede eliminar: el contrato tiene pagos registrados. Cancélalo en su lugar.'
            );
        }

        DB::transaction(function () use ($contrato) {
            $this->liberarCajones($contrato, 'Contrato eliminado');
            $contrato->delete(); // las asignaciones se eliminan en cascada
        });

        return redirect()->route('contratos.index')->with(
            'success',
            "Contrato {$contrato->numero_contrato} eliminado."
        );
    }

    /** Combos de los formularios: solo lo que debe verse (US-0302). */
    private function combos(): array
    {
        return [
            'clientes'  => Cliente::where('activo', true)->orderBy('nombre')->get(),
            'vehiculos' => Vehiculo::where('activo', true)->with('tipoVehiculo')->orderBy('placas')->get(),
            'tarifas'   => Tarifa::vigente()->with('tipoVehiculo')
                            ->orderBy('tipo_tarifa')->orderBy('nombre')->get(),
            'espacios'  => Espacio::where('activo', true)->where('estado', 'DISPONIBLE')
                            ->with(['zona', 'tipoVehiculo'])->orderBy('codigo')->get(),
        ];
    }

    /**
     * Crea el contrato con folio autogenerado. Si dos altas simultáneas chocan
     * por el UNIQUE de `numero_contrato`, reintenta hasta 3 veces.
     */
    private function crearContrato(array $datos): ContratoPension
    {
        $intentos = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($datos) {
                    $datos['numero_contrato'] = $this->generarFolio();
                    $datos['estado']          = 'PENDIENTE';

                    return ContratoPension::create($datos);
                });
            } catch (QueryException $e) {
                $intentos++;
                $choque = str_contains($e->getMessage(), 'Duplicate')
                    || (int) ($e->errorInfo[1] ?? 0) === 1062;

                if (! $choque || $intentos >= 3) {
                    throw $e;
                }
            }
        }
    }

    /** Folio consecutivo por año: PEN-2026-0001, PEN-2026-0002, ... */
    private function generarFolio(): string
    {
        $patron = 'PEN-' . now()->format('Y') . '-%';

        $ultima = ContratoPension::where('numero_contrato', 'like', $patron)
            ->orderByRaw('CAST(SUBSTRING(numero_contrato, 10) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->value('numero_contrato');

        $secuencia = $ultima ? ((int) substr($ultima, -4)) + 1 : 1;

        return sprintf('PEN-%s-%04d', now()->format('Y'), $secuencia);
    }
}
```

<!-- SIGUE VISTAS CONTRATOS -->

## 2.5 Vistas de contratos

### `resources/views/contratos/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Contratos de pensión')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Contratos de pensión</h4>
    @can('contratos.crear')
        <a href="{{ route('contratos.create') }}" class="btn btn-primary">+ Nuevo contrato</a>
    @endcan
</div>

{{-- US-0307: alerta de vencimiento inminente --}}
@can('contratos.ver')
    @if ($totalPorVencer > 0)
        <div class="alert alert-warning d-flex justify-content-between align-items-center py-2" role="alert">
            <span>
                <strong>{{ $totalPorVencer }}</strong>
                {{ Str::plural('contrato', $totalPorVencer) }} por vencer en los próximos
                {{ \App\Models\ContratoPension::DIAS_AVISO }} días.
            </span>
            <a href="{{ route('contratos.index', ['por_vencer' => 1]) }}" class="btn btn-sm btn-warning">Ver lista</a>
        </div>
    @endif
@endcan

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" value="{{ $q }}" class="form-control"
               placeholder="Buscar por folio, cliente o placas">
    </div>
    <div class="col-md-3">
        <select name="estado" class="form-select" aria-label="Estado">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\ContratoPension::ESTADOS as $e)
                <option value="{{ $e }}" @selected($estado === $e)>
                    {{ \App\Models\ContratoPension::ESTADO_LABELS[$e] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="por_vencer" value="1" id="porVencer"
                   @checked($filtroPorVencer)>
            <label class="form-check-label" for="porVencer">Por vencer ({{ \App\Models\ContratoPension::DIAS_AVISO }} días)</label>
        </div>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
        @if ($q || $estado || $filtroPorVencer)
            <a href="{{ route('contratos.index') }}" class="btn btn-outline-light">Limpiar</a>
        @endif
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Folio</th><th>Cliente</th><th>Vehículo</th><th>Cajón</th>
                <th class="text-end">Precio</th><th>Vigencia</th>
                <th class="text-center">Estado</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($contratos as $contrato)
                <tr>
                    <td class="fw-bold">{{ $contrato->numero_contrato }}</td>
                    <td>{{ $contrato->cliente?->nombre_completo ?? '—' }}</td>
                    <td>
                        {{ $contrato->vehiculo?->placas ?? '—' }}
                        <small class="text-muted d-block">
                            {{ $contrato->vehiculo?->marca }} {{ $contrato->vehiculo?->modelo }}
                        </small>
                    </td>
                    <td>{{ $contrato->asignacionVigiente?->espacio?->codigo ?? '—' }}</td>
                    <td class="text-end">${{ number_format((float) $contrato->precio, 2) }}
                        <small class="text-muted d-block">
                            {{ \App\Models\ContratoPension::PERIODICIDAD_LABELS[$contrato->periodicidad] }}
                        </small>
                    </td>
                    <td class="small">
                        {{ $contrato->fecha_inicio->format('d/m/Y') }}
                        —
                        {{ $contrato->fecha_fin?->format('d/m/Y') ?? 'sin fecha' }}
                        @if ($contrato->fecha_fin)
                            @php $dias = $contrato->diasParaVencer(); @endphp
                            @if ($dias !== null && $dias < 0)
                                <span class="badge bg-danger">vencido</span>
                            @elseif ($dias !== null && $dias <= \App\Models\ContratoPension::DIAS_AVISO)
                                <span class="badge bg-warning text-dark">{{ $dias }} días</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $contrato->estado_clase }}">{{ $contrato->estado_label }}</span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('contratos.show', $contrato) }}" class="btn btn-sm btn-outline-secondary">Expediente</a>
                        @can('contratos.editar')
                            <a href="{{ route('contratos.edit', $contrato) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endcan
                        @can('contratos.eliminar')
                            <form action="{{ route('contratos.destroy', $contrato) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar este contrato?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No hay contratos{{ $q || $estado || $filtroPorVencer ? ' que coincidan con el filtro' : ' registrados' }}.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $contratos->links() }}</div>
@endsection
```

### `resources/views/contratos/_form.blade.php`

```blade
@csrf

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Cliente <span class="text-danger">*</span></label>
        <select name="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($clientes as $cliente)
                <option value="{{ $cliente->id }}" @selected(old('cliente_id', $contrato->cliente_id ?? '') == $cliente->id)>
                    {{ $cliente->nombre_completo }}
                </option>
            @endforeach
        </select>
        @error('cliente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Vehículo <span class="text-danger">*</span></label>
        <select name="vehiculo_id" class="form-select @error('vehiculo_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($vehiculos as $vehiculo)
                <option value="{{ $vehiculo->id }}" @selected(old('vehiculo_id', $contrato->vehiculo_id ?? '') == $vehiculo->id)>
                    {{ $vehiculo->placas }} — {{ $vehiculo->marca }} {{ $vehiculo->modelo }}
                    ({{ $vehiculo->tipoVehiculo?->nombre }})
                </option>
            @endforeach
        </select>
        @error('vehiculo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">El vehículo debe pertenecer al cliente seleccionado.</small>
    </div>

    <div class="col-md-4">
        <label class="form-label">Tarifa vigente <span class="text-danger">*</span></label>
        <select name="tarifa_id" class="form-select @error('tarifa_id') is-invalid @enderror" required>
            <option value="">— Seleccione —</option>
            @foreach ($tarifas as $tarifa)
                <option value="{{ $tarifa->id }}" data-precio="{{ $tarifa->precio }}"
                        @selected(old('tarifa_id', $contrato->tarifa_id ?? '') == $tarifa->id)>
                    {{ $tarifa->etiqueta }}
                </option>
            @endforeach
        </select>
        @error('tarifa_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Solo aparecen las tarifas vigentes hoy (US-0302).</small>
    </div>

    <div class="col-md-3">
        <label class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
        <input type="date" name="fecha_inicio"
               value="{{ old('fecha_inicio', optional($contrato->fecha_inicio ?? null)->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
               class="form-control @error('fecha_inicio') is-invalid @enderror" required>
        @error('fecha_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Fecha de término</label>
        <input type="date" name="fecha_fin"
               value="{{ old('fecha_fin', optional($contrato->fecha_fin ?? null)->format('Y-m-d')) }}"
               class="form-control @error('fecha_fin') is-invalid @enderror">
        @error('fecha_fin')<div class="invalid-feedback">{{ $message }}</div>@enderror>
        <small class="text-muted">Vacío = sin vencimiento fijo.</small>
    </div>

    <div class="col-md-3">
        <label class="form-label">Precio congelado <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="precio" step="0.01" min="0" max="9999999999.99"
                   value="{{ old('precio', $contrato->precio ?? '') }}"
                   class="form-control @error('precio') is-invalid @enderror" required>
            @error('precio')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <small class="text-muted">Se llena con la tarifa elegida; puedes ajustarlo.</small>
    </div>

    <div class="col-md-3">
        <label class="form-label">Periodicidad <span class="text-danger">*</span></label>
        <select name="periodicidad" class="form-select @error('periodicidad') is-invalid @enderror" required>
            @foreach (\App\Models\ContratoPension::PERIODICIDADES as $p)
                <option value="{{ $p }}" @selected(old('periodicidad', $contrato->periodicidad ?? 'MENSUAL') === $p)>
                    {{ \App\Models\ContratoPension::PERIODICIDAD_LABELS[$p] }}
                </option>
            @endforeach
        </select>
        @error('periodicidad')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Día de cobro</label>
        <input type="number" name="dia_cobro" min="1" max="31"
               value="{{ old('dia_cobro', $contrato->dia_cobro ?? '') }}"
               class="form-control @error('dia_cobro') is-invalid @enderror" placeholder="15">
        @error('dia_cobro')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Depósito en garantía</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="deposito" step="0.01" min="0"
                   value="{{ old('deposito', $contrato->deposito ?? 0) }}"
                   class="form-control @error('deposito') is-invalid @enderror">
            @error('deposito')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    @if (! isset($contrato))
    <div class="col-md-6">
        <label class="form-label">Cajón (opcional)</label>
        <select name="espacio_id" class="form-select @error('espacio_id') is-invalid @enderror">
            <option value="">— Asignar después desde el expediente —</option>
            @foreach ($espacios as $espacio)
                <option value="{{ $espacio->id }}" @selected(old('espacio_id') == $espacio->id)>
                    {{ $espacio->codigo }} · {{ $espacio->tipoVehiculo?->nombre }} · {{ $espacio->zona?->nombre }}
                </option>
            @endforeach
        </select>
        @error('espacio_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Solo cajones libres; la compatibilidad con el tipo de vehículo se valida en el servidor.</small>
    </div>
    @endif

    <div class="col-12">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" rows="2"
                  class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $contrato->observaciones ?? '') }}</textarea>
        @error('observaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

{{-- Copia el precio de la tarifa seleccionada; sin JS se captura a mano. --}}
<script>
    (function () {
        const select = document.querySelector('select[name="tarifa_id"]');
        const precio = document.querySelector('input[name="precio"]');
        if (!select || !precio) return;
        select.addEventListener('change', function () {
            const opcion = select.options[select.selectedIndex];
            if (opcion && opcion.dataset.precio) {
                precio.value = opcion.dataset.precio;
            }
        });
    })();
</script>
```

### `resources/views/contratos/create.blade.php`

```blade
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
```

### `resources/views/contratos/edit.blade.php`

```blade
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
```

### `resources/views/contratos/show.blade.php` (expediente, US-0306)

```blade
@extends('layouts.app')
@section('title', 'Expediente ' . $contrato->numero_contrato)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        {{ $contrato->numero_contrato }}
        <span class="badge {{ $contrato->estado_clase }} ms-2">{{ $contrato->estado_label }}</span>
        @if ($contrato->fecha_fin)
            @php $dias = $contrato->diasParaVencer(); @endphp
            @if ($dias !== null && $dias < 0)
                <span class="badge bg-danger ms-1">Vencido hace {{ abs($dias) }} días</span>
            @elseif ($dias !== null && $dias <= \App\Models\ContratoPension::DIAS_AVISO)
                <span class="badge bg-warning text-dark ms-1">Vence en {{ $dias }} días</span>
            @endif
        @endif
    </h4>
    <div>
        @can('contratos.editar')
            <a href="{{ route('contratos.edit', $contrato) }}" class="btn btn-outline-primary">Editar</a>
        @endcan
        <a href="{{ route('contratos.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><strong>Contrato</strong></div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">Tarifa</dt>
                    <dd class="col-7">{{ $contrato->tarifa?->nombre ?? '—' }}</dd>
                    <dt class="col-5">Precio congelado</dt>
                    <dd class="col-7 fw-bold">${{ number_format((float) $contrato->precio, 2) }}</dd>
                    <dt class="col-5">Periodicidad</dt>
                    <dd class="col-7">{{ \App\Models\ContratoPension::PERIODICIDAD_LABELS[$contrato->periodicidad] }}</dd>
                    <dt class="col-5">Día de cobro</dt>
                    <dd class="col-7">{{ $contrato->dia_cobro ? 'día ' . $contrato->dia_cobro : '—' }}</dd>
                    <dt class="col-5">Depósito</dt>
                    <dd class="col-7">${{ number_format((float) $contrato->deposito, 2) }}</dd>
                    <dt class="col-5">Inicio</dt>
                    <dd class="col-7">{{ $contrato->fecha_inicio->format('d/m/Y') }}</dd>
                    <dt class="col-5">Término</dt>
                    <dd class="col-7">{{ $contrato->fecha_fin?->format('d/m/Y') ?? 'sin fecha' }}</dd>
                    <dt class="col-5">Observaciones</dt>
                    <dd class="col-7">{{ $contrato->observaciones ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><strong>Cliente</strong></div>
            <div class="card-body small">
                <p class="mb-1 fw-bold">{{ $contrato->cliente?->nombre_completo ?? '—' }}</p>
                <p class="mb-1">Tel: {{ $contrato->cliente?->telefono ?: '—' }}</p>
                <p class="mb-1">Email: {{ $contrato->cliente?->email ?: '—' }}</p>
                <hr class="my-2">
                <p class="mb-1 fw-bold">
                    {{ $contrato->vehiculo?->placas }} — {{ $contrato->vehiculo?->marca }} {{ $contrato->vehiculo?->modelo }}
                </p>
                <p class="mb-0">
                    {{ $contrato->vehiculo?->tipoVehiculo?->nombre }} · {{ $contrato->vehiculo?->anio }} ·
                    {{ $contrato->vehiculo?->color }}
                </p>
            </div>
        </div>
    </div>

    {{-- US-0304: cajón actual + historial --}}
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><strong>Cajón asignado</strong></div>
            <div class="card-body">
                @if ($contrato->asignacionVigiente?->espacio)
                    <p class="mb-1">
                        <span class="badge bg-dark fs-6">{{ $contrato->asignacionVigiente->espacio->codigo }}</span>
                        {{ $contrato->asignacionVigiente->espacio->zona?->nombre }}
                    </p>
                    <p class="small text-muted mb-2">
                        Desde {{ $contrato->asignacionVigiente->fecha_inicio?->format('d/m/Y') }}
                    </p>
                    @can('contratos.asignar')
                        <form action="{{ route('contratos.espacio.destroy', $contrato) }}" method="POST"
                              onsubmit="return confirm('¿Liberar el cajón de este contrato?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Liberar cajón</button>
                        </form>
                    @endcan
                @else
                    <p class="text-muted small">Sin cajón asignado.</p>
                @endif

                @can('contratos.asignar')
                    @if (! in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true))
                        <hr class="my-2">
                        <form action="{{ route('contratos.espacio', $contrato) }}" method="POST" class="row g-2">
                            @csrf
                            <div class="col-9">
                                <select name="espacio_id" class="form-select form-select-sm"
                                        aria-label="Cajón a asignar" required>
                                    <option value="">— Seleccione cajón —</option>
                                    @foreach ($contrato->vehiculo?->tipoVehiculo?->espacios()->where('activo', true)->where('estado', 'DISPONIBLE')->orderBy('codigo')->get() ?? [] as $espacio)
                                        <option value="{{ $espacio->id }}">
                                            {{ $espacio->codigo }} · {{ $espacio->zona?->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-sm btn-primary w-100">Asignar</button>
                            </div>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Historial de cajones</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Cajón</th><th>Zona</th><th>Desde</th><th>Hasta</th><th>Motivo</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($contrato->asignaciones as $asignacion)
                        <tr>
                            <td>{{ $asignacion->espacio?->codigo }}</td>
                            <td>{{ $asignacion->espacio?->zona?->nombre }}</td>
                            <td>{{ $asignacion->fecha_inicio?->format('d/m/Y') }}</td>
                            <td>{{ $asignacion->fecha_fin?->format('d/m/Y') ?: 'vigente' }}</td>
                            <td>{{ $asignacion->motivo ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin asignaciones registradas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><strong>Pagos</strong></div>
            <div class="card-body text-center text-muted py-4">
                @if ($contrato->pagos->isEmpty())
                    Sin pagos registrados <span class="badge bg-secondary">Sprint 4</span>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                        <tr><th>Recibo</th><th>Fecha</th><th class="text-end">Total</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($contrato->pagos as $pago)
                            <tr>
                                <td>{{ $pago->numero_recibo }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($pago->fecha_pago)->format('d/m/Y') }}</td>
                                <td class="text-end">${{ number_format((float) $pago->total, 2) }}</td>
                                <td>{{ $pago->estado }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        {{-- US-0305: cambio de estado --}}
        @can('contratos.cambiar_estado')
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Cambiar estado</strong></div>
                <div class="card-body">
                    @if ($transiciones)
                        <form action="{{ route('contratos.estado', $contrato) }}" method="POST" class="row g-2">
                            @csrf
                            <div class="col-8">
                                <select name="estado" class="form-select" aria-label="Nuevo estado" required>
                                    @foreach ($transiciones as $nuevo)
                                        <option value="{{ $nuevo }}">
                                            {{ \App\Models\ContratoPension::ESTADO_LABELS[$nuevo] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-primary w-100">Aplicar</button>
                            </div>
                        </form>
                        <small class="text-muted d-block mt-2">
                            Estados permitidos desde «{{ $contrato->estado_label }}».
                        </small>
                    @else
                        <p class="mb-0 text-muted">Este contrato está cerrado: no admite más cambios de estado.</p>
                    @endif
                </div>
            </div>
        @endcan

        {{-- US-0305: renovación --}}
        @can('contratos.renovar')
            @if (in_array($contrato->estado, ['PENDIENTE', 'ACTIVA', 'VENCIDA'], true))
                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-white"><strong>Renovar</strong></div>
                    <div class="card-body">
                        <form action="{{ route('contratos.renovar', $contrato) }}" method="POST">
                            @csrf
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small mb-1">Nueva periodicidad</label>
                                    <select name="periodicidad" class="form-select form-select-sm" required>
                                        @foreach (\App\Models\ContratoPension::PERIODICIDADES as $p)
                                            <option value="{{ $p }}" @selected($contrato->periodicidad === $p)>
                                                {{ \App\Models\ContratoPension::PERIODICIDAD_LABELS[$p] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="actualizar_precio"
                                               value="1" id="actPrecio">
                                        <label class="form-check-label small" for="actPrecio">
                                            Actualizar a tarifa vigente
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-outline-primary btn-sm w-100">Renovar contrato</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan
    </div>
</div>
@endsection
```

> El `<select>` de cajones del expediente lista los cajones **libres compatibles** con el tipo de vehículo (`tipoVehiculo->espacios()`); si el tipo no tiene cajones libres, el `select` queda vacío y conviene mostrar el mensaje "Sin cajones libres de este tipo" (ver §3.4).

# FASE 3 — Asignación y liberación de cajones (US-0304)

Se añaden **dos métodos públicos** y **dos privados** a `ContratoPensionController`
(al final de la clase, antes del cierre `}`). No se crea ningún archivo nuevo.

## 3.1 Métodos públicos (rutas `POST /contratos/{contrato}/espacio` y `DELETE .../espacio`)

```php
    /** US-0304: asigna un cajón libre al contrato desde el expediente. */
    public function asignarEspacio(Request $request, ContratoPension $contrato)
    {
        $this->authorize('contratos.asignar');

        if (in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
            return back()->with(
                'error',
                "El contrato {$contrato->numero_contrato} está cerrado: no admite cajón."
            );
        }

        $datos = $request->validate(
            ['espacio_id' => ['required', 'integer', 'exists:espacios,id']],
            ['espacio_id.required' => 'Seleccione un cajón.'],
            ['espacio_id' => 'Cajón']
        );

        $error = $this->asignarCajon($contrato, (int) $datos['espacio_id'], 'Asignación manual');

        if ($error) {
            return back()->with('error', "No se pudo asignar el cajón: {$error}.");
        }

        $codigo = $contrato->fresh()->asignacionVigiente?->espacio?->codigo;

        return back()->with('success', "Contrato {$contrato->numero_contrato}: cajón {$codigo} asignado.");
    }

    /** US-0304: libera el cajón vigente sin cerrar el contrato. */
    public function liberarEspacio(ContratoPension $contrato)
    {
        $this->authorize('contratos.asignar');

        if (! $contrato->asignacionVigiente) {
            return back()->with('error', 'Este contrato no tiene cajón asignado.');
        }

        $codigo = $contrato->asignacionVigiente?->espacio?->codigo;

        DB::transaction(fn () => $this->liberarCajones($contrato, 'Liberación manual'));

        return back()->with('success', "Contrato {$contrato->numero_contrato}: cajón {$codigo} liberado.");
    }
```

## 3.2 Métodos privados (la lógica real)

```php
    /**
     * Asigna un cajón al contrato. Devuelve `null` si todo va bien o un mensaje
     * en español (minúsculas, sin punto final) si algo impide la asignación.
     *
     * Reglas: cajón activo y LIBRE, tipo de vehículo compatible, sin otra
     * asignación vigente; el estado del cajón se sincroniza con el contrato
     * (ACTIVA → OCUPADO; PENDIENTE/SUSPENDIDA/VENCIDA → RESERVADO).
     */
    private function asignarCajon(ContratoPension $contrato, int $espacioId, string $motivo): ?string
    {
        return DB::transaction(function () use ($contrato, $espacioId, $motivo) {
            $espacio = Espacio::whereKey($espacioId)->lockForUpdate()->first();

            if (! $espacio || ! $espacio->activo) {
                return 'el cajón no existe o está inactivo';
            }

            if ($espacio->estado !== 'DISPONIBLE') {
                return "el cajón {$espacio->codigo} no está libre (estado: {$espacio->estado_label})";
            }

            $tipoDelVehiculo = $contrato->vehiculo?->tipo_vehiculo_id;
            if ($tipoDelVehiculo && (int) $espacio->tipo_vehiculo_id !== (int) $tipoDelVehiculo) {
                return "el cajón {$espacio->codigo} no admite el tipo de vehículo de este contrato";
            }

            if ($espacio->asignaciones()->whereNull('fecha_fin')->exists()) {
                return "el cajón {$espacio->codigo} ya tiene una asignación vigente";
            }

            if ($contrato->asignacionVigiente) {
                if ($contrato->asignacionVigiente->espacio_id === $espacio->id) {
                    return "el contrato ya usa el cajón {$espacio->codigo}";
                }
                $this->liberarCajones($contrato, 'Cambio de cajón');
            }

            AsignacionEspacio::create([
                'contrato_pension_id' => $contrato->id,
                'espacio_id'          => $espacio->id,
                'fecha_inicio'        => now()->toDateString(),
                'fecha_fin'           => null,
                'motivo'              => $motivo,
                'activo'              => true,
            ]);

            $espacio->update([
                'estado' => $contrato->estado === 'ACTIVA' ? 'OCUPADO' : 'RESERVADO',
            ]);

            return null;
        });
    }

    /**
     * Cierra la(s) asignación(es) vigente(s) del contrato y devuelve sus
     * cajones a DISPONIBLE (salvo que estén en MANTENIMIENTO/BLOQUEADO).
     */
    private function liberarCajones(ContratoPension $contrato, string $motivo): void
    {
        $vigentes = $contrato->asignaciones()->whereNull('fecha_fin')->get();

        foreach ($vigentes as $asignacion) {
            $asignacion->update([
                'fecha_fin' => now()->toDateString(),
                'motivo'    => $motivo,
                'activo'    => false,
            ]);

            $espacio = $asignacion->espacio;
            if ($espacio && in_array($espacio->estado, ['OCUPADO', 'RESERVADO'], true)) {
                $espacio->update(['estado' => 'DISPONIBLE']);
            }
        }
    }

    /**
     * Mantiene sincronizado el estado del cajón cuando cambia el contrato
     * (se llama desde cambiarEstado, Fase 4).
     */
    private function sincronizarEspacio(ContratoPension $contrato): void
    {
        $espacio = $contrato->asignacionVigiente?->espacio;

        if (! $espacio) {
            return;
        }

        $estado = match ($contrato->estado) {
            'ACTIVA'                 => 'OCUPADO',
            'PENDIENTE', 'SUSPENDIDA', 'VENCIDA' => 'RESERVADO',
            default                  => 'DISPONIBLE',
        };

        if ($espacio->estado !== $estado) {
            $espacio->update(['estado' => $estado]);
        }
    }
```

## 3.3 Notas de ejecución

- `Espacio::...->lockForUpdate()` dentro de `DB::transaction` evita que dos
  recepcionistas asignen el mismo cajón al mismo tiempo.
- Al **cambiar de cajón**, el anterior se cierra con motivo
  `Cambio de cajón` y vuelve a `DISPONIBLE` (no se borra: queda el historial
  en `asignaciones_espacio`, US-0306).
- Al **eliminar el contrato** (`destroy`), `liberarCajones()` se ejecuta antes
  del `delete()`; las asignaciones se caen en cascada por la FK.

## 3.4 Mensaje «Sin cajones libres de este tipo»

El `<select>` del expediente (§2.5) solo lista cajones `DISPONIBLE` del tipo de
vehículo. Para que la UI no quede muda cuando no hay ninguno, añade antes del
`@can('contratos.asignar')` de ese panel:

```blade
@php
    $libresDelTipo = $contrato->vehiculo?->tipoVehiculo?->espacios()
        ->where('activo', true)->where('estado', 'DISPONIBLE')->count() ?? 0;
@endphp

@if ($libresDelTipo === 0)
    <p class="small text-warning mb-0">Sin cajones libres de este tipo de vehículo.</p>
@endif
```

# FASE 4 — Estados y renovación (US-0305)

Dos métodos públicos más en `ContratoPensionController`.

## 4.1 Cambio de estado (matriz `ContratoPension::TRANSICIONES`)

```php
    /** US-0305: cambia el estado respetando la matriz de transiciones. */
    public function cambiarEstado(Request $request, ContratoPension $contrato)
    {
        $this->authorize('contratos.cambiar_estado');

        $permitidos = $contrato->transicionesPermitidas();

        $datos = $request->validate(
            ['estado' => ['required', 'in:' . implode(',', $permitidos ?: ['__ninguno__'])]],
            ['estado.in' => 'Ese cambio de estado no está permitido desde «'
                . ContratoPension::ESTADO_LABELS[$contrato->estado] . '».'],
            ['estado' => 'Estado']
        );

        DB::transaction(function () use ($contrato, $datos) {
            $contrato->update(['estado' => $datos['estado']]);

            if (in_array($datos['estado'], ['CANCELADA', 'FINALIZADA'], true)) {
                $this->liberarCajones($contrato, "Contrato {$datos['estado']}");
            } else {
                $this->sincronizarEspacio($contrato);
            }
        });

        return back()->with(
            'success',
            "Contrato {$contrato->numero_contrato}: estado ahora en «"
            . ContratoPension::ESTADO_LABELS[$datos['estado']] . '».'
        );
    }
```

> Si `$permitidos` está vacío (estado final), el `in:` con `__ninguno__` hace
> que la validación siempre falle con el mensaje en español; la vista ya no
> muestra el formulario en ese caso (§2.5).

## 4.2 Renovación

```php
    /**
     * US-0305: alarga la vigencia según la periodicidad elegida. Si se marca
     * «actualizar a tarifa vigente», el precio pasa al de la tarifa actual.
     */
    public function renovar(Request $request, ContratoPension $contrato)
    {
        $this->authorize('contratos.renovar');

        if (! in_array($contrato->estado, ['PENDIENTE', 'ACTIVA', 'VENCIDA'], true)) {
            return back()->with(
                'error',
                "Solo se renuevan contratos pendientes, activos o vencidos."
            );
        }

        $datos = $request->validate(
            [
                'periodicidad'    => ['required', 'in:' . implode(',', ContratoPension::PERIODICIDADES)],
                'actualizar_precio' => ['nullable', 'boolean'],
            ],
            [],
            ['periodicidad' => 'Periodicidad']
        );

        $meses = ContratoPension::MESES[$datos['periodicidad']];
        $base  = ($contrato->fecha_fin?->gt(now()) ? $contrato->fecha_fin->copy() : now()->startOfDay())
                    ->addMonthNoOverflow($meses);

        $contrato->update([
            'periodicidad' => $datos['periodicidad'],
            'fecha_inicio' => $contrato->fecha_inicio,
            'fecha_fin'    => $base,
            'estado'       => $contrato->estado === 'VENCIDA' ? 'ACTIVA' : $contrato->estado,
            'precio'       => ! empty($datos['actualizar_precio']) && $contrato->tarifa
                ? $contrato->tarifa->precio
                : $contrato->precio,
        ]);

        $this->sincronizarEspacio($contrato);

        return back()->with(
            'success',
            "Contrato {$contrato->numero_contrato} renovado: vence el {$contrato->fresh()->fecha_fin->format('d/m/Y')}."
        );
    }
```

**Comportamiento clave (precio congelado):** sin la casilla, el precio no se
toca aunque la tarifa suba; con ella, se copia el precio vigente de la tarifa
del contrato.

# FASE 5 — Alerta de vencimientos en el dashboard (US-0307)

## 5.1 `app/Http/Controllers/HomeController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\ContratoPension;
use Illuminate\Support\Facades\Gate;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        // Solo quien puede ver contratos recibe la alerta.
        $porVencer = Gate::allows('contratos.ver')
            ? ContratoPension::porVencer()->with('cliente')->get()
            : collect();

        return view('home', compact('porVencer'));
    }
}
```

## 5.2 `resources/views/home.blade.php` (añadir antes de cerrar `@section('content')`)

```blade
    {{-- US-0307: contratos por vencer en los próximos {{ ContratoPension::DIAS_AVISO }} días --}}
    @if ($porVencer->isNotEmpty())
        <div class="col-12">
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
                    <strong>Contratos por vencer ({{ $porVencer->count() }})</strong>
                    @can('contratos.ver')
                        <a href="{{ route('contratos.index', ['por_vencer' => 1]) }}" class="btn btn-sm btn-outline-dark">
                            Ver todos
                        </a>
                    @endcan
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($porVencer->take(5) as $contrato)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <strong>{{ $contrato->numero_contrato }}</strong>
                                — {{ $contrato->cliente?->nombre_completo }}
                                <small class="text-muted">
                                    vence {{ $contrato->fecha_fin?->format('d/m/Y') }}
                                </small>
                            </span>
                            <span class="badge bg-warning text-dark">
                                {{ $contrato->diasParaVencer() }} días
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ($porVencer->count() > 5)
                    <div class="card-footer small text-muted">
                        …y {{ $porVencer->count() - 5 }} contrato(s) más.
                    </div>
                @endif
            </div>
        </div>
    @endif
```

> El dashboard usa `container-fluid` + `row g-3`, así que el bloque anterior
> cierra la fila actual (`col-12`) sin romper la cuadrícula.

## 5.3 Verificación rápida

```powershell
php artisan view:clear
```

Entrar como `admin@pension.mx` → el panel debe aparecer solo si hay contratos
con `fecha_fin` dentro de 15 días; con la lista vacía no se muestra nada.

## 6. Rutas y menú

### 6.1 `routes/web.php`

```php
use App\Http\Controllers\ContratoPensionController;
use App\Http\Controllers\TarifaController;
// …
Route::middleware(['auth'])->group(function () {
    // … Sprint 1 y 2 …

    // ── Sprint 3 ───────────────────────────────────────────────
    Route::resource('tarifas', TarifaController::class)->except(['show']);

    Route::resource('contratos', ContratoPensionController::class); // sí incluye show
    Route::post('contratos/{contrato}/estado', [ContratoPensionController::class, 'cambiarEstado'])
        ->name('contratos.estado');
    Route::post('contratos/{contrato}/renovar', [ContratoPensionController::class, 'renovar'])
        ->name('contratos.renovar');
    Route::post('contratos/{contrato}/espacio', [ContratoPensionController::class, 'asignarEspacio'])
        ->name('contratos.espacio');
    Route::delete('contratos/{contrato}/espacio', [ContratoPensionController::class, 'liberarEspacio'])
        ->name('contratos.espacio.destroy');
});
```

> `resource('contratos')` genera 7 rutas (`index/store/create/show/edit/update/destroy`),
> más las 4 explícitas = **11 rutas**; `tarifas` genera 6 (`except show`).
> Total en `php artisan route:list`: **73 rutas** (56 del Sprint 2 + 17 nuevas).

### 6.2 Menú lateral — `resources/views/layouts/app.blade.php`

```blade
@can('tarifas.ver')
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('tarifas.*') ? 'text-white fw-bold' : 'text-white-50' }}"
       href="{{ route('tarifas.index') }}">Tarifas</a>
</li>
@endcan
@can('contratos.ver')
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('contratos.*') ? 'text-white fw-bold' : 'text-white-50' }}"
       href="{{ route('contratos.index') }}">Pensiones</a>
</li>
@endcan
```

En el menú deben quedar **antes** de los ítems deshabilitados `Pagos` / `Accesos` /
`Incidencias` / `Reportes`.

---

## 7. Ejecución y verificación

### 7.1 Orden de ejecución

```powershell
# 1) Seeders (idempotentes; NO usar migrate:fresh)
php artisan db:seed --class=TarifaSeeder        # 10 tarifas
php artisan db:seed --class=RolPermisoSeeder    # 32 permisos + 5 roles + admin
# DatabaseSeeder ya incluye ZonaSeeder, TipoVehiculoSeeder, TarifaSeeder, RolPermisoSeeder

# 2) Cachés
php artisan config:clear
php artisan view:clear

# 3) Rutas y assets
php artisan route:list                          # 73 rutas
npm run build

# 4) Servidor
php artisan serve --port=8000
```

### 7.2 Checklist de verificación (Sprint 3)

**Fase 1 — Tarifas (US-0301, US-0302)**

- [x] `TarifaSeeder` crea 10 tarifas y es repetible (2ª corrida sin duplicados).
- [x] `/tarifas` lista con tipo de vehículo, tipo de tarifa, precio, vigencia y badge
      Vigente / No vigente.
- [x] Administrador da de alta, edita y elimina; eliminar con contratos → `activo = 0`
      (desactivación) en vez de borrar.
- [x] Supervisión de permisos: **Supervisor** ve el módulo con `tarifas.ver` pero **sin
      botones**; **Cajero** no tiene `tarifas.ver` → **403** en `/tarifas`.
- [x] Invitado → 302 a `/login`.

**Fase 2 — Contratos (US-0303, US-0306)**

- [x] El select de tarifas solo incluye vigentes.
- [x] Folio consecutivo: `PEN-2026-0001`, `PEN-2026-0002`, `PEN-2026-0003`.
- [x] Estado inicial `PENDIENTE` y precio autofill desde la tarifa (JS inline en `_form`).
- [x] Validaciones server-side: `fecha_fin` posterior a `fecha_inicio`; tarifa no vigente →
      «La tarifa seleccionada no está vigente: elige una tarifa vigente.»
- [x] `index` filtra por cliente, estado, `por_vencer` y búsqueda; estado vacío →
      «No hay contratos que coincidan con el filtro.»
- [x] Expediente `/contratos/{id}` con datos, historial de cajones y panel de asignación.
- [x] Contrato `CANCELADA` no admite cajón ni cambios de estado.

**Fase 3 — Cajones (US-0304)**

- [x] Asignar cajón → `DISPONIBLE → RESERVADO` (PENDIENTE) / `→ OCUPADO` (ACTIVA).
- [x] Cajón de tipo incompatible rechazado en el servidor:
      «el cajón A-01 no admite el tipo de vehículo de este contrato».
- [x] Cajón no libre rechazado: «el cajón A-01 no está libre (estado: Ocupado)».
- [x] Cambio de cajón cierra la asignación previa con motivo `Cambio de cajón` y abre otra.
- [x] Liberar manual → `DISPONIBLE` + historial cerrado (`Liberación manual`).

**Fase 4 — Estados y renovación (US-0305)**

- [x] Matriz de transiciones respetada (desde `PENDIENTE` solo ofrece Activa / Cancelada).
- [x] `PENDIENTE → ACTIVA` ocupa el cajón; `→ CANCELADA` lo libera con motivo
      `Contrato CANCELADA` y deja el expediente sin formulario de renovar ni de asignación.
- [x] Renovación: suma días a `fecha_fin` conservando el precio capturado.

**Fase 5 — Alertas (US-0307)**

- [x] Panel «Contratos por vencer (n)» en el dashboard con badge de días.
- [x] Alerta en el índice: «1 contrato por vencer en los próximos 15 días».

**Fase 6 — Rutas y menú**

- [x] 17 rutas nuevas registradas (73 en total) y menú `Tarifas` / `Pensiones` con `@can`.

**Permisos (§7.3)**

- [x] Cajero: `/contratos/create` → 403, `/tarifas` → 403, sin `+ Nuevo contrato`.
- [x] Supervisor: listados 200 sin botones; `POST /contratos/{id}/estado` → 403.
- [x] Invitado: `/contratos`, `/tarifas`, `/contratos/create`, `/home` → redirect a `/login`.

### 7.3 Matriz de permisos Sprint 3

| Rol | tarifas.ver | tarifas.crear/editar/eliminar | contratos.ver | contratos.crear/editar | contratos.asignar | contratos.cambiar_estado | contratos.renovar | contratos.eliminar |
|---|---|---|---|---|---|---|---|---|
| ADMINISTRADOR | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| RECEPCION | ✔ | ✖ | ✔ | ✔ | ✔ | ✖ | ✖ | ✖ |
| SUPERVISOR | ✔ | ✖ | ✔ | ✖ | ✖ | ✖ | ✖ | ✖ |
| CAJERO | ✖ (403) | ✖ | ✔ | ✖ | ✖ | ✖ | ✖ | ✖ |
| SEGURIDAD | ✖ | ✖ | ✔ | ✖ | ✖ | ✖ | ✖ | ✖ |

Total: **32 permisos** (21 previos + `tarifas` ×4 + `contratos` ×7).

> **Ajuste posterior (10/10/2026):** `CAJERO` ahora tiene también
> **`contratos.crear` + `contratos.editar` + `contratos.cambiar_estado`**
> (el cajero da de alta, corrige y activa/suspende pensiones). La tabla
> anterior refleja el estado al ejecutar este sprint; el total global de
> permisos **no cambia** (solo el reparto: CAJERO 7 → 10). Registrado en
> «Desviaciones» del Apéndice D.

---

## Apéndice A — Estados y transiciones

**Contrato:** `PENDIENTE, ACTIVA, VENCIDA, SUSPENDIDA, CANCELADA, FINALIZADA`
**Espacio:** `DISPONIBLE, OCUPADO, RESERVADO, MANTENIMIENTO, BLOQUEADO`
**Tipo de tarifa:** `HORA, DIA, NOCHE, MENSUAL, ANUAL, EVENTO`
**Periodicidad:** `MENSUAL, TRIMESTRAL, SEMESTRAL, ANUAL`

| Desde | Hacia |
|---|---|
| PENDIENTE | ACTIVA, CANCELADA |
| ACTIVA | VENCIDA, SUSPENDIDA, CANCELADA, FINALIZADA |
| VENCIDA / SUSPENDIDA | ACTIVA, CANCELADA, FINALIZADA |
| CANCELADA / FINALIZADA | *(ninguna)* |

Sincronización cajón: `ACTIVA → OCUPADO`; `PENDIENTE/SUSPENDIDA/VENCIDA → RESERVADO`;
`CANCELADA/FINALIZADA → DISPONIBLE`.

## Apéndice B — Problemas conocidos (y cómo se resolvieron)

1. **`asignacionVigiente` mal definida.** En el Sprint 2 quedó
   `belongsTo(..., 'contrato_pension_id')`, que busca `asignaciones.id = contrato.contrato_pension_id`
   y siempre devuelve `NULL`. Se corrigió a `hasOne(...)->whereNull('fecha_fin')` en
   `ContratoPension` **y** `Espacio`.
2. **Ramas defensivas en `asignarCajon`.** «el cajón … ya tiene una asignación vigente» y
   «el contrato ya usa el cajón …» rara vez se alcanzan porque la validación de `estado`
   del cajón (paso anterior) dispara primero; se conservan por si un cajón queda
   `DISPONIBLE` con una asignación abierta (dato inconsistente).
3. **Folio duplicado.** `crearContrato` reintenta 10 veces ante `1062` (duplicado) para
   rellenar huecos de `PEN-AAAA-NNNN`.
4. **SweetAlert de éxito cierra solo** (`timer: 2500`); el de error no tiene `timer`.
5. **Precio autofill** por `tarifa_id` con JS inline en `_form`; el servidor vuelve a
   validarlo con `precio > 0`.
6. **Cajero sin `tarifas.ver`:** el contrato se crea igual porque el select de tarifas
   solo requiere `contratos.crear` y el servidor valida por id.
7. **No existe botón de «cambio de cajón» en la UI** cuando hay asignación vigente: el
   cambio se hace vía `POST /contratos/{id}/espacio` (el formulario solo aparece sin
   cajón); el historial conserva ambos movimientos.

## Apéndice C — Próximo sprint

**Sprint 4 — Módulos 8 y 9:** pagos (recibos, `Pagos` en el menú), cortes de caja y
reportes; pendientes: `Pago.php` ya creado vacío, enlace `Pagos` del menú.

---

## Apéndice D — Registro de ejecución

**Fecha:** 07/10/2026 · **Entorno:** Laravel 10.50.3 + PHP 8.1.18 + MySQL `pensiones`

### Comandos ejecutados

```powershell
php artisan db:seed --class=TarifaSeeder          # 10 tarifas
php artisan db:seed --class=RolPermisoSeeder      # 32 permisos / 5 roles
php artisan config:clear; php artisan view:clear
php artisan route:list                            # 73 rutas
npm run build                                     # OK
php artisan serve --port=8000
php -l  (todos los PHP nuevos: sin errores)
```

### Verificación por historia de usuario

| US | Qué se probó | Resultado |
|---|---|---|
| US-0301 | CRUD de tarifas, filtros, vigencia, eliminar sin/con contratos | ✅ Ok (eliminar con contratos desactiva) |
| US-0302 | Select solo con tarifas vigentes; validación server-side | ✅ Ok |
| US-0303 | Alta con folio consecutivo, estado `PENDIENTE`, precio autofill, validaciones | ✅ Ok |
| US-0304 | Asignar, liberar, cambio de cajón, tipo incompatible, cajón ocupado | ✅ Ok |
| US-0305 | Matriz de transiciones, ocupación/liberación de cajón, renovación | ✅ Ok |
| US-0306 | Expediente, historial de cajones, filtros del listado | ✅ Ok |
| US-0307 | Panel «por vencer» + alerta en dashboard/índice | ✅ Ok |
| Permisos | Invitado 302, Cajero 403, Supervisor 200 sin botones + POST 403 | ✅ Ok |

### Desviaciones respecto a la guía

- Se agregó la sección **§7.3** con la matriz real de permisos por rol.
- **Ajuste posterior (10/10/2026):** se quitó el select **«Periodicidad»**
  del formulario de alta/edición de contrato (`contratos/_form.blade.php`) y
  su regla de validación en `ContratoPensionRequest`; el campo se fija solo
  con el **`->default('MENSUAL')`** de la columna (el controlador ya no lo
  envía). La lectura en el expediente y el formulario de **renovación**
  (US-0305, `contratos.renovar`) no se tocaron. Verificado: el alta
  `PEN-2026-0002` sin enviar `periodicidad` → guardada como `Mensual`;
  `GET /contratos/6/edit` sin `select[name="periodicidad"]` ni su etiqueta;
  `PUT` sin el campo → 200 y `Periodicidad: Mensual` intacta.
- **Ajuste posterior (10/10/2026):** se añadieron `contratos.crear`,
  `contratos.editar` y `contratos.cambiar_estado` al rol `CAJERO` (pedido del
  equipo antes del Sprint 6). Verificado con `cajero@pension.mx`:
  `/contratos` y `/contratos/create` → 200 con «+ Nuevo contrato», alta real
  `PEN-2026-0001` → 302, `/contratos/5/edit` → 200,
  `POST /contratos/5/estado {estado:'ACTIVA'}` → 200 (badge «Activa» en
  expediente e índice) y estado inválido → 422 con el mensaje de la matriz
  `TRANSICIONES`. Se quedó **sin** `contratos.asignar` / `renovar` /
  `eliminar` / `tarifas.ver`: `POST /contratos/5/espacio` y `/tarifas` → 403.
  Regresión: `supervisor@pension.mx` sigue en **403** en `/contratos/create`,
  `/contratos/5/edit` y `POST /contratos/5/estado`.
- **Bug corregido durante la verificación:** `asignacionVigiente` (`belongsTo` → `hasOne`)
  en `ContratoPension` y `Espacio` (Apéndice B.1).
- El caso «vehículo pertenece a otro cliente» no pudo probarse en UI: el entorno solo
  tiene 1 cliente / 1 vehículo; la regla vive en `ContratoPensionRequest`.
  **(Superado el 12/10/2026:** la regla pasó a `resolverVehiculo()` en el
  controlador y el caso sí se probó — ver el rediseño de más abajo.**)**
- **Rediseño del alta de pensiones (12/10/2026, pedido del equipo):**
  1. **Cliente** → textbox con buscador AJAX (`GET /clientes/buscar`,
     `ClienteController@buscar`, autoriza `clientes.ver` **o** `contratos.crear`
     para que el CAJERO pueda buscar) + **alta rápida** visible cuando no hay
     coincidencias (Nombre/Apellido(s)/Teléfono; se crea en `contratos.store`,
     sin requerir `clientes.crear`).
  2. **Nuevas columnas en `contratos_pension`** (migración aditiva
     `2026_10_10_100000_add_datos_nueva_pension...`): `nombre_empresa`,
     `contacto_empresa`, `direccion_empresa`, `fecha_entrada` (datetime) y
     `documentacion` enum(`INE`,`TARJETA_CIRCULACION`,`LICENCIA_CONDUCIR`).
     Filas viejas quedan en NULL y el expediente muestra «—».
  3. **Vehículo en línea** → se quitó el select `vehiculo_id`; ahora captura
     tipo de unidad/placas/marca/modelo/color y `resolverVehiculo()` crea o
     **reutiliza por placas** (actualiza datos si el vehículo es del mismo
     cliente; error 422 si es de otro). Se eliminó la regla «vehículo
     pertenece al cliente» y `vehiculos`/`clientes` de `combos()` (ahora solo
     `tiposVehiculo`, `tarifas`, `espacios`).
  4. **Documentación** → pidió checkbox pero «solo una de las tres opciones»:
     se implementó como **radio group** (control correcto para exclusión
     mutua), `required`.
  5. **Fecha y hora de entrada** → `datetime-local` con `now()` editable;
     el servidor guarda `now()` si viene vacío.
  - Verificado con CAJERO (12/10): alta `PEN-2026-0003` con cliente nuevo
    «Maria Lopez» (alta rápida, split nombre/apellidos), vehículo nuevo
    `XYZ9876` y doc `INE` → +1 cliente, +1 vehículo; placas de **otro cliente**
    → 422 «ya están registradas a nombre de «Maria Lopez»»; reutilización con
    el dueño correcto → `PEN-2026-0004` **sin duplicar** vehículo (attrs
    actualizados); edición de `PEN-2026-0003` (empresa/color/doc) → OK;
    validación vacía → 12 mensajes en español; regresión de pagos →
    `REC-2026-0003` 1 mes × $1,500.00. Bug propio corregido en la pasada:
    `authorizeAny()` no existe en el trait → `abort_unless(...->can() ...)`.
  - ⚠️ Evitar `@php($var = …)` inline en vistas: con Livewire se compila mal.
- **Autocompletado por placas (12/10/2026):** nuevo endpoint
  `GET /vehiculos/buscar?placas=` (`VehiculoController@buscar`; autoriza
  `vehiculos.ver` **o** `contratos.crear`, igual que `clientes/buscar`;
  devuelve `{vehiculo: {...}|null}` con tipo/marca/modelo/color y el dueño).
  En `contratos/_form.blade.php` las placas buscan con *debounce* de 300 ms y:
  rellenan tipo/marca/modelo/color; muestran un aviso bajo el campo (verde si
  el dueño coincide con el cliente elegido, amarillo si no coincide, azul si
  aún no se elige cliente); si las placas cambian a unas **inexistentes** se
  limpia el autocompletado (salvo que el usuario haya editado un campo a
  mano); y se ejecuta al cargar la edición. El guardado sigue igual:
  `resolverVehiculo()` reutiliza o crea por placas. Verificado (12/10):
  `ABC1234` → Nissan Versa «Juan Perez Lopez» (aviso azul), elegir a Maria
  → aviso amarillo de dueño distinto, `XYZ9876` → Toyota Corolla Azul con
  aviso verde, `NEW1234` → sin coincidencia y campos limpios, marca escrita
  a mano se conserva, alta completa `PEN-2026-0005` (id 9) reutiliza el
  vehículo id 4 (**vehiculos = 2**), `/contratos/7/edit` precarga con aviso
  verde, consola sin errores y `laravel.log` limpio. Ruta total: **94**.

### Estado de los datos al terminar

- 10 tarifas activas, 32 permisos, 2 contratos: `PEN-2026-0001` (`CANCELADA`) y
  `PEN-2026-0002` (`ACTIVA`, cajón A-01 `OCUPADO`).
- Cajones: A-01 zona A `OCUPADO`, A-02 zona A `DISPONIBLE`, A-01 zona B `DISPONIBLE`.
- **12/10/2026 (rediseño):** 5 permisos más (CAJERO con contratos, total 40);
  clientes = 2 («Juan Perez Lopez», «Maria Lopez»), vehículos = 2
  (`ABC1234`, `XYZ9876`), contratos = 5 (`PEN-2026-0001/0002` previos +
  `PEN-2026-0003`, `PEN-2026-0004` y `PEN-2026-0005` del nuevo formulario,
  los tres con datos de empresa, `fecha_entrada` y `documentacion`), pagos = 3
  (`REC-2026-0003` 1 mes × $1,500.00 del contrato 7).

### Estado final

**Sprint 3 — Completado** (código, seeders, rutas, menú, vistas y verificación en
navegador con los 3 roles).

