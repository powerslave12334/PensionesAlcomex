# Guía paso a paso — Sprint 4: Pagos / Caja y recibos (Módulo 8)

> Serie de guías: `GUIA-LOGIN-MODULO1.md` (Sprint 1) → `GUIA-SPRINT2-ESPACIOS.md`
> (Sprint 2) → `GUIA-SPRINT3-TARIFAS-Y-PENSIONES.md` (Sprint 3) → **este documento**.

---

## 0. Alcance

### Historias de usuario (`PLAN-SCRUM.md` §7)

| ID | Historia de usuario | Pts | Prioridad |
|---|---|---|---|
| US-0401 | Como **cajero**, registrar un cobro con recibo foliado único, periodo que cubre, subtotal, descuento, impuesto y total. | 13 | M |
| US-0402 | Como **cajero**, elegir el método de pago (efectivo, tarjeta, transferencia, depósito) y que se pida referencia cuando corresponda. | 5 | M |
| US-0403 | Como **cajero**, imprimir o descargar el recibo en PDF. | 5 | M |
| US-0404 | Como **administrador**, ver el historial de pagos por contrato y por cliente. | 5 | M |
| US-0405 | Como **cajero**, hacer el **corte de caja** del día (totales por método). | 8 | S |
| US-0406 | Como **administrador**, cancelar o reembolsar un pago con motivo, sin perder el registro. | 5 | S |
| US-0407 | Como **sistema**, ocultar/bloquear el botón de cobro si no hay permiso `pagos.crear`. | 2 | M |
| | **Total** | **43** | |

**BD:** `pagos`, `metodos_pago` (ya migradas en `MIGRACIONES.md`, **vacías**).

### Estado actual (tras Sprint 3)

- 26 migraciones aplicadas, **73 rutas**, **32 permisos**, 10 tarifas activas.
- Datos: 1 cliente, 1 vehículo (`ABC1234`, automóvil), 3 cajones
  (A-01 zona A `OCUPADO`, A-02 zona A `DISPONIBLE`, A-01 zona B `DISPONIBLE`),
  **2 contratos**: `PEN-2026-0001` (`CANCELADA`) y `PEN-2026-0002` (`ACTIVA`, cajón A-01,
  precio $1,500.00, vence 07/10/2027).
- `pagos` = 0 filas · `metodos_pago` = **0 filas** (no tenía seeder en el Sprint 1).
- Ya existe `app/Models/Pago.php` **vacío** y la relación `ContratoPension::pagos()`.
- En `contratos/show.blade.php` hay la tarjeta **Pagos** con el texto
  `Sin pagos registrados Sprint 4` (placeholder que este sprint reemplaza).
- En `resources/views/layouts/app.blade.php` está el ítem de menú
  `<span class="nav-link disabled">Pagos</span>` (hay que activarlo).
- **No** existen: `MetodoPago`, `PagoRequest`, `PagoController`, vistas `pagos/*`.

### Artefactos que crea este sprint

| Archivo | Tipo | Fase |
|---|---|---|
| `database/seeders/MetodoPagoSeeder.php`   | nuevo | 1 |
| `database/seeders/RolPermisoSeeder.php`   | modifica (+4 permisos `pagos.*`) | 1 |
| `database/seeders/DatabaseSeeder.php`     | modifica (registra el seeder) | 1 |
| `app/Models/MetodoPago.php`               | nuevo | 2 |
| `app/Models/Pago.php`                     | reescribe | 2 |
| `app/Models/Cliente.php`                  | modifica (+`pagos()`) | 2 |
| `app/Http/Requests/PagoRequest.php`       | nuevo | 3 |
| `app/Http/Controllers/PagoController.php` | nuevo | 4 |
| `resources/views/pagos/{index,_form,create,show,corte}.blade.php` | nuevos | 5 |
| `resources/views/contratos/show.blade.php`| modifica (cobro + historial) | 6 |
| `routes/web.php`                          | modifica (+6 rutas) | 7 |
| `resources/views/layouts/app.blade.php`   | modifica (menú Pagos) | 7 |

---

## 1. Decisiones del sprint

1. **Folio de recibo `REC-AAAA-NNNN`** con `UNIQUE` en `numero_recibo`; se genera con el
   mismo patrón que `PEN-…` (reintento ante `1062`, 3 intentos).
2. **Catálogo `metodos_pago` con `requiere_referencia`.** El flag del catálogo gobierna
   si `referencia` es obligatoria (US-0402): la validación es **en el servidor**
   (`PagoRequest`), el JS solo muestra/oculta el campo.
3. **Nunca se borra un pago.** Estados `PENDIENTE → PAGADO → CANCELADO / REEMBOLSADO`
   con matriz de transiciones; cancelar/reembolsar exige **motivo** y se guarda en
   `observaciones` con fecha (US-0406). La FK de `pagos.contrato_pension_id` es
   `restrictOnDelete`, por eso el contrato con pagos no se puede eliminar (ya avisado
   en el Sprint 3).
4. **El servidor recalcula `total = subtotal − descuento + impuesto`** y **ignora** el
   valor que envíe el navegador (integridad). El JS solo lo pinta en la vista.
5. **Recibo imprimible en lugar de PDF con librería** (US-0403): `pagos/show` es una
   vista sin sidebar con `@media print` y botón «Imprimir / Guardar como PDF»
   (`window.print()`). No requiere `composer require` ni conexión. El Apéndice B.4
   explica cómo añadir `barryvdh/laravel-dompdf` si más adelante se necesita un archivo
   `.pdf` generado en el servidor.
6. **Corte de caja = informe en vivo** por día (`pagos/corte?fecha=`), totales por
   método sobre los pagos `PAGADO` del día; **sin tabla nueva**. Un corte «firmado»
   (con usuario que cierra turno) queda para el Sprint 9.
7. **4 permisos nuevos** `pagos.{ver,crear,cancelar,corte}` → **36 permisos** en total
   (US-0407: el botón «Cobrar» va con `@can('pagos.crear')`).
8. **Se cobra desde el expediente del contrato**: botón «Cobrar» que abre
   `pagos/create?contrato_id=…` precargado con periodo siguiente y total según
   `ContratoPension::MESES[$contrato->periodicidad]`.
9. **Sin edición de pagos**: `resource` solo `index/create/store/show`. Corregir un
   cobro se hace cancelando y volviendo a cobrar (rastro de auditoría).

---

## FASE 1 — Seeders: catálogo de métodos y permisos `pagos.*`

### 1.1 Generar artefactos

```powershell
php artisan make:model MetodoPago
php artisan make:request PagoRequest
php artisan make:controller PagoController
php artisan make:seeder MetodoPagoSeeder
```

> No se crea migración: `metodos_pago` y `pagos` ya vienen del Sprint 1
> (`MIGRACIONES.md` §5.4 y §5.14).

### 1.2 `database/seeders/MetodoPagoSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MetodoPagoSeeder extends Seeder
{
    /**
     * Catálogo de la hoja "metodos_pago" (US-0402).
     * `requiere_referencia` gobierna si el formulario exige referencia.
     */
    public function run(): void
    {
        $metodos = [
            ['nombre' => 'Efectivo',      'requiere_referencia' => false],
            ['nombre' => 'Tarjeta',       'requiere_referencia' => true],
            ['nombre' => 'Transferencia', 'requiere_referencia' => true],
            ['nombre' => 'Depósito',      'requiere_referencia' => true],
            ['nombre' => 'Terminal',      'requiere_referencia' => false],
        ];

        foreach ($metodos as $metodo) {
            DB::table('metodos_pago')->updateOrInsert(
                ['nombre' => $metodo['nombre']],
                $metodo + ['activo' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
```

### 1.3 `database/seeders/RolPermisoSeeder.php` — ampliar

**a)** Agregar el módulo nuevo al arreglo `$modulos` (después del bloque `// Sprint 3`):

```php
            'contratos'       => ['ver', 'crear', 'editar', 'eliminar', 'asignar', 'cambiar_estado', 'renovar'],
            // Sprint 4
            'pagos'           => ['ver', 'crear', 'cancelar', 'corte'],
```

**b)** Reemplazar **completamente** el reparto de permisos de cada rol
(`syncPermissions` borra lo anterior, así que hay que pegar las listas enteras):

```php
        Role::findByName('ADMINISTRADOR')->syncPermissions(Permission::all());

        Role::findByName('RECEPCION')->syncPermissions([
            // Sprint 1
            'clientes.ver', 'clientes.crear', 'clientes.editar',
            'vehiculos.ver', 'vehiculos.crear', 'vehiculos.editar',
            // Sprint 2
            'zonas.ver', 'tipos_vehiculo.ver',
            'espacios.ver', 'espacios.crear', 'espacios.editar', 'espacios.cambiar_estado',
            // Sprint 3
            'tarifas.ver',
            'contratos.ver', 'contratos.crear', 'contratos.editar', 'contratos.asignar',
            // Sprint 4 — consulta el histórico, no cobra
            'pagos.ver',
        ]);

        Role::findByName('SUPERVISOR')->syncPermissions([
            'clientes.ver', 'vehiculos.ver',
            'zonas.ver', 'tipos_vehiculo.ver', 'espacios.ver',
            'tarifas.ver', 'contratos.ver',
            // Sprint 4 — vigila la caja
            'pagos.ver', 'pagos.corte',
        ]);

        Role::findByName('CAJERO')->syncPermissions([
            'espacios.ver', 'contratos.ver',
            // Sprint 4 — cobra y hace corte (US-0401, US-0405)
            'pagos.ver', 'pagos.crear', 'pagos.corte',
        ]);

        Role::findByName('SEGURIDAD')->syncPermissions([
            'espacios.ver', 'contratos.ver',   // verificar titular del cajón
        ]);
```

> `SEGURIDAD` **no** recibe permisos de pagos: es el caso negativo de US-0407.

### 1.4 `database/seeders/DatabaseSeeder.php`

```php
        $this->call([
            ZonaSeeder::class,
            TipoVehiculoSeeder::class,
            TarifaSeeder::class,
            MetodoPagoSeeder::class,   // ← nuevo (Sprint 4)
            RolPermisoSeeder::class,
        ]);
```

### 1.5 Ejecutar

```powershell
php artisan db:seed --class=MetodoPagoSeeder    # 5 métodos de pago
php artisan db:seed --class=RolPermisoSeeder    # 36 permisos (32 + 4 nuevos)
php artisan config:clear
php artisan view:clear
```

---

## FASE 2 — Modelos

### 2.1 `app/Models/MetodoPago.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoPago extends Model
{
    // La tabla del Excel es `metodos_pago`; Eloquent deduciría `metodo_pagos`.
    protected $table = 'metodos_pago';

    protected $fillable = ['nombre', 'requiere_referencia', 'activo'];

    protected $casts = [
        'requiere_referencia' => 'boolean',
        'activo'              => 'boolean',
    ];

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /** Combos del formulario de cobro (US-0402): solo métodos activos. */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('nombre');
    }
}
```

### 2.2 `app/Models/Pago.php` (reescribe el archivo vacío)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    /** Valores exactos del ENUM de la migración. */
    public const ESTADOS = ['PENDIENTE', 'PAGADO', 'CANCELADO', 'REEMBOLSADO'];

    public const ESTADO_LABELS = [
        'PENDIENTE'   => 'Pendiente',
        'PAGADO'      => 'Pagado',
        'CANCELADO'   => 'Cancelado',
        'REEMBOLSADO' => 'Reembolsado',
    ];

    public const ESTADO_CLASSES = [
        'PENDIENTE'   => 'bg-secondary',
        'PAGADO'      => 'bg-success',
        'CANCELADO'   => 'bg-danger',
        'REEMBOLSADO' => 'bg-warning text-dark',
    ];

    /** Matriz de transiciones (US-0406). Vacío = estado final: el registro no cambia. */
    public const TRANSICIONES = [
        'PENDIENTE'   => ['PAGADO', 'CANCELADO'],
        'PAGADO'      => ['CANCELADO', 'REEMBOLSADO'],
        'CANCELADO'   => [],
        'REEMBOLSADO' => [],
    ];

    protected $fillable = [
        'contrato_pension_id', 'cliente_id', 'numero_recibo', 'fecha_pago',
        'periodo_inicio', 'periodo_fin', 'subtotal', 'descuento', 'impuesto',
        'total', 'metodo_pago_id', 'referencia', 'estado', 'usuario_id',
        'observaciones',
    ];

    protected $casts = [
        'fecha_pago'     => 'datetime',
        'periodo_inicio' => 'date',
        'periodo_fin'    => 'date',
        'subtotal'       => 'decimal:2',
        'descuento'      => 'decimal:2',
        'impuesto'       => 'decimal:2',
        'total'          => 'decimal:2',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoPension::class, 'contrato_pension_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Filtros del historial (US-0404): texto libre, estado, método, contrato,
     * cliente y rango de fechas. Devuelve el Builder para poder clonarlo y
     * calcular el resumen con los mismos criterios.
     */
    public function scopeFiltrar(
        Builder $query,
        ?string $q = null,
        ?string $estado = null,
        ?int $metodoId = null,
        ?int $contratoId = null,
        ?int $clienteId = null,
        ?string $desde = null,
        ?string $hasta = null
    ): Builder {
        return $query
            ->when($q, fn (Builder $b, string $t) => $b->where(function ($w) use ($t) {
                $w->where('numero_recibo', 'like', "%{$t}%")
                    ->orWhereHas('contrato', fn ($c) => $c->where('numero_contrato', 'like', "%{$t}%"))
                    ->orWhereHas('cliente', fn ($c) => $c
                        ->where('nombre', 'like', "%{$t}%")
                        ->orWhere('apellido_paterno', 'like', "%{$t}%"));
            }))
            ->when($estado, fn (Builder $b, string $e) => $b->where('estado', $e))
            ->when($metodoId, fn (Builder $b, int $m) => $b->where('metodo_pago_id', $m))
            ->when($contratoId, fn (Builder $b, int $c) => $b->where('contrato_pension_id', $c))
            ->when($clienteId, fn (Builder $b, int $c) => $b->where('cliente_id', $c))
            ->when($desde, fn (Builder $b, string $d) => $b->whereDate('fecha_pago', '>=', $d))
            ->when($hasta, fn (Builder $b, string $d) => $b->whereDate('fecha_pago', '<=', $d));
    }

    /** Pagos de un día para el corte de caja (US-0405). */
    public function scopeDelDia(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_pago', $fecha);
    }

    /** Estados a los que se puede pasar desde el actual; vacío = final. */
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

### 2.3 `app/Models/Cliente.php` — relación con pagos

Agrega el `use Illuminate\Database\Eloquent\Relations\HasMany;` (ya existe) y:

```php
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }
```

> Sirve para el historial por cliente (US-0404) y para futuros reportes.

---

## FASE 3 — Validación `app/Http/Requests/PagoRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\ContratoPension;
use App\Models\MetodoPago;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el controlador hace $this->authorize('pagos.crear')
    }

    public function rules(): array
    {
        return [
            'contrato_pension_id' => [
                'required', 'integer', 'exists:contratos_pension,id',
                // US-0406 / integridad: no se cobra a un contrato cerrado
                function (string $attribute, mixed $value, Closure $fail): void {
                    $contrato = ContratoPension::find($value);
                    if ($contrato && in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
                        $fail("El contrato {$contrato->numero_contrato} está cerrado: no se le puede cobrar.");
                    }
                },
            ],
            'metodo_pago_id' => [
                'required', 'integer',
                Rule::exists('metodos_pago', 'id')->where('activo', true),
                // US-0402: la referencia es obligatoria si el método lo pide.
                // Ojo: la regla vive aquí (y no en `referencia`) porque Laravel
                // omite las validaciones de un atributo vacío (null) salvo que
                // sean implícitas; `referencia` vacía no dispararía el closure.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $metodo = MetodoPago::find($value);
                    $valor  = trim((string) $this->input('referencia'));
                    if ($metodo?->requiere_referencia && $valor === '') {
                        $fail("El método «{$metodo->nombre}» requiere número de referencia o autorización.");
                    }
                },
            ],
            'fecha_pago'     => ['required', 'date'],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fin'    => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'subtotal'       => ['required', 'numeric', 'min:0.01'],
            'descuento'      => ['nullable', 'numeric', 'min:0', 'lte:subtotal'],
            'impuesto'       => ['nullable', 'numeric', 'min:0'],
            'total'          => ['required', 'numeric', 'min:0'],
            'referencia'     => ['nullable', 'string', 'max:100'],
            'estado'        => ['required', 'in:PENDIENTE,PAGADO'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'contrato_pension_id.required' => 'Selecciona el contrato que se está cobrando.',
            'contrato_pension_id.exists'   => 'Ese contrato no existe.',
            'metodo_pago_id.required'      => 'Selecciona el método de pago.',
            'metodo_pago_id.exists'        => 'Ese método de pago no existe o está inactivo.',
            'fecha_pago.required'          => 'Indica la fecha del pago.',
            'fecha_pago.date'              => 'La fecha del pago no es válida.',
            'periodo_inicio.required'      => 'Indica el inicio del periodo que cubre el pago.',
            'periodo_fin.required'         => 'Indica el fin del periodo que cubre el pago.',
            'periodo_fin.after_or_equal'   => 'El fin del periodo debe ser igual o posterior al inicio.',
            'subtotal.required'            => 'El subtotal no puede estar vacío.',
            'subtotal.numeric'             => 'El subtotal debe ser un importe numérico.',
            'subtotal.min'                 => 'El subtotal debe ser mayor que cero.',
            'descuento.numeric'            => 'El descuento debe ser un importe numérico.',
            'descuento.lte'                => 'El descuento no puede superar el subtotal.',
            'impuesto.numeric'             => 'El impuesto debe ser un importe numérico.',
            'total.numeric'                => 'El total debe ser un importe numérico.',
            'total.min'                    => 'El total no puede ser negativo.',
            'referencia.max'               => 'La referencia no puede exceder 100 caracteres.',
            'estado.required'              => 'Indica el estado del cobro.',
            'estado.in'                    => 'Ese estado de pago no es válido.',
        ];
    }
}
```

> `total` se **valida** pero se **recalcula** en el controlador (decisión 4), así que
> un valor mañoso del navegador nunca contamina la BD.

---

## FASE 4 — Controlador `app/Http/Controllers/PagoController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\PagoRequest;
use App\Models\ContratoPension;
use App\Models\MetodoPago;
use App\Models\Pago;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    /** US-0404: historial con filtros y resumen del mismo filtro. */
    public function index(Request $request)
    {
        $this->authorize('pagos.ver');

        $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'estado'   => ['nullable', 'in:' . implode(',', Pago::ESTADOS)],
            'metodo'   => ['nullable', 'integer', 'exists:metodos_pago,id'],
            'contrato' => ['nullable', 'integer', 'exists:contratos_pension,id'],
            'cliente'  => ['nullable', 'integer', 'exists:clientes,id'],
            'desde'    => ['nullable', 'date'],
            'hasta'    => ['nullable', 'date', 'after_or_equal:desde'],
        ], [], [
            'q' => 'búsqueda', 'estado' => 'estado', 'metodo' => 'método',
            'contrato' => 'contrato', 'cliente' => 'cliente',
            'desde' => 'desde', 'hasta' => 'hasta',
        ]);

        $q         = $request->string('q')->toString() ?: null;
        $estado    = $request->string('estado')->toString() ?: null;
        $metodo    = $request->filled('metodo') ? (int) $request->metodo : null;
        $contrato  = $request->filled('contrato') ? (int) $request->contrato : null;
        $cliente   = $request->filled('cliente') ? (int) $request->cliente : null;
        $desde     = $request->string('desde')->toString() ?: null;
        $hasta     = $request->string('hasta')->toString() ?: null;

        $consulta = Pago::filtrar($q, $estado, $metodo, $contrato, $cliente, $desde, $hasta);

        $pagos = (clone $consulta)
            ->with(['contrato', 'cliente', 'metodoPago'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Resumen con exactamente los mismos filtros (solo lo cobrado)
        $resumen = (clone $consulta)
            ->where('estado', 'PAGADO')
            ->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total')
            ->first();

        $filtros = compact('q', 'estado', 'metodo', 'contrato', 'cliente', 'desde', 'hasta');

        return view('pagos.index', [
            'pagos'    => $pagos,
            'resumen'  => $resumen,
            'filtros'  => $filtros,
            'tieneFiltro' => array_filter($filtros, fn ($v) => $v !== null && $v !== '') !== [],
            'contratos' => ContratoPension::orderBy('numero_contrato')->get(['id', 'numero_contrato']),
            'clientes'  => \App\Models\Cliente::orderBy('nombre')->get(['id', 'nombre', 'apellido_paterno']),
            'metodos'   => MetodoPago::activos()->get(),
        ]);
    }

    /** US-0401: alta de cobro (con precarga desde el expediente, US-0407). */
    public function create(Request $request)
    {
        $this->authorize('pagos.crear');

        $contrato = null;
        $prefill  = ['periodo_inicio' => now()->toDateString(), 'periodo_fin' => '', 'subtotal' => ''];

        if ($request->filled('contrato_id')) {
            $contrato = ContratoPension::find((int) $request->contrato_id);

            if (! $contrato) {
                return redirect()->route('pagos.create')->with('error', 'Ese contrato no existe.');
            }

            if (in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
                return redirect()->route('pagos.create')->with(
                    'error',
                    "El contrato {$contrato->numero_contrato} está cerrado: no se le puede cobrar."
                );
            }

            [$prefill['periodo_inicio'], $prefill['periodo_fin']] = $this->periodoSugerido($contrato);
            $prefill['subtotal'] = number_format((float) $contrato->precio, 2, '.', '');
        }

        return view('pagos.create', array_merge($this->combos(), compact('contrato', 'prefill')));
    }

    /** US-0401 + US-0402: guarda el cobro con folio único y totales del servidor. */
    public function store(PagoRequest $request)
    {
        $this->authorize('pagos.crear');

        $datos = $request->validated();

        $contrato = ContratoPension::findOrFail($datos['contrato_pension_id']);

        $datos['cliente_id']  = $contrato->cliente_id;
        $datos['usuario_id']  = Auth::id();
        $datos['fecha_pago']  = Carbon::parse($datos['fecha_pago']);
        $datos['descuento']   = (float) ($datos['descuento'] ?? 0);
        $datos['impuesto']    = (float) ($datos['impuesto'] ?? 0);
        $datos['subtotal']    = (float) $datos['subtotal'];
        $datos['total']       = round($datos['subtotal'] - $datos['descuento'] + $datos['impuesto'], 2);
        $datos['referencia']  = trim((string) ($datos['referencia'] ?? '')) ?: null;
        $datos['observaciones'] = $request->string('observaciones')->toString() ?: null;

        if ($datos['total'] < 0) {
            return back()->withInput()->withErrors(['descuento' => 'El descuento no puede superar el subtotal.']);
        }

        $pago = $this->crearPago($datos);

        return redirect()->route('pagos.show', $pago)->with(
            'success',
            'Recibo ' . $pago->numero_recibo . ' registrado por $'
                . number_format((float) $pago->total, 2) . '.'
        );
    }

    /** US-0403: recibo imprimible («Guardar como PDF» desde el diálogo de impresión). */
    public function show(Pago $pago)
    {
        $this->authorize('pagos.ver');

        $pago->load(['contrato.vehiculo', 'cliente', 'metodoPago', 'usuario']);

        return view('pagos.show', compact('pago'));
    }

    /** US-0405: corte de caja del día (informe, sin tabla nueva). */
    public function corte(Request $request)
    {
        $this->authorize('pagos.corte');

        $request->validate(['fecha' => ['nullable', 'date']], [
            'fecha.date' => 'La fecha del corte no es válida.',
        ]);

        $fecha = $request->date('fecha') ?? now();

        $pagos = Pago::delDia($fecha->toDateString())
            ->with(['metodoPago', 'cliente', 'contrato'])
            ->orderBy('id')
            ->get();

        $cobrados   = $pagos->where('estado', 'PAGADO');
        $porMetodo  = $cobrados
            ->groupBy('metodo_pago_id')
            ->map(fn ($grupo) => [
                'metodo'    => $grupo->first()->metodoPago?->nombre ?? '—',
                'cantidad'  => $grupo->count(),
                'subtotal'  => round($grupo->sum('subtotal'), 2),
                'descuento' => round($grupo->sum('descuento'), 2),
                'impuesto'  => round($grupo->sum('impuesto'), 2),
                'total'     => round($grupo->sum('total'), 2),
            ])
            ->sortBy('metodo')
            ->values();

        $totales = [
            'cantidad'  => $cobrados->count(),
            'subtotal'  => round($cobrados->sum('subtotal'), 2),
            'descuento' => round($cobrados->sum('descuento'), 2),
            'impuesto'  => round($cobrados->sum('impuesto'), 2),
            'total'     => round($cobrados->sum('total'), 2),
        ];

        $excluidos = $pagos->where('estado', '!=', 'PAGADO');

        return view('pagos.corte', compact('fecha', 'porMetodo', 'totales', 'cobrados', 'excluidos'));
    }

    /** US-0406: cancelar o reembolsar con motivo (el registro nunca se borra). */
    public function estado(Request $request, Pago $pago)
    {
        $this->authorize('pagos.cancelar');

        $permitidas = $pago->transicionesPermitidas();

        $datos = $request->validate([
            'accion' => ['required', 'in:' . implode(',', $permitidas ?: ['__ninguno__'])],
            'motivo' => ['required', 'string', 'max:300'],
        ], [
            'accion.in'   => 'Ese movimiento no está permitido desde «'
                . Pago::ESTADO_LABELS[$pago->estado] . '».',
            'motivo.required' => 'Escribe el motivo del movimiento.',
            'motivo.string'   => 'El motivo debe ser texto.',
        ], [
            'accion' => 'movimiento',
            'motivo' => 'motivo',
        ]);

        DB::transaction(function () use ($pago, $datos) {
            $nota = '[' . now()->format('d/m/Y H:i') . '] '
                . Pago::ESTADO_LABELS[$datos['accion']] . ': ' . $datos['motivo'];

            $pago->update([
                'estado'        => $datos['accion'],
                'observaciones' => trim(($pago->observaciones ? $pago->observaciones . "\n" : '') . $nota),
            ]);
        });

        return redirect()->route('pagos.show', $pago)->with(
            'success',
            "Recibo {$pago->numero_recibo}: " . strtolower(Pago::ESTADO_LABELS[$datos['accion']]) . '.'
        );
    }

    /** Combos de los formularios. */
    private function combos(): array
    {
        return [
            'contratos' => ContratoPension::with(['cliente', 'vehiculo'])
                ->whereNotIn('estado', ['CANCELADA', 'FINALIZADA'])
                ->orderBy('numero_contrato')
                ->get(),
            'metodos' => MetodoPago::activos()->get(),
        ];
    }

    /**
     * Periodo que sigue al último cobro (o el del contrato si es el primero),
     * acotado a la vigencia. Ej.: PEN-2026-0002 MENSUAL, sin pagos, inicia
     * 07/10/2026 → 07/10/2026 – 06/11/2026.
     */
    private function periodoSugerido(ContratoPension $contrato): array
    {
        $ultima = $contrato->pagos()
            ->whereNotNull('periodo_fin')
            ->orderByDesc('periodo_fin')
            ->first();

        $inicio = $ultima ? $ultima->periodo_fin->copy()->addDay() : $contrato->fecha_inicio->copy();
        $meses  = ContratoPension::MESES[$contrato->periodicidad] ?? 1;
        $fin    = $inicio->copy()->addMonthsNoOverflow($meses)->subDay();

        if ($contrato->fecha_fin && $fin->greaterThan($contrato->fecha_fin)) {
            $fin = $contrato->fecha_fin->copy();
        }

        if ($contrato->fecha_fin && $inicio->greaterThan($contrato->fecha_fin)) {
            $inicio = $contrato->fecha_fin->copy();
            $fin    = $contrato->fecha_fin->copy();
        }

        return [$inicio->toDateString(), $fin->toDateString()];
    }

    /**
     * Crea el recibo con folio autogenerado. Si dos cobros simultáneos chocan
     * por el UNIQUE de `numero_recibo`, reintenta hasta 3 veces.
     */
    private function crearPago(array $datos): Pago
    {
        $intentos = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($datos) {
                    $datos['numero_recibo'] = $this->generarFolio();
                    $datos['estado']        = $datos['estado'] ?? 'PAGADO';

                    return Pago::create($datos);
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

    /** Folio consecutivo por año: REC-2026-0001, REC-2026-0002, ... */
    private function generarFolio(): string
    {
        $patron = 'REC-' . now()->format('Y') . '-%';

        $ultima = Pago::where('numero_recibo', 'like', $patron)
            ->orderByRaw('CAST(SUBSTRING(numero_recibo, 10) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->value('numero_recibo');

        $secuencia = $ultima ? ((int) substr($ultima, -4)) + 1 : 1;

        return sprintf('REC-%s-%04d', now()->format('Y'), $secuencia);
    }
}
```

> `SUBSTRING(numero_recibo, 10)` funciona igual que en `PEN-…`: el prefijo
> `REC-2026-` mide 9 caracteres, así que la posición 10 arranca en la secuencia.

---

## FASE 5 — Vistas de pagos

### 5.1 `resources/views/pagos/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Pagos y recibos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Pagos y recibos</h4>
    <div class="d-flex gap-2">
        @can('pagos.corte')
            <a href="{{ route('pagos.corte') }}" class="btn btn-outline-secondary">Corte de caja</a>
        @endcan
        @can('pagos.crear')
            <a href="{{ route('pagos.create') }}" class="btn btn-primary">+ Nuevo cobro</a>
        @endcan
    </div>
</div>

{{-- US-0404: resumen calculado con los mismos filtros --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success">${{ number_format((float) $resumen->total, 2) }}</div>
                <small class="text-muted">Cobrado con este filtro</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold">{{ $resumen->cantidad }}</div>
                <small class="text-muted">{{ Str::plural('recibo', $resumen->cantidad) }}</small>
            </div>
        </div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" value="{{ $filtros['q'] }}" class="form-control"
               placeholder="Buscar recibo, folio o cliente">
    </div>
    <div class="col-md-2">
        <select name="estado" class="form-select" aria-label="Estado">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\Pago::ESTADOS as $e)
                <option value="{{ $e }}" @selected($filtros['estado'] === $e)>
                    {{ \App\Models\Pago::ESTADO_LABELS[$e] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="metodo" class="form-select" aria-label="Método de pago">
            <option value="">Todos los métodos</option>
            @foreach ($metodos as $m)
                <option value="{{ $m->id }}" @selected($filtros['metodo'] === $m->id)>{{ $m->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="contrato" class="form-select" aria-label="Contrato">
            <option value="">Todos los contratos</option>
            @foreach ($contratos as $c)
                <option value="{{ $c->id }}" @selected($filtros['contrato'] === $c->id)>{{ $c->numero_contrato }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="cliente" class="form-select" aria-label="Cliente">
            <option value="">Todos los clientes</option>
            @foreach ($clientes as $cl)
                <option value="{{ $cl->id }}" @selected($filtros['cliente'] === $cl->id)>
                    {{ $cl->nombre }} {{ $cl->apellido_paterno }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-1">
        <input type="date" name="desde" value="{{ $filtros['desde'] }}" class="form-control" aria-label="Desde">
    </div>
    <div class="col-md-1">
        <input type="date" name="hasta" value="{{ $filtros['hasta'] }}" class="form-control" aria-label="Hasta">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
        @if ($tieneFiltro)
            <a href="{{ route('pagos.index') }}" class="btn btn-outline-light">Limpiar</a>
        @endif
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Recibo</th><th>Fecha</th><th>Contrato</th><th>Cliente</th>
                <th>Método</th><th>Referencia</th><th class="text-end">Total</th>
                <th class="text-center">Estado</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($pagos as $pago)
                <tr>
                    <td class="fw-bold">{{ $pago->numero_recibo }}</td>
                    <td>{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                    <td>{{ $pago->contrato?->numero_contrato ?? '—' }}</td>
                    <td>{{ $pago->cliente?->nombre_completo ?? '—' }}</td>
                    <td>{{ $pago->metodoPago?->nombre ?? '—' }}</td>
                    <td class="small">{{ $pago->referencia ?: '—' }}</td>
                    <td class="text-end">${{ number_format((float) $pago->total, 2) }}</td>
                    <td class="text-center">
                        <span class="badge {{ $pago->estado_clase }}">{{ $pago->estado_label }}</span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('pagos.show', $pago) }}" class="btn btn-sm btn-outline-secondary">Recibo</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        No hay pagos{{ $tieneFiltro ? ' que coincidan con el filtro' : ' registrados' }}.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $pagos->links() }}</div>
@endsection
```

### 5.2 `resources/views/pagos/_form.blade.php`

```blade
@php
    $f = old();
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="contrato_pension_id">Contrato <span class="text-danger">*</span></label>
        <select name="contrato_pension_id" id="contrato_pension_id" class="form-select" required
                @if ($contrato) data-fijo="1" @endif>
            <option value="">— Seleccione contrato —</option>
            @foreach ($contratos as $c)
                <option value="{{ $c->id }}"
                    @selected((int) ($f['contrato_pension_id'] ?? ($contrato->id ?? 0)) === $c->id)
                    data-precio="{{ $c->precio }}"
                    data-inicio="{{ $c->fecha_inicio->format('Y-m-d') }}"
                    data-meses="{{ \App\Models\ContratoPension::MESES[$c->periodicidad] ?? 1 }}">
                    {{ $c->numero_contrato }} — {{ $c->cliente?->nombre_completo ?? 's/n' }}
                </option>
            @endforeach
        </select>
        @error('contrato_pension_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <small class="text-muted">Sin contratos cancelados ni finalizados.</small>
    </div>

    <div class="col-md-3">
        <label class="form-label" for="fecha_pago">Fecha del pago <span class="text-danger">*</span></label>
        <input type="date" name="fecha_pago" id="fecha_pago" class="form-control"
               value="{{ $f['fecha_pago'] ?? now()->toDateString() }}" required>
        @error('fecha_pago') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="estado">Estado del cobro <span class="text-danger">*</span></label>
        <select name="estado" id="estado" class="form-select" required>
            <option value="PAGADO" @selected(($f['estado'] ?? 'PAGADO') === 'PAGADO')>Pagado (cobrado)</option>
            <option value="PENDIENTE" @selected(($f['estado'] ?? '') === 'PENDIENTE')>Pendiente (por cobrar)</option>
        </select>
        @error('estado') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="periodo_inicio">Periodo desde <span class="text-danger">*</span></label>
        <input type="date" name="periodo_inicio" id="periodo_inicio" class="form-control"
               value="{{ $f['periodo_inicio'] ?? $prefill['periodo_inicio'] }}" required>
        @error('periodo_inicio') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="periodo_fin">Periodo hasta <span class="text-danger">*</span></label>
        <input type="date" name="periodo_fin" id="periodo_fin" class="form-control"
               value="{{ $f['periodo_fin'] ?? $prefill['periodo_fin'] }}" required>
        @error('periodo_fin') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="subtotal">Subtotal <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" name="subtotal" id="subtotal" class="form-control"
               value="{{ $f['subtotal'] ?? $prefill['subtotal'] }}" required>
        @error('subtotal') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="descuento">Descuento</label>
        <input type="number" step="0.01" min="0" name="descuento" id="descuento" class="form-control"
               value="{{ $f['descuento'] ?? '0.00' }}">
        @error('descuento') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="impuesto">Impuesto</label>
        <input type="number" step="0.01" min="0" name="impuesto" id="impuesto" class="form-control"
               value="{{ $f['impuesto'] ?? '0.00' }}">
        @error('impuesto') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="total">Total <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="total" id="total" class="form-control"
               value="{{ $f['total'] ?? $prefill['subtotal'] ?? '0.00' }}" required>
        <small class="text-muted">Calculado: − descuento + impuesto</small>
        @error('total') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="metodo_pago_id">Método de pago <span class="text-danger">*</span></label>
        <select name="metodo_pago_id" id="metodo_pago_id" class="form-select" required>
            <option value="">— Seleccione —</option>
            @foreach ($metodos as $m)
                <option value="{{ $m->id }}"
                    @selected((int) ($f['metodo_pago_id'] ?? 0) === $m->id)
                    data-referencia="{{ (int) $m->requiere_referencia ? 1 : 0 }}">
                    {{ $m->nombre }}
                </option>
            @endforeach
        </select>
        @error('metodo_pago_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="referencia">Referencia / autorización</label>
        <input type="text" name="referencia" id="referencia" maxlength="100" class="form-control"
               value="{{ old('referencia') }}" placeholder="Obligatoria según el método">
        @error('referencia') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <small class="text-muted" id="refAyuda">El método elegido decide si es obligatoria.</small>
    </div>

    <div class="col-12">
        <label class="form-label" for="observaciones">Observaciones</label>
        <textarea name="observaciones" id="observaciones" rows="2" class="form-control"
                  maxlength="1000">{{ old('observaciones') }}</textarea>
        @error('observaciones') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar cobro</button>
        <a href="{{ route('pagos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const contrato = document.querySelector('#contrato_pension_id');
        const metodo   = document.querySelector('#metodo_pago_id');
        const ref      = document.querySelector('#referencia');
        const refAyuda = document.querySelector('#refAyuda');
        const inicio   = document.querySelector('#periodo_inicio');
        const fin      = document.querySelector('#periodo_fin');
        const subtotal = document.querySelector('#subtotal');
        const desc     = document.querySelector('#descuento');
        const imp      = document.querySelector('#impuesto');
        const total    = document.querySelector('#total');

        function calcular() {
            const s = parseFloat(subtotal.value || 0);
            const d = parseFloat(desc.value || 0);
            const i = parseFloat(imp.value || 0);
            total.value = (Math.round((s - d + i) * 100) / 100).toFixed(2);
        }

        function exigirReferencia() {
            const opcion = metodo.options[metodo.selectedIndex];
            const obliga = opcion && opcion.dataset.referencia === '1';
            ref.required = obliga;
            ref.placeholder = obliga ? 'Obligatoria' : 'Opcional';
            refAyuda.textContent = obliga
                ? 'Este método requiere número de referencia o autorización.'
                : 'Este método no requiere referencia.';
        }

        function desdeContrato() {
            const opcion = contrato.options[contrato.selectedIndex];
            if (!opcion || !opcion.dataset.precio) return;

            subtotal.value = parseFloat(opcion.dataset.precio).toFixed(2);

            const meses = parseInt(opcion.dataset.meses || 1, 10);
            const d = new Date(opcion.dataset.inicio + 'T00:00:00');
            if (isNaN(d)) return;

            inicio.value = opcion.dataset.inicio;

            const hasta = new Date(d);
            hasta.setMonth(hasta.getMonth() + meses);
            hasta.setDate(hasta.getDate() - 1);

            const rellenar = n => String(n).padStart(2, '0');
            fin.value = hasta.getFullYear() + '-' + rellenar(hasta.getMonth() + 1)
                + '-' + rellenar(hasta.getDate());

            calcular();
        }

        [subtotal, desc, imp].forEach(el => el.addEventListener('input', calcular));
        metodo.addEventListener('change', exigirReferencia);
        contrato.addEventListener('change', function () {
            if (!contrato.dataset.fijo) desdeContrato();
        });

        calcular();
        exigirReferencia();
    });
</script>
```

### 5.3 `resources/views/pagos/create.blade.php`

```blade
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
        </form>
    </div>
</div>
@endsection
```

### 5.4 `resources/views/pagos/show.blade.php` — recibo imprimible (US-0403)

```blade
@extends('layouts.app')
@section('title', 'Recibo ' . $pago->numero_recibo)

@section('content')
<style>
    @media print {
        nav, .navbar, .d-print-none { display: none !important; }
        .flex-grow-1 { margin: 0 !important; }
        main, .p-4 { padding: 0 !important; }
        .recibo { border: 0 !important; box-shadow: none !important; }
        body { background: #fff !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
    <h4 class="mb-0">Recibo {{ $pago->numero_recibo }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('pagos.index') }}" class="btn btn-outline-secondary">Volver</a>
        <button onclick="window.print()" class="btn btn-outline-primary">Imprimir / Guardar PDF</button>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm recibo">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div>
                        <h5 class="mb-0">{{ config('app.name') }}</h5>
                        <small class="text-muted">Recibo de pensión vehicular</small>
                    </div>
                    <div class="text-end">
                        <div class="fs-5 fw-bold">{{ $pago->numero_recibo }}</div>
                        <span class="badge {{ $pago->estado_clase }}">{{ $pago->estado_label }}</span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-6">
                        <div class="text-muted small">Cliente</div>
                        <div class="fw-semibold">{{ $pago->cliente?->nombre_completo ?? '—' }}</div>
                        <div class="text-muted small">{{ $pago->cliente?->email ?: '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Contrato / vehículo</div>
                        <div class="fw-semibold">{{ $pago->contrato?->numero_contrato ?? '—' }}</div>
                        <div class="text-muted small">
                            {{ $pago->contrato?->vehiculo?->placas ?? '—' }}
                            {{ $pago->contrato?->vehiculo?->marca }}
                            {{ $pago->contrato?->vehiculo?->modelo }}
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4">
                        <div class="text-muted small">Fecha de pago</div>
                        <div>{{ $pago->fecha_pago->format('d/m/Y') }}</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Periodo que cubre</div>
                        <div>
                            {{ $pago->periodo_inicio?->format('d/m/Y') }} — {{ $pago->periodo_fin?->format('d/m/Y') }}
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Registró</div>
                        <div>{{ $pago->usuario?->name ?? '—' }}</div>
                    </div>
                </div>

                <table class="table table-sm">
                    <thead class="table-light">
                    <tr><th>Concepto</th><th class="text-end">Importe</th></tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>Pensión {{ $pago->contrato?->periodicidad
                            ? \App\Models\ContratoPension::PERIODICIDAD_LABELS[$pago->contrato->periodicidad]
                            : 'vehicular' }}</td>
                        <td class="text-end">${{ number_format((float) $pago->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Descuento</td>
                        <td class="text-end">−${{ number_format((float) $pago->descuento, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Impuesto</td>
                        <td class="text-end">+${{ number_format((float) $pago->impuesto, 2) }}</td>
                    </tr>
                    </tbody>
                    <tfoot>
                    <tr class="table-light fw-bold">
                        <td>Total</td>
                        <td class="text-end">${{ number_format((float) $pago->total, 2) }}</td>
                    </tr>
                    </tfoot>
                </table>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="text-muted small">Método de pago</div>
                        <div>{{ $pago->metodoPago?->nombre ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Referencia</div>
                        <div>{{ $pago->referencia ?: '—' }}</div>
                    </div>
                </div>

                @if ($pago->observaciones)
                    <div class="mt-3 border-top pt-2">
                        <div class="text-muted small">Observaciones</div>
                        <div class="small" style="white-space: pre-line">{{ $pago->observaciones }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4 d-print-none">
        {{-- US-0406: cancelar / reembolsar con motivo --}}
        @can('pagos.cancelar')
            @php $acciones = $pago->transicionesPermitidas(); @endphp
            @if ($acciones)
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><strong>Corregir este cobro</strong></div>
                    <div class="card-body">
                        <form action="{{ route('pagos.estado', $pago) }}" method="POST" class="row g-2">
                            @csrf
                            <div class="col-12">
                                <label class="form-label" for="accion">Movimiento</label>
                                <select name="accion" id="accion" class="form-select" required>
                                    @foreach ($acciones as $a)
                                        <option value="{{ $a }}">{{ \App\Models\Pago::ESTADO_LABELS[$a] }}</option>
                                    @endforeach
                                </select>
                                @error('accion') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="motivo">Motivo <span class="text-danger">*</span></label>
                                <textarea name="motivo" id="motivo" rows="3" class="form-control" required
                                          maxlength="300">{{ old('motivo') }}</textarea>
                                @error('motivo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                <small class="text-muted">Queda registrado en el recibo; nada se borra.</small>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-outline-danger w-100"
                                        onclick="return confirm('¿Confirmar el movimiento de este recibo?')">
                                    Aplicar movimiento
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @else
                <div class="alert alert-secondary mb-0">
                    Recibo en estado final ({{ $pago->estado_label }}): no admite más movimientos.
                </div>
            @endif
        @endcan

        @if ($pago->contrato)
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Contrato</strong></div>
                <div class="card-body">
                    <a href="{{ route('contratos.show', $pago->contrato) }}" class="btn btn-outline-secondary w-100">
                        Ver expediente {{ $pago->contrato->numero_contrato }}
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
```

> `@media print` oculta el sidebar, la barra superior y los botones
> (`d-print-none`), dejando solo el recibo. En el diálogo de impresión se elige
> **Guardar como PDF** → cumplo US-0403 sin dependencias.

### 5.5 `resources/views/pagos/corte.blade.php` — corte de caja (US-0405)

```blade
@extends('layouts.app')
@section('title', 'Corte de caja')

@section('content')
<style>
    @media print {
        nav, .navbar, .d-print-none { display: none !important; }
    }
</style>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Corte de caja — {{ $fecha->format('d/m/Y') }}</h4>
    <div class="d-flex gap-2 d-print-none">
        <form method="GET" action="{{ route('pagos.corte') }}" class="d-flex gap-2">
            <input type="date" name="fecha" value="{{ $fecha->toDateString() }}" class="form-control">
            <button class="btn btn-outline-secondary">Ver día</button>
        </form>
        <button onclick="window.print()" class="btn btn-outline-primary">Imprimir</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success">${{ number_format($totales['total'], 2) }}</div>
                <small class="text-muted">Total cobrado</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold">{{ $totales['cantidad'] }}</div>
                <small class="text-muted">{{ Str::plural('cobro', $totales['cantidad']) }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold">${{ number_format($totales['descuento'], 2) }}</div>
                <small class="text-muted">Descuentos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold">${{ number_format($totales['impuesto'], 2) }}</div>
                <small class="text-muted">Impuestos</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Totales por método de pago</strong></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Método</th><th class="text-center">Comprobantes</th>
                <th class="text-end">Subtotal</th><th class="text-end">Descuento</th>
                <th class="text-end">Impuesto</th><th class="text-end">Total</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($porMetodo as $fila)
                <tr>
                    <td>{{ $fila['metodo'] }}</td>
                    <td class="text-center">{{ $fila['cantidad'] }}</td>
                    <td class="text-end">${{ number_format($fila['subtotal'], 2) }}</td>
                    <td class="text-end">${{ number_format($fila['descuento'], 2) }}</td>
                    <td class="text-end">${{ number_format($fila['impuesto'], 2) }}</td>
                    <td class="text-end fw-semibold">${{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Sin cobros registrados este día.</td></tr>
            @endforelse
            </tbody>
            <tfoot>
            <tr class="table-light fw-bold">
                <td>TOTAL</td>
                <td class="text-center">{{ $totales['cantidad'] }}</td>
                <td class="text-end">${{ number_format($totales['subtotal'], 2) }}</td>
                <td class="text-end">${{ number_format($totales['descuento'], 2) }}</td>
                <td class="text-end">${{ number_format($totales['impuesto'], 2) }}</td>
                <td class="text-end">${{ number_format($totales['total'], 2) }}</td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

@if ($excluidos->isNotEmpty())
    <div class="alert alert-secondary mt-3 mb-0">
        <strong>{{ $excluidos->count() }}</strong> movimiento(s) del día
        <strong>no entran al total</strong> (cancelados o reembolsados):
        {{ $excluidos->pluck('numero_recibo')->join(', ') }}.
    </div>
@endif
@endsection
```

---

## FASE 6 — Integración con el expediente (US-0404 + US-0407)

### 6.1 Botón «Cobrar» en `resources/views/contratos/show.blade.php`

Reemplaza el bloque de acciones del encabezado (líneas 18-23):

```blade
    <div class="d-flex gap-2">
        @can('pagos.crear')
            @if (! in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true))
                <a href="{{ route('pagos.create', ['contrato_id' => $contrato->id]) }}"
                   class="btn btn-success">Cobrar</a>
            @endif
        @endcan
        @can('contratos.editar')
            <a href="{{ route('contratos.edit', $contrato) }}" class="btn btn-outline-primary">Editar</a>
        @endcan
        <a href="{{ route('contratos.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
```

### 6.2 Tarjeta «Pagos» del expediente

Reemplaza por completo el `<div class="card shadow-sm mt-3">` que hoy contiene
`Sin pagos registrados Sprint 4` (líneas 161-184 del archivo actual):

```blade
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Pagos</strong>
                @php
                    $cobrado = $contrato->pagos->where('estado', 'PAGADO')->sum('total');
                @endphp
                <span class="small text-muted">
                    Cobrado: <strong>${{ number_format((float) $cobrado, 2) }}</strong>
                </span>
            </div>
            <div class="card-body text-center text-muted py-4">
                @if ($contrato->pagos->isEmpty())
                    Sin pagos registrados todavía.
                @else
                    <table class="table table-sm mb-0 text-start">
                        <thead class="table-light">
                        <tr>
                            <th>Recibo</th><th>Fecha</th><th>Periodo</th>
                            <th>Método</th><th class="text-end">Total</th>
                            <th class="text-center">Estado</th><th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($contrato->pagos as $pago)
                            <tr>
                                <td class="fw-semibold">{{ $pago->numero_recibo }}</td>
                                <td>{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                                <td class="small">
                                    {{ $pago->periodo_inicio?->format('d/m/Y') }} —
                                    {{ $pago->periodo_fin?->format('d/m/Y') }}
                                </td>
                                <td>{{ $pago->metodoPago?->nombre ?? '—' }}</td>
                                <td class="text-end">${{ number_format((float) $pago->total, 2) }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $pago->estado_clase }}">{{ $pago->estado_label }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('pagos.show', $pago) }}"
                                       class="btn btn-sm btn-outline-secondary">Recibo</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
```

> **US-0404**: aquí está el historial **por contrato**; el historial **por cliente**
> (y por fecha/método) vive en `pagos/index` con sus filtros.

---

## FASE 7 — Rutas y menú

### 7.1 `routes/web.php`

```php
use App\Http\Controllers\PagoController;
// …
Route::middleware(['auth'])->group(function () {
    // … Sprints 1 a 3 …

    // ── Sprint 4 ───────────────────────────────────────────────
    // ¡Ojo! `pagos/corte` va ANTES del resource: si no, la ruta
    // pagos/{pago} se come «corte» como si fuera un folio (Apéndice B.1).
    Route::get('pagos/corte', [PagoController::class, 'corte'])->name('pagos.corte');
    Route::resource('pagos', PagoController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('pagos/{pago}/estado', [PagoController::class, 'estado'])->name('pagos.estado');
});
```

Rutas nuevas: `resource(4) + corte + estado` = **6** → total **79 rutas**.

### 7.2 Menú — `resources/views/layouts/app.blade.php`

Reemplaza la línea deshabilitada

```blade
            <li class="nav-item"><span class="nav-link disabled text-white-50">Pagos</span></li>
```

por

```blade
            @can('pagos.ver')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pagos.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                       href="{{ route('pagos.index') }}">Pagos</a>
                </li>
            @endcan
```

---

## FASE 8 — Ejecución y verificación

### 8.1 Orden de ejecución

```powershell
# 1) Seeders (idempotentes; NO usar migrate:fresh)
php artisan db:seed --class=MetodoPagoSeeder     # 5 métodos
php artisan db:seed --class=RolPermisoSeeder     # 36 permisos

# 2) Cachés y assets
php artisan config:clear
php artisan view:clear
php artisan route:list                           # 79 rutas
npm run build

# 3) Servidor  (si 8000 lo ocupa otra app: usar --port=8002)
php artisan serve --port=8000
```

### 8.2 Checklist de verificación (Sprint 4)

**Catálogo y permisos (FASE 1)**

- [x] `MetodoPagoSeeder` crea 5 métodos y es repetible (2ª corrida sin duplicados).
- [x] `permissions` = **36**; `pagos.ver/crear/cancelar/corte` existen.
- [x] Cajero con `pagos.ver + crear + corte`; Supervisor con `pagos.ver + corte`;
      Recepción solo `pagos.ver`; Seguridad **sin** permisos de pagos.
      (Verificado leyendo los permisos reales de cada rol en BD.)

**US-0401 — Cobro**

- [x] `+ Nuevo cobro` con contrato precargado (desde «Cobrar» en el expediente)
      llena periodo siguiente y subtotal = precio del contrato.
- [x] Recibo foliado `REC-2026-0001`, `REC-2026-0002`, `REC-2026-0003`…
- [x] `cliente_id` y `usuario_id` se guardan solos; `estado` inicial `PAGADO`.
- [x] El total guardado **coincide** con `subtotal − descuento + impuesto`
      aunque se maquille el campo `total` en el navegador
      (enviado `total=1` → guardado `1500.00`).
- [x] `fecha_fin` del periodo anterior a `periodo_inicio` → error de validación
      («El fin del periodo debe ser igual o posterior al inicio.»).
- [x] Cobrar a un contrato `CANCELADA` → SweetAlert
      «El contrato PEN-2026-0001 está cerrado: no se le puede cobrar.».

**US-0402 — Método y referencia**

- [x] Efectivo/Terminal: referencia opcional. Tarjeta/Transferencia/Depósito: el
      formulario la marca `required` y el servidor rechaza si viene vacía
      («El método «Tarjeta» requiere número de referencia o autorización.»).
- [x] El select solo trae métodos `activo = 1` (con `Terminal` desactivada
      desapareció de las opciones; se reactivó después).

**US-0403 — Recibo**

- [x] `/pagos/{id}` se imprime sin sidebar ni botones; «Guardar como PDF» entrega
      el recibo con folio, cliente, periodo, desglose y total.
      (Comprobado con `emulateMedia({media:'print'})`: navbar y `.d-print-none`
      ocultos, recibo y total visibles.)

**US-0404 — Historial**

- [x] `pagos/index` filtra por texto, estado, método, contrato y rango de fechas;
      el resumen «Cobrado con este filtro» cambia con los filtros.
- [x] El expediente muestra los pagos del contrato con su cobro acumulado
      ($1,500.00 = solo el `PAGADO`).

**US-0405 — Corte de caja**

- [x] `/pagos/corte` con la fecha de hoy lista cobros y totales por método.
- [x] Los pagos `CANCELADO`/`REEMBOLSADO` del día aparecen fuera del total
      (y `PENDIENTE` tampoco cuenta).
- [x] Cajero y Supervisor pueden entrar; Seguridad → 403.
      (Recepción se verificó por permisos: solo `pagos.ver`, sin `pagos.corte`
      → 403; no hay usuario de recepción en la BD.)

**US-0406 — Cancelar / reembolsar**

- [x] `PAGADO` ofrece Cancelado y Reembolsado; exige motivo; el motivo queda en
      `observaciones` con fecha y **el registro sigue ahí**.
- [x] Un recibo `CANCELADO` ya no ofrece movimientos (estado final);
      transición fuera de matriz → 422 «Ese movimiento no está permitido
      desde «Pagado».».
- [x] El contrato con pagos sigue sin poder eliminarse (Sprint 3)
      → «No se puede eliminar: el contrato tiene pagos registrados.».

**US-0407 — Permisos**

- [x] El botón «Cobrar» y `+ Nuevo cobro` solo aparecen con `pagos.crear`.
- [x] Rol **sin** `pagos.crear` (SUPERVISOR) → 403 en `GET /pagos/create`
      *(el borrador decía «Cajero», pero el CAJERO sí tiene `pagos.crear`)*.
- [x] Seguridad → 403 en `/pagos` (sin `pagos.ver`); invitado → redirect a `/login`.
- [x] `POST /pagos/{id}/estado` → 403 para Cajero y Supervisor (solo
      Administrador tiene `pagos.cancelar`).

### 8.3 Matriz de permisos tras el Sprint 4

| Rol | pagos.ver | pagos.crear | pagos.cancelar | pagos.corte |
|---|---|---|---|---|
| ADMINISTRADOR | ✔ | ✔ | ✔ | ✔ |
| CAJERO | ✔ | ✔ | ✖ | ✔ |
| SUPERVISOR | ✔ | ✖ | ✖ | ✔ |
| RECEPCION | ✔ | ✖ | ✖ | ✖ |
| SEGURIDAD | ✖ (403) | ✖ | ✖ | ✖ |

**36 permisos** = 32 previos + `pagos` ×4. (Cancelar/reembolsar queda solo en
Administrador: es una corrección contable, no una operación de caja.)

---

## Apéndice A — Estados, catálogo y reglas de negocio

**Estados del pago:** `PENDIENTE, PAGADO, CANCELADO, REEMBOLSADO`

| Desde | Hacia |
|---|---|
| PENDIENTE | PAGADO, CANCELADO |
| PAGADO | CANCELADO, REEMBOLSADO |
| CANCELADO / REEMBOLSADO | *(ninguna)* |

**Catálogo `metodos_pago`** (5 filas, `requiere_referencia`):

| # | Método | Referencia |
|---|---|---|
| 1 | Efectivo | no |
| 2 | Tarjeta | **sí** |
| 3 | Transferencia | **sí** |
| 4 | Depósito | **sí** |
| 5 | Terminal | no |

**Reglas**

- `total = subtotal − descuento + impuesto`, **calculado en el servidor**.
- `descuento ≤ subtotal` (validación `lte:subtotal`; con `max:subtotal` Laravel intenta parsear el nombre del campo como número y revienta con `NumberFormatException`).
- Folio `REC-AAAA-NNNN` único con reintento ante `1062` (3 intentos).
- `cliente_id` y `usuario_id` se copian/levantan en `store`; el navegador no los envía.
- Periodo sugerido: continúa al último `periodo_fin` + 1 día; dura
  `ContratoPension::MESES[periodicidad]` y se **acota** a `fecha_fin` del contrato.
- Un contrato `CANCELADA`/`FINALIZADA` no admite cobros (regla en `PagoRequest`).
- Un contrato con pagos **no se puede eliminar** (FK `restrictOnDelete`, Sprint 3).
- Corte: suma solo `PAGADO`; el resto se lista como excluido.

---

## Apéndice B — Problemas conocidos

1. **Orden de rutas.** `Route::get('pagos/corte')` debe ir **antes** de
   `Route::resource('pagos', …)`: Laravel resuelve por orden de registro y
   `pagos/{pago}` capturaría `/pagos/corte` como si `corte` fuera el folio
   (error 404 de modelo o 500). Mismo motivo por el que el `resource` va
   `->only(['index','create','store','show'])`.
2. **`total` se recalcula**: si pruebas desde el navegador enviando otro valor,
   verás que se guarda el correcto. No es un bug.
3. **`syncPermissions` borra y reescribe**: al editar `RolPermisoSeeder` hay que
   pegar la lista **completa** de cada rol; si dejas fuera un permiso de Sprint 1-3,
   desaparece de ese rol.
4. **Sin librería de PDF**: se imprime con `window.print()`. Si se necesita un
   archivo generado en el servidor:
   `composer require barryvdh/laravel-dompdf` y en `show()` →
   `return PDF::loadView('pagos.show', compact('pago'))->download('recibo.pdf')`,
   cuidando que la vista no use `@extends('layouts.app')` (dompdf no ejecuta JS ni
   hojas de estilo externas).
5. **No hay edición ni borrado de pagos**: cualquier corrección es
   cancelar/reembolsar y volver a cobrar; así se conserva el rastro.
6. **`fecha_pago` es `dateTime` en la BD pero el formulario envía `date`**: se
   convierte con `Carbon::parse()` en `store()`; el recibo muestra la hora en la que
   se registró.
7. **Periodo acotado**: si el contrato vence en 10 días, el último periodo se recorta
   a `fecha_fin` (no se cobra un mes completo que no se va a usar).
8. **`descuento`/`impuesto` como `''`**: el formulario los envía vacíos; el modelo
   los casta a `decimal:2` y `store()` aplica `0` por defecto.

---

## Apéndice C — Próximo sprint

**Sprint 5 — Entradas y Salidas / Accesos (Módulos 5 y 9):** `movimientos_acceso`,
`puertas`, `dispositivos`, cálculo de permanencia, QR (mock) y listado auditable de
entradas/salidas. Requiere: permanencia → cobro por hora → ligar al `Pago`.

---

## Apéndice D — Registro de ejecución

**Fecha:** 08/10/2026 · **Entorno:** Laravel 10.50.3 + PHP 8.1.18 + MySQL `pensiones`

> Nota de entorno: `php` en el PATH ahora resuelve a `C:\php83\php.exe` (**8.5.11**,
> avisa con deprecaciones de `PDO::MYSQL_ATTR_SSL_CA`). Se usó indistintamente
> `php` y `C:\php\php.exe` (8.1.18); `route:list --json` con 8.5 ensucia el JSON
> con esas deprecaciones, por lo que el conteo se hizo con `C:\php\php.exe`.
> El servidor dev quedó en **http://127.0.0.1:8002** porque el puerto 8000 estaba
> ocupado por otra aplicación (HELPDESK) de otro usuario en la misma máquina.

### Comandos ejecutados

```powershell
php artisan db:seed --class=MetodoPagoSeeder   # 5 métodos (2ª corrida: sigue en 5)
php artisan db:seed --class=RolPermisoSeeder   # 36 permisos
php artisan config:clear; php artisan view:clear
C:\php\php.exe artisan route:list              # 79 rutas
npm run build                                  # app-2jFFs6xI.css + app-CGdMZqlu.js
C:\php\php.exe artisan serve --port=8002
```

### Verificación por historia de usuario

| US | Qué se probó | Resultado |
|---|---|---|
| US-0401 | Cobro desde «Cobrar» con periodo siguiente y subtotal 1500; folio `REC-2026-0001`; `cliente_id=1`, `usuario_id=1`; envío con `total=1` maquillado → guardado `1500.00`; `periodo_fin < periodo_inicio` → 422 del servidor; contrato cerrado → SweetAlert | ✅ |
| US-0402 | Tarjeta/Transferencia marcan `required` la referencia; servidor rechaza vacía («El método «Tarjeta» requiere número de referencia o autorización.»); con referencia `AUTH-778899` guarda; `Terminal` desactivada desaparece del select (se reactivó) | ✅ |
| US-0403 | `/pagos/1` con `@media print`: navbar y botones ocultos, recibo (folio, cliente, contrato, periodo, desglose, «Registró», total) visible | ✅ |
| US-0404 | Índice con filtros `q/estado/metodo/contrato/desde/hasta` (comprobados por URL), resumen «Cobrado con este filtro» ($1,500.00 / 1 recibo), expediente con tabla de pagos y «Cobrado: $1,500.00» | ✅ |
| US-0405 | Corte 08/10: $3,000.00 / 2 cobros con desglose por método; tras cancelar: $1,500.00 / 1 cobro; `PENDIENTE` fuera del total; corte del 07/10 = $0.00 | ✅ |
| US-0406 | `PAGADO` → Cancelado/Reembolsado; `PENDIENTE` → Pagado/Cancelado; `CANCELADO` sin formulario; motivo obligatorio (HTML5 y servidor) queda en `observaciones` «[08/10/2026 19:48] Cancelado: Cobro capturado por error»; registro intacto; transición inválida → 422 | ✅ |
| US-0407 | «Cobrar» y «+ Nuevo cobro» solo con `pagos.crear`; SUPERVISOR 403 en `/pagos/create`; SEGURIDAD 403 en `/pagos`, `/create`, `/corte`, `/{id}` y menú oculto; invitado → `/login` | ✅ |
| Permisos | Cajero 200 en index/create/corte/show y 403 en `POST pagos/{id}/estado`; Supervisor 200 en index/corte/show y 403 en `create` y `estado`; Administrador con todas las rutas | ✅ |

**Matriz final comprobada en navegador (200 / 403 / →login):**

| Rol | `/pagos` | `/pagos/create` | `/pagos/corte` | `POST …/estado` | Botón «Cobrar» | Menú Pagos |
|---|---|---|---|---|---|---|
| ADMINISTRADOR | 200 | 200 | 200 | 200 | ✔ | ✔ |
| CAJERO | 200 | 200 | 200 | **403** | ✔ | ✔ |
| SUPERVISOR | 200 | **403** | 200 | **403** | ✖ | ✔ |
| SEGURIDAD | **403** | **403** | **403** | 403 | ✖ | ✖ |
| INVITADO | → `/login` | → `/login` | → `/login` | → `/login` | ✖ | ✖ |

### Desviaciones respecto a la guía

1. **`MetodoPago::$table`** — Eloquent deducía `metodo_pagos` y la tabla real es
   `metodos_pago` → 500 en `/pagos`. Añadido `protected $table = 'metodos_pago';`
   (también corregido en §2.1 de esta guía).
2. **`descuento.max:subtotal`** — `max:` con el nombre de otro campo hace que
   Laravel intente parsear `"subtotal"` como número (`Brick\Math` →
   `NumberFormatException` en la validación). Cambiado a `lte:subtotal`
   (clave de mensaje `descuento.lte`).
3. **`referencia` con closure en esa clave** — Laravel omite las reglas no
   implícitas cuando el valor es `null` (campo vacío), así que la validación de
   US-0402 no corría: el cobro se guardaba sin referencia. El closure se movió a
   `metodo_pago_id` (siempre presente) y `referencia` quedó
   `nullable|string|max:100`.
4. **Fecha del recibo** — `format('d/m/Y H:i')` mostraba «00:00» en una columna
   `date`; se cambió a `format('d/m/Y')` en `index` y `show`.
5. **Corte imprimible** — `corte.blade.php` no tenía `@media print`; se añadió el
   mismo bloque de `show` (navbar y botones fuera del papel).
6. **Usuario de prueba SEGURIDAD** — no existía en BD; se creó
   `seguridad@pension.mx` / `password` con rol `SEGURIDAD` para poder probar
   US-0407 (los usuarios no están en ningún seeder).
7. **Checklist US-0407** — el borrador decía «Cajero sin `pagos.crear` → 403»;
   el CAJERO sí tiene `pagos.crear`, el 403 se verificó con SUPERVISOR.
8. **Puerto** — 8000 ocupado por otra app → servidor en **8002**.

### Estado de los datos al terminar

- `metodos_pago` = 5 activos (Depósito, Efectivo, Tarjeta, Terminal, Transferencia).
- `permissions` = 36 · `roles` = 5 · `users` = 4
  (admin, cajero, supervisor y **seguridad** recién creado).
- `pagos` = **3**: `REC-2026-0001` PAGADO $1,500.00 (Tarjeta, ref. `AUTH-778899`),
  `REC-2026-0002` CANCELADO $1,500.00 (Efectivo, motivo en `observaciones`),
  `REC-2026-0003` PENDIENTE $1,500.00 (Efectivo) — los tres de `PEN-2026-0002`.
- Contratos = 2 (`PEN-2026-0001` CANCELADA, `PEN-2026-0002` ACTIVA),
  tarifas = 10, cajones sin cambios (A-01 ocupado por el contrato).
- `laravel.log`: sin errores nuevos tras las tres correcciones (los únicos
  `local.ERROR` de hoy son los dos bugs ya corregidos y un typo en un `tinker`).

### Estado final

**Sprint 4 — Completado.** FASES 1–8 ejecutadas y verificadas en navegador
(admin, cajero, supervisor, seguridad e invitado) contra el servidor en
`http://127.0.0.1:8002`; `php -l` limpio en los 9 archivos PHP nuevos/modificados,
79 rutas, `npm run build` sin errores. Queda pendiente únicamente el **Sprint 5**
(`GUIA-SPRINT5` según `Apéndice C`).






