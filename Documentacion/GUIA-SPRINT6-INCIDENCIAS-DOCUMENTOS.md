# Guía paso a paso - Sprint 6: Incidencias y documentos del vehículo (Módulo 10)

Guía ejecutable al estilo de `GUIA-SPRINT5-ENTRADAS-SALIDAS-ACCESOS.md`: cada bloque
de código es un archivo **completo** (se copia tal cual), salvo que el encabezado
diga «fragmento: añadir» o «fragmento: reemplazar». Al final queda el **Apéndice D**
para registrar la ejecución real.

> **Reglas de la casa (no romper):** nunca `php artisan ui bootstrap --auth`,
> nunca `php artisan migrate:fresh` (solo `db:seed --class=...` y `migrate`
> aditivo), nunca editar la BD a mano fuera de los seeders, y todo texto en
> español con acentos escrito desde la herramienta de edición (PowerShell
> rompe el UTF-8).

> **Este sprint NO trae migraciones:** las 4 tablas (`tipos_incidencia`,
> `incidencias`, `incidencia_archivos`, `vehiculo_documentos`) se crearon en el
> Sprint 0 desde `MIGRACIONES.md` §5.5/§5.16/§5.17/§5.19 y están vacías.

---

## 0. Alcance

### Historias de usuario (`PLAN-SCRUM.md` §7)

| ID | Historia de usuario | Pts | Prioridad | En esta guía |
|---|---|---|---|---|
| US-0601 | Como **seguridad**, quiero registrar una incidencia (daño, accidente, objeto olvidado, vehículo abandonado, problema de pago) vinculada al vehículo y cliente. | 8 | S | ✅ completo |
| US-0602 | Como **seguridad**, quiero subir fotos/videos/documentos a la incidencia, para guardar la evidencia. | 5 | S | ✅ subida real a `storage/` |
| US-0603 | Como **supervisor**, quiero mover la incidencia entre estados (abierta → en proceso → cerrada), para dar seguimiento. | 5 | S | ✅ matriz de transiciones |
| US-0604 | Como **administrador**, quiero administrar los tipos de incidencia, para personalizar los motivos. | 3 | S | ✅ CRUD con baja lógica |
| US-0605 | Como **administrador**, quiero el expediente de documentos del vehículo (tarjeta de circulación, seguro) con fecha de vencimiento y alertas, para prevenir vencimientos. | 8 | S | ✅ expediente + alertas 30 días |
| | **Total** | **29** | | |

**BD:** `incidencias`, `tipos_incidencia`, `incidencia_archivos`,
`vehiculo_documentos` **ya existen** (Sprint 0) y están vacías. **0 migraciones
nuevas.**

### Estado actual (tras Sprint 5)

- Rutas **94**, permisos **40** (`accesos.*` incluidos), usuarios **5**
  (`admin`, `cajero`, `supervisor`, `seguridad`, `recepcion`), roles 5.
- **Ajuste 10/10/2026:** `CAJERO` recibió `contratos.crear`,
  `contratos.editar` y `contratos.cambiar_estado` (da de alta, corrige y
  activa pensiones). No cambia el total de permisos (sigue en **40**): solo
  cambia el reparto (CAJERO 7 → 10). Ver desviación registrada en
  `GUIA-SPRINT3-TARIFAS-Y-PENSIONES.md`.
- **Ajuste 12/10/2026:** rediseño del alta de pensiones (ver desviación en
  `GUIA-SPRINT3`): nuevas rutas `GET clientes/buscar` y `GET vehiculos/buscar`
  (autocompletado por placas) → base **94 rutas**; los objetivos de esta guía
  quedan **94 → 114** (no 92 → 112). Añade 1 migración aditiva ya aplicada
  (`2026_10_10_100000_add_datos_nueva_pension...`); los permisos siguen en
  **40**.
- Catálogos sembrados: 4 zonas, 5 tipos de vehículo, 10 tarifas, 5 métodos de
  pago, 7 dispositivos, 2 puertas, **7 tipos de incidencia = 0** (aún no sembrados).
- **La BD quedó casi sin datos operativos** (limpieza del equipo): `espacios`,
  `pagos` y `movimientos_acceso` = **0**. El 10/10/2026 se repobló lo mínimo
  para probar el ajuste del CAJERO: 1 cliente (`Juan Perez Lopez`), 1 vehículo
  (`ABC1234`) y 1 contrato (`PEN-2026-0001`, id 5, **ACTIVA** — alta y
  activación hechas por el cajero). El paso 7.0 de esta guía
  solo falta crear el segundo vehículo (`XYZ9999`).
- **12/10/2026 (tras el rediseño del alta):** datos actuales = 2 clientes
  (`Juan Perez Lopez`, `Maria Lopez`), 2 vehículos (`ABC1234`, `XYZ9876`),
  5 contratos (`PEN-2026-0001` ACTIVA, `PEN-2026-0002` MENSUAL precio 1600,
  `PEN-2026-0003`, `PEN-2026-0004` y `PEN-2026-0005` con
  empresa/fecha_entrada/documentación), 3 pagos (`REC-2026-0001` 6 meses
  $9,600, `REC-2026-0002` y `REC-2026-0003` de 1 mes $1,500 c/u). Cajones
  siguen en 0. Para el paso 7.0 ya existe un segundo vehículo distinto de
  `ABC1234` (`XYZ9876`); si el paso pide `XYZ9999` específicamente, crearlo.
- El enlace del menú «Incidencias» está como
  `<li class="nav-item"><span class="nav-link disabled text-white-50">Incidencias</span></li>`
  (`layouts/app.blade.php` línea 100); «Accesos» ya quedó activo.
- Dashboard: tarjeta «Vehículos dentro» = 0; «Espacios ocupados»/«Ingresos» en `—`
  (Sprint 8); alerta de contratos por vencer vacía.
- `public/storage` **ya existe** (symlink de `storage:link`), y el proyecto ya
  sube archivos en `VehiculoController::guardarFoto()` → patrón reutilizable.
- Servidor dev en `http://127.0.0.1:8002` (el 8000 lo ocupa otra aplicación);
  `php` del PATH resuelve a 8.5.11 → usar `C:\php\php.exe` (8.1.18) cuando se
  necesite salida limpia (`route:list --json`).

### Artefactos que crea este sprint

| Tipo | Archivo |
|---|---|
| Migración | **Ninguna** (las 4 tablas ya existen) |
| Seeder | `database/seeders/TipoIncidenciaSeeder.php` (nuevo) |
| Seeder | `database/seeders/RolPermisoSeeder.php` (+ `incidencias.*`, `documentos.*`), `DatabaseSeeder.php` |
| Modelo | `app/Models/TipoIncidencia.php`, `Incidencia.php`, `IncidenciaArchivo.php`, `VehiculoDocumento.php` |
| Modelo | `app/Models/Vehiculo.php` (+ `incidencias()`, `documentos()`, `contratoActivo()`) |
| Request | `app/Http/Requests/IncidenciaRequest.php`, `IncidenciaEstadoRequest.php`, `ArchivoRequest.php`, `TipoIncidenciaRequest.php`, `DocumentoRequest.php` |
| Controlador | `app/Http/Controllers/IncidenciaController.php`, `TipoIncidenciaController.php`, `DocumentoController.php` |
| Vistas | `resources/views/incidencias/{index,create,edit,show,_form}.blade.php` |
| Vistas | `resources/views/tipos_incidencia/{index,create,edit,_form}.blade.php` |
| Vistas | `resources/views/documentos/{index,expediente}.blade.php` |
| Rutas/menú | `routes/web.php` (20 rutas → **114**), `layouts/app.blade.php` (grupo Incidencias), `HomeController` + `home.blade.php` (alerta de documentos) |
| Enlaces | `vehiculos/index.blade.php` (botón «Documentos»), `accesos/show.blade.php` (incidencias del vehículo) |

---

## 1. Decisiones del sprint

1. **Cero migraciones.** Las 4 tablas están en la BD desde el Sprint 0 (27
   migraciones). Se comprueba con `migrate` («Nothing to migrate») y no se toca
   el esquema. Único artefacto de BD: `TipoIncidenciaSeeder`.
2. **7 tipos de incidencia sembrados del Excel** (`MIGRACIONES.md` §5.5):
   Daño, Accidente, Intento de acceso, Objeto perdido, Vehículo abandonado,
   Problema de pago, Otro. Idempotente (`updateOrInsert` por nombre).
3. **`cliente_id` y `contrato_pension_id` se derivan del vehículo** al guardar
   (el usuario solo elige el vehículo): `incidencias.cliente_id` es `NOT NULL`
   y el contrato se toma si hay uno `ACTIVA` vigente (`NULL` si no hay).
   La incidencia queda ligada al cliente correcto aunque el vehículo cambie de
   dueño después.
4. **Estados y transiciones** (patrón `ContratoPension::TRANSICIONES`):
   `ABIERTA → [EN PROCESO, CERRADA]`, `EN PROCESO → [ABIERTA, CERRADA]`,
   `CERRADA → [ABIERTA]` (reabrir). La transición se valida **en servidor**
   (`IncidenciaEstadoRequest`), no solo en la interfaz.
5. **El permiso se evalúa en el `FormRequest` antes que la validación.** Cada
   request nuevo devuelve `can(...)` en `authorize()`: así un rol sin permiso
   recibe **403** (no un 422 confuso) y un rol con permiso y payload vacío
   recibe **422**. El controlador además repite `$this->authorize(...)` en los
   métodos `GET` (que no tienen request propio).
6. **Evidencia con subida real a disco** (US-0602): `UploadedFile::store()` en
   `storage/app/public/incidencias/{id}/` con disco `public`, igual que
   `VehiculoController::guardarFoto()`; en la BD se guarda la **ruta relativa**
   (`incidencias/1/foto.jpg`) y la vista arma `asset('storage/'.$a->url)`.
   > **Aclaración** (Apéndice B): la «decisión 7» de la guía 5 (evidencia LPR
   > por URL, sin disco) aplica solo a `foto_entrada`/`foto_salida` de las
   > cámaras; la subida de archivos del Módulo 10 sí usa `storage/`, como ya
   > hacía el Módulo 3 desde la Guía 1.
7. **Tipos de archivo y límites:** `FOTO` → imagen jpg/png/webp ≤ 2 MB;
   `VIDEO` → mp4/webm/ogg ≤ 20 MB; `DOCUMENTO` → pdf/office/txt ≤ 10 MB.
   El `tipo_archivo` lo elige el usuario y de él dependen las reglas
   (`ArchivoRequest` condicional).
8. **La evidencia se agrega desde la ficha** (`/incidencias/{id}`), no en el
   alta: primero se deja la incidencia, luego se adjunta. El alta puede quedar
   sin evidencia (la columna es opcional). Así `store()` no valida arrays de
   archivos.
9. **Sin `destroy` de incidencias:** son historial. Solo cambian de estado
   (US-0603). El `resource` se declara `->except(['destroy'])`.
10. **Tipos de incidencia en uso no se borran:** `incidencias.tipo_incidencia_id`
    es `restrictOnDelete`; si el tipo tiene incidencias, `destroy()` lo
    **desactiva** y lo informa (patrón `TipoVehiculoController::destroy`).
11. **Expediente por vehículo en dos vistas:**
    - `GET /vehiculos/{vehiculo}/documentos` → expediente de **ese** vehículo
      (alta, edición y baja de sus documentos).
    - `GET /documentos` → **índice global con alertas** (vencidos y por vencer,
      filtros por placas/tipo/estado). Es la pantalla que «previene
      vencimientos» (US-0605) y la que enlaza el menú.
12. **`vehiculo_documentos.estado` lo mantiene el servidor** (colgar de lo que
    escriba el usuario provocaría mentiras en las alertas): en el hook
    `saving` del modelo, `VENCIDO` si `fecha_vencimiento` < hoy, `VIGENTE` en
    cualquier otro caso (`NULL` = sin vencimiento). El listado agrega un badge
    derivado «Por vencer» para lo que vence en ≤ **30 días**
    (`VehiculoDocumento::DIAS_AVISO = 30`).
13. **Permisos nuevos (40 → 49):** `incidencias.ver`, `incidencias.crear`,
    `incidencias.editar`, `incidencias.cambiar_estado`, `incidencias.tipos`
    (todo el catálogo de tipos) y `documentos.ver`, `documentos.crear`,
    `documentos.editar`, `documentos.eliminar`. Reparto:
    - ADMINISTRADOR → 9 (todas, `syncPermissions(Permission::all())`).
    - SEGURIDAD → `incidencias.ver + crear + editar`, `documentos.ver` (4).
    - SUPERVISOR → `incidencias.ver + editar + cambiar_estado`, `documentos.ver` (4).
    - RECEPCION → `incidencias.ver`, `documentos.ver + crear` (3).
    - CAJERO → `documentos.ver` (1).
14. **20 rutas nuevas (94 → 114)** y, otra vez, **las estáticas antes del
    `resource`** (lección de Sprint 4/5).
15. **Sin dependencias nuevas:** no se instala npm ni se toca
    `resources/js/app.js`; el solo `@push('scripts')` de las vistas basta
    (el `@stack('scripts')` ya está en el layout desde el Sprint 5).

---

## FASE 1 - Base de datos y seeders

### 1.1 Verificar que las tablas ya existen

```powershell
C:\php\php.exe artisan migrate
# Nothing to migrate.
```

Si algo falara aquí, el esquema se arma desde `Documentacion/MIGRACIONES.md`
§5.5, §5.16, §5.17 y §5.19 (nunca `migrate:fresh`).

### 1.2 `database/seeders/TipoIncidenciaSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoIncidenciaSeeder extends Seeder
{
    /**
     * Catálogo de tipos de incidencia (hoja "tipos_incidencia" del Excel).
     * Idempotente: updateOrInsert por nombre.
     */
    public function run(): void
    {
        $tipos = [
            ['nombre' => 'Daño',               'descripcion' => 'Golpes, rayones o averías en el vehículo.'],
            ['nombre' => 'Accidente',          'descripcion' => 'Choque o colisión dentro del recinto.'],
            ['nombre' => 'Intento de acceso',  'descripcion' => 'Intento de entrada o salida no autorizada.'],
            ['nombre' => 'Objeto perdido',     'descripcion' => 'Objeto olvidado o extraviado en el estacionamiento.'],
            ['nombre' => 'Vehículo abandonado', 'descripcion' => 'Vehículo sin contrato que lleva días en el recinto.'],
            ['nombre' => 'Problema de pago',   'descripcion' => 'Disputa o incidencia con un cobro.'],
            ['nombre' => 'Otro',               'descripcion' => 'Motivo no clasificado (especificar en la descripción).'],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipos_incidencia')->updateOrInsert(
                ['nombre' => $tipo['nombre']],
                [
                    'descripcion'  => $tipo['descripcion'],
                    'activo'       => true,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }
    }
}
```

### 1.3 `database/seeders/RolPermisoSeeder.php` - ampliar

**Fragmento: reemplazar** el arreglo `$modulos` (agregar los dos módulos
nuevos al final, junto al comentario del Sprint 5):

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
            // Sprint 4
            'pagos'           => ['ver', 'crear', 'cancelar', 'corte'],
            // Sprint 5 — Módulos 5 y 9
            'accesos'         => ['ver', 'registrar', 'cobrar', 'configurar'],
            // Sprint 6 — Módulo 10
            'incidencias'     => ['ver', 'crear', 'editar', 'cambiar_estado', 'tipos'],
            'documentos'      => ['ver', 'crear', 'editar', 'eliminar'],
        ];
```

> Los permisos usan `firstOrCreate`, así que re-correr el seeder **agrega** los
> 9 nuevos sin tocar los 40 existentes (40 → 49).

**Fragmento: añadir** en el bloque `RECEPCION` (después de las líneas del
Sprint 5):

```php
            // Sprint 6 — reporta incidencias y da de alta documentos (US-0601, US-0605)
            'incidencias.ver',
            'documentos.ver', 'documentos.crear',
```

**Fragmento: añadir** en el bloque `SUPERVISOR`:

```php
            // Sprint 6 — da seguimiento y cierra incidencias (US-0603)
            'incidencias.ver', 'incidencias.editar', 'incidencias.cambiar_estado',
            'documentos.ver',
```

**Fragmento: añadir** en el bloque `CAJERO`:

```php
            // Sprint 6 — consulta el expediente al cobrar (US-0605)
            'documentos.ver',
```

**Fragmento: añadir** en el bloque `SEGURIDAD`:

```php
            // Sprint 6 — registra incidencias con evidencia (US-0601, US-0602)
            'incidencias.ver', 'incidencias.crear', 'incidencias.editar',
            'documentos.ver',
```

`ADMINISTRADOR` no se toca: ya hace `syncPermissions(Permission::all())` y se
queda con las 49.

### 1.4 `database/seeders/DatabaseSeeder.php`

**Fragmento: reemplazar** el arreglo de `$this->call([...])`:

```php
        $this->call([
            ZonaSeeder::class,
            TipoVehiculoSeeder::class,
            TarifaSeeder::class,
            MetodoPagoSeeder::class,   // Sprint 4
            DispositivoSeeder::class,  // Sprint 5
            PuertaSeeder::class,       // Sprint 5 — requiere dispositivos
            TipoIncidenciaSeeder::class, // Sprint 6 — nuevo (Módulo 10)
            RolPermisoSeeder::class,
        ]);
```

### 1.5 Ejecutar

```powershell
C:\php\php.exe artisan db:seed --class=TipoIncidenciaSeeder
# 7 tipos de incidencia
C:\php\php.exe artisan db:seed --class=RolPermisoSeeder
# 49 permisos (los 40 previos intactos), 5 roles
```

---

## FASE 2 - Modelos

### 2.1 `app/Models/TipoIncidencia.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoIncidencia extends Model
{
    protected $table = 'tipos_incidencia';

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
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

### 2.2 `app/Models/Incidencia.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incidencia extends Model
{
    /** Valores exactos de la migración (VARCHAR, no ENUM). */
    public const ESTADOS = ['ABIERTA', 'EN PROCESO', 'CERRADA'];

    public const ESTADO_LABELS = [
        'ABIERTA'    => 'Abierta',
        'EN PROCESO' => 'En proceso',
        'CERRADA'    => 'Cerrada',
    ];

    public const ESTADO_CLASSES = [
        'ABIERTA'    => 'bg-danger',
        'EN PROCESO' => 'bg-warning text-dark',
        'CERRADA'    => 'bg-success',
    ];

    /** Matriz de transiciones permitidas (US-0603). */
    public const TRANSICIONES = [
        'ABIERTA'    => ['EN PROCESO', 'CERRADA'],
        'EN PROCESO' => ['ABIERTA', 'CERRADA'],
        'CERRADA'    => ['ABIERTA'],   // reabrir
    ];

    protected $table = 'incidencias';

    protected $fillable = [
        'vehiculo_id', 'cliente_id', 'contrato_pension_id', 'tipo_incidencia_id',
        'fecha_hora', 'descripcion', 'estado', 'usuario_id',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contratoPension(): BelongsTo
    {
        return $this->belongsTo(ContratoPension::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoIncidencia::class, 'tipo_incidencia_id');
    }

    /** Quien la registró. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(IncidenciaArchivo::class);
    }

    public function scopeFiltrar(
        Builder $query,
        ?string $q,
        ?string $estado,
        ?int $tipoId,
        ?string $desde,
        ?string $hasta
    ): Builder {
        return $query
            ->when($q, fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->where('descripcion', 'like', "%{$q}%")
                ->orWhereHas('vehiculo', fn (Builder $v) => $v->where('placas', 'like', "%{$q}%"))
                ->orWhereHas('cliente', fn (Builder $c) => $c
                    ->where('nombre', 'like', "%{$q}%")
                    ->orWhere('apellido_paterno', 'like', "%{$q}%"))))
            ->when($estado, fn (Builder $b) => $b->where('estado', $estado))
            ->when($tipoId, fn (Builder $b) => $b->where('tipo_incidencia_id', $tipoId))
            ->when($desde, fn (Builder $b) => $b->where('fecha_hora', '>=', "{$desde} 00:00:00"))
            ->when($hasta, fn (Builder $b) => $b->where('fecha_hora', '<=', "{$hasta} 23:59:59"))
            ->orderByDesc('fecha_hora');
    }

    /** Estados a los que puede pasar desde el actual (vacío = sin cambios). */
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

### 2.3 `app/Models/IncidenciaArchivo.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidenciaArchivo extends Model
{
    public const TIPOS = ['FOTO', 'VIDEO', 'DOCUMENTO'];

    public const TIPO_LABELS = [
        'FOTO'      => 'Foto',
        'VIDEO'     => 'Video',
        'DOCUMENTO' => 'Documento',
    ];

    protected $table = 'incidencia_archivos';

    protected $fillable = [
        'incidencia_id', 'tipo_archivo', 'url', 'nombre_archivo', 'mime_type',
    ];

    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    /** Ruta pública completa (la BD guarda la ruta relativa). */
    public function getRutaAttribute(): string
    {
        return asset('storage/' . $this->url);
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_LABELS[$this->tipo_archivo] ?? $this->tipo_archivo;
    }
}
```

### 2.4 `app/Models/VehiculoDocumento.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiculoDocumento extends Model
{
    public const TIPOS = [
        'TARJETA_CIRCULACION', 'SEGURO', 'IDENTIFICACION', 'CONTRATO', 'OTRO',
    ];

    public const TIPO_LABELS = [
        'TARJETA_CIRCULACION' => 'Tarjeta de circulación',
        'SEGURO'              => 'Seguro',
        'IDENTIFICACION'      => 'Identificación',
        'CONTRATO'            => 'Contrato',
        'OTRO'                => 'Otro',
    ];

    public const ESTADOS = ['VIGENTE', 'VENCIDO'];

    /** Días de aviso previos al vencimiento (US-0605). */
    public const DIAS_AVISO = 30;

    protected $table = 'vehiculo_documentos';

    protected $fillable = [
        'vehiculo_id', 'tipo_documento', 'numero', 'fecha_emision',
        'fecha_vencimiento', 'archivo_url', 'estado',
    ];

    protected $casts = [
        'fecha_emision'     => 'date',
        'fecha_vencimiento' => 'date',
    ];

    /**
     * `estado` lo mantiene el servidor: el usuario solo pone la fecha.
     * (Decisión 12; la columna existe en la migración Sprint 0.)
     */
    protected static function booted(): void
    {
        static::saving(function (self $documento) {
            $vencida = $documento->fecha_vencimiento
                && $documento->fecha_vencimiento->lt(today());

            $documento->estado = $vencida ? 'VENCIDO' : 'VIGENTE';
        });
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /** Vencidos + por vencer (<= DIAS_AVISO días): lo que alimenta las alertas. */
    public function scopeAlertas(Builder $query): Builder
    {
        return $query->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<=', today()->addDays(self::DIAS_AVISO))
            ->orderBy('fecha_vencimiento');
    }

    public function scopeFiltrar(Builder $query, ?string $q, ?string $tipo, ?string $estado): Builder
    {
        return $query
            ->when($q, fn (Builder $b) => $b->whereHas('vehiculo', fn (Builder $v) => $v
                ->where('placas', 'like', "%{$q}%")
                ->orWhere('marca', 'like', "%{$q}%")))
            ->when($tipo, fn (Builder $b) => $b->where('tipo_documento', $tipo))
            ->when($estado === 'VENCIDO', fn (Builder $b) => $b
                ->whereNotNull('fecha_vencimiento')
                ->where('fecha_vencimiento', '<', today()))
            ->when($estado === 'POR_VENCER', fn (Builder $b) => $b
                ->whereNotNull('fecha_vencimiento')
                ->whereBetween('fecha_vencimiento', [today(), today()->addDays(self::DIAS_AVISO)]))
            ->when($estado === 'VIGENTE', fn (Builder $b) => $b
                ->where(fn (Builder $w) => $w->whereNull('fecha_vencimiento')
                    ->orWhere('fecha_vencimiento', '>', today()->addDays(self::DIAS_AVISO))))
            ->orderBy('fecha_vencimiento');
    }

    public function diasParaVencer(): ?int
    {
        if (! $this->fecha_vencimiento) {
            return null;
        }

        // Positivo = aún no vence; negativo = ya venció (-1 = venció ayer).
        return (int) ($this->fecha_vencimiento->diffInDays(today(), false) * -1);
    }

    public function getVigenciaLabelAttribute(): string
    {
        if ($this->fecha_vencimiento && $this->fecha_vencimiento->lt(today())) {
            return 'Vencido';
        }

        $dias = $this->diasParaVencer();

        return ($dias !== null && $dias <= self::DIAS_AVISO) ? 'Por vencer' : 'Vigente';
    }

    public function getVigenciaClaseAttribute(): string
    {
        return match ($this->vigencia_label) {
            'Vencido'    => 'bg-danger',
            'Por vencer' => 'bg-warning text-dark',
            default      => 'bg-success',
        };
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_LABELS[$this->tipo_documento] ?? $this->tipo_documento;
    }
}
```

> `diasParaVencer()` devuelve días **positivos** cuando aún no vence y
> **negativos** cuando ya venció (`-1` = venció ayer). Si no hay fecha, `null`.

### 2.5 `app/Models/Vehiculo.php` - ampliar

**Fragmento: añadir** (junto a las demás relaciones):

```php
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(VehiculoDocumento::class);
    }

    /** Contrato ACTIVA más reciente: con él se precarga la incidencia (US-0601). */
    public function contratoActivo(): HasOne
    {
        return $this->hasOne(ContratoPension::class)
            ->where('estado', 'ACTIVA')
            ->orderByDesc('fecha_inicio');
    }
```

---

## FASE 3 - Form Requests

Todos siguen la **decisión 5**: `authorize()` devuelve el `can(...)` de su ruta,
así el 403 llega antes que la validación.

### 3.1 `app/Http/Requests/IncidenciaRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->routeIs('incidencias.store')
            ? 'incidencias.crear'
            : 'incidencias.editar') ?? false;
    }

    public function rules(): array
    {
        return [
            // El vehículo solo se pide al crear: editar no lo cambia (decisión 9).
            'vehiculo_id' => array_merge(
                $this->routeIs('incidencias.store') ? ['required'] : ['nullable'],
                ['integer', Rule::exists('vehiculos', 'id')->where('activo', true)]
            ),
            'tipo_incidencia_id' => [
                'required', 'integer',
                Rule::exists('tipos_incidencia', 'id')->where('activo', true),
            ],
            'fecha_hora' => [
                'required', 'date',
                // Tolerancia de 5 minutos por el reloj del navegador (decisión del Sprint 5).
                'before_or_equal:' . now()->addMinutes(5),
            ],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehiculo_id.required'         => 'Selecciona el vehículo.',
            'vehiculo_id.exists'           => 'Ese vehículo no existe o está dado de baja.',
            'tipo_incidencia_id.required'  => 'Selecciona el tipo de incidencia.',
            'tipo_incidencia_id.exists'    => 'Ese tipo de incidencia no existe o está inactivo.',
            'fecha_hora.required'          => 'Indica la fecha y hora de la incidencia.',
            'fecha_hora.before_or_equal'   => 'La fecha y hora de la incidencia no puede ser futura.',
            'descripcion.max'              => 'La descripción no puede exceder 2000 caracteres.',
        ];
    }
}
```

### 3.2 `app/Http/Requests/IncidenciaEstadoRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\Incidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidenciaEstadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('incidencias.cambiar_estado') ?? false;
    }

    public function rules(): array
    {
        $incidencia = $this->route('incidencia');
        $permitidos = $incidencia instanceof Incidencia
            ? $incidencia->transicionesPermitidas()
            : [];

        return [
            'estado' => [
                'required',
                Rule::in(Incidencia::ESTADOS),
                // Transición real desde el estado actual (decisión 4).
                Rule::in($permitidos),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Selecciona el nuevo estado.',
            'estado.in'       => 'Ese estado no es válido o no está permitido desde el estado actual.',
        ];
    }
}
```

### 3.3 `app/Http/Requests/ArchivoRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\IncidenciaArchivo;
use Illuminate\Foundation\Http\FormRequest;

class ArchivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('incidencias.editar') ?? false;
    }

    public function rules(): array
    {
        $reglas = [
            'tipo_archivo' => ['required', 'in:' . implode(',', IncidenciaArchivo::TIPOS)],
            'archivo'      => ['required', 'file'],
        ];

        // Decisión 7: el límite y los formatos dependen del tipo elegido.
        if ($this->input('tipo_archivo') === 'FOTO') {
            $reglas['archivo'] = ['required', 'file', 'image|mimes:jpg,jpeg,png,webp', 'max:2048'];
        } elseif ($this->input('tipo_archivo') === 'VIDEO') {
            $reglas['archivo'] = ['required', 'file', 'mimes:mp4,webm,ogg', 'max:20480'];
        } elseif ($this->input('tipo_archivo') === 'DOCUMENTO') {
            $reglas['archivo'] = ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,txt', 'max:10485'];
        }

        return $reglas;
    }

    public function messages(): array
    {
        return [
            'tipo_archivo.required' => 'Indica el tipo de evidencia.',
            'tipo_archivo.in'       => 'Ese tipo de evidencia no es válido.',
            'archivo.required'      => 'Adjunta el archivo de evidencia.',
            'archivo.file'          => 'El archivo no se pudo leer.',
            'archivo.image'         => 'La evidencia debe ser una imagen (jpg, png o webp).',
            'archivo.mimes'         => 'Ese formato no está permitido para el tipo seleccionado.',
            'archivo.max'           => 'El archivo supera el tamaño máximo permitido.',
        ];
    }
}
```

### 3.4 `app/Http/Requests/TipoIncidenciaRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('incidencias.tipos') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:80',
                Rule::unique('tipos_incidencia', 'nombre')->ignore($this->route('tipo')),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo'      => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre del tipo de incidencia.',
            'nombre.max'      => 'El nombre no puede exceder 80 caracteres.',
            'nombre.unique'   => 'Ya existe un tipo de incidencia con ese nombre.',
        ];
    }

    public function prepareForValidation(): void
    {
        if (! $this->has('activo')) {
            $this->merge(['activo' => false]);
        }
    }
}
```

### 3.5 `app/Http/Requests/DocumentoRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\VehiculoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->routeIs('vehiculos.documentos.store')
            ? 'documentos.crear'
            : 'documentos.editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => [
                'required',
                Rule::in(VehiculoDocumento::TIPOS),
            ],
            'numero'           => ['nullable', 'string', 'max:50'],
            'fecha_emision'    => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            // Escaneo del documento: imagen o PDF (opcional).
            'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $emision = $this->input('fecha_emision');
            $vencimiento = $this->input('fecha_vencimiento');

            if ($emision && $vencimiento && $vencimiento < $emision) {
                $validator->errors()->add(
                    'fecha_vencimiento',
                    'El vencimiento no puede ser anterior a la fecha de emisión.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'tipo_documento.required' => 'Selecciona el tipo de documento.',
            'tipo_documento.in'       => 'Ese tipo de documento no es válido.',
            'fecha_emision.date'      => 'La fecha de emisión no es válida.',
            'fecha_vencimiento.date'  => 'La fecha de vencimiento no es válida.',
            'archivo.mimes'           => 'El archivo debe ser un PDF o una imagen (jpg, png, webp).',
            'archivo.max'             => 'El archivo supera los 5 MB.',
        ];
    }
}
```

---

## FASE 4 - Controladores

### 4.1 `app/Http/Controllers/IncidenciaController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchivoRequest;
use App\Http\Requests\IncidenciaEstadoRequest;
use App\Http\Requests\IncidenciaRequest;
use App\Models\Incidencia;
use App\Models\TipoIncidencia;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IncidenciaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('incidencias.ver');

        $filtros = [
            'q'      => $request->string('q')->toString(),
            'estado' => in_array($request->input('estado'), Incidencia::ESTADOS, true)
                ? $request->input('estado')
                : null,
            'tipo'   => $request->integer('tipo') ?: null,
            'desde'  => $request->input('desde'),
            'hasta'  => $request->input('hasta'),
        ];

        $incidencias = Incidencia::filtrar(
                $filtros['q'], $filtros['estado'], $filtros['tipo'],
                $filtros['desde'], $filtros['hasta']
            )
            ->with(['vehiculo', 'cliente', 'tipo', 'usuario'])
            ->withCount('archivos')
            ->paginate(15)
            ->withQueryString();

        $tipos = TipoIncidencia::activos()->orderBy('nombre')->get();

        return view('incidencias.index', compact('incidencias', 'filtros', 'tipos'));
    }

    public function create()
    {
        $this->authorize('incidencias.crear');

        // El combo trae cliente y contrato activo para autocompletar la ficha.
        $vehiculos = Vehiculo::where('activo', true)
            ->with(['cliente', 'contratoActivo'])
            ->orderBy('placas')
            ->get(['id', 'placas', 'marca', 'modelo', 'cliente_id']);

        $tipos = TipoIncidencia::activos()->orderBy('nombre')->get();

        return view('incidencias.create', compact('vehiculos', 'tipos'));
    }

    public function store(IncidenciaRequest $request)
    {
        $this->authorize('incidencias.crear');

        $vehiculo = Vehiculo::with('contratoActivo')
            ->findOrFail($request->validated('vehiculo_id'));

        // Decisión 3: cliente y contrato se derivan del vehículo.
        $incidencia = Incidencia::create([
            'vehiculo_id'         => $vehiculo->id,
            'cliente_id'          => $vehiculo->cliente_id,
            'contrato_pension_id' => $vehiculo->contratoActivo?->id,
            'tipo_incidencia_id'  => $request->validated('tipo_incidencia_id'),
            'fecha_hora'          => $request->validated('fecha_hora'),
            'descripcion'         => $request->validated('descripcion'),
            'estado'              => 'ABIERTA',
            'usuario_id'          => auth()->id(),
        ]);

        return redirect()->route('incidencias.show', $incidencia)
            ->with('success', 'Incidencia registrada. Ya puedes adjuntar la evidencia en su ficha.');
    }

    public function show(Incidencia $incidencia)
    {
        $this->authorize('incidencias.ver');

        $incidencia->load([
            'vehiculo.cliente', 'vehiculo.entradaAbierta', 'cliente',
            'tipo', 'usuario', 'contratoPension', 'archivos',
        ]);

        $transiciones = $incidencia->transicionesPermitidas();

        return view('incidencias.show', compact('incidencia', 'transiciones'));
    }

    public function edit(Incidencia $incidencia)
    {
        $this->authorize('incidencias.editar');

        $tipos = TipoIncidencia::activos()->orderBy('nombre')->get();

        return view('incidencias.edit', compact('incidencia', 'tipos'));
    }

    public function update(IncidenciaRequest $request, Incidencia $incidencia)
    {
        $this->authorize('incidencias.editar');

        // El vehículo no se cambia: la incidencia es un hecho histórico.
        $incidencia->update([
            'tipo_incidencia_id' => $request->validated('tipo_incidencia_id'),
            'fecha_hora'         => $request->validated('fecha_hora'),
            'descripcion'        => $request->validated('descripcion'),
        ]);

        return redirect()->route('incidencias.show', $incidencia)
            ->with('success', 'Incidencia actualizada.');
    }

    /** US-0603: cambio de estado con transición validada en servidor. */
    public function cambiarEstado(IncidenciaEstadoRequest $request, Incidencia $incidencia)
    {
        $this->authorize('incidencias.cambiar_estado');

        $estado = $request->validated('estado');
        $incidencia->update(['estado' => $estado]);

        return back()->with(
            'success',
            'Estado de la incidencia: ' . Incidencia::ESTADO_LABELS[$estado] . '.'
        );
    }

    /** US-0602: sube la evidencia a storage/app/public/incidencias/{id}/. */
    public function subirArchivo(ArchivoRequest $request, Incidencia $incidencia)
    {
        $this->authorize('incidencias.editar');

        $archivo = $request->file('archivo');
        $ruta = $archivo->store('incidencias/' . $incidencia->id, 'public');

        $incidencia->archivos()->create([
            'tipo_archivo'  => $request->validated('tipo_archivo'),
            'url'           => $ruta,
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'mime_type'     => $archivo->getMimeType(),
        ]);

        return back()->with('success', 'Evidencia agregada a la incidencia.');
    }

    public function eliminarArchivo(Incidencia $incidencia, $archivo)
    {
        $this->authorize('incidencias.editar');

        $registro = $incidencia->archivos()->findOrFail($archivo);

        Storage::disk('public')->delete($registro->url);
        $registro->delete();

        return back()->with('success', 'Evidencia eliminada.');
    }
}
```

### 4.2 `app/Http/Controllers/TipoIncidenciaController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoIncidenciaRequest;
use App\Models\TipoIncidencia;
use Illuminate\Http\Request;

class TipoIncidenciaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('incidencias.tipos');

        $tipos = TipoIncidencia::query()
            ->buscar($request->string('q')->toString())
            ->when($request->has('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->withCount('incidencias')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('tipos_incidencia.index', compact('tipos'));
    }

    public function create()
    {
        $this->authorize('incidencias.tipos');

        return view('tipos_incidencia.create');
    }

    public function store(TipoIncidenciaRequest $request)
    {
        $this->authorize('incidencias.tipos');

        TipoIncidencia::create($request->validated());

        return redirect()->route('tipos-incidencia.index')
            ->with('success', 'Tipo de incidencia registrado.');
    }

    public function edit(TipoIncidencia $tipo)
    {
        $this->authorize('incidencias.tipos');

        return view('tipos_incidencia.edit', compact('tipo'));
    }

    public function update(TipoIncidenciaRequest $request, TipoIncidencia $tipo)
    {
        $this->authorize('incidencias.tipos');

        $tipo->update($request->validated());

        return redirect()->route('tipos-incidencia.index')
            ->with('success', 'Tipo de incidencia actualizado.');
    }

    public function destroy(TipoIncidencia $tipo)
    {
        $this->authorize('incidencias.tipos');

        // restrictOnDelete en incidencias: en uso = desactivar (decisión 10).
        if ($tipo->incidencias()->exists()) {
            $tipo->update(['activo' => false]);
            $mensaje = 'El tipo está en uso: se desactivó en lugar de eliminarlo.';
        } else {
            $tipo->delete();
            $mensaje = 'Tipo de incidencia eliminado.';
        }

        return redirect()->route('tipos-incidencia.index')->with('success', $mensaje);
    }
}
```

### 4.3 `app/Http/Controllers/DocumentoController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentoRequest;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    /** Índice global con alertas de vencimiento (US-0605). */
    public function index(Request $request)
    {
        $this->authorize('documentos.ver');

        $filtros = [
            'q'     => $request->string('q')->toString(),
            'tipo'  => in_array($request->input('tipo'), VehiculoDocumento::TIPOS, true)
                ? $request->input('tipo')
                : null,
            'estado' => in_array($request->input('estado'), ['VIGENTE', 'VENCIDO', 'POR_VENCER'], true)
                ? $request->input('estado')
                : null,
        ];

        $documentos = VehiculoDocumento::filtrar($filtros['q'], $filtros['tipo'], $filtros['estado'])
            ->with('vehiculo')
            ->paginate(15)
            ->withQueryString();

        $vencidos = VehiculoDocumento::alertas()
            ->where('fecha_vencimiento', '<', today())
            ->count();

        $porVencer = VehiculoDocumento::alertas()
            ->where('fecha_vencimiento', '>=', today())
            ->count();

        return view('documentos.index', compact('documentos', 'filtros', 'vencidos', 'porVencer'));
    }

    /** Expediente de un vehículo (alta, edición y baja). */
    public function expediente(Vehiculo $vehiculo)
    {
        $this->authorize('documentos.ver');

        $documentos = $vehiculo->documentos()->orderBy('fecha_vencimiento')->get();

        return view('documentos.expediente', compact('vehiculo', 'documentos'));
    }

    public function store(DocumentoRequest $request, Vehiculo $vehiculo)
    {
        $this->authorize('documentos.crear');

        $datos = $request->validated();

        if ($request->hasFile('archivo')) {
            $datos['archivo_url'] = $request->file('archivo')
                ->store('documentos/' . $vehiculo->id, 'public');
        }

        unset($datos['archivo']);
        $vehiculo->documentos()->create($datos);

        return back()->with('success', 'Documento agregado al expediente.');
    }

    public function update(DocumentoRequest $request, Vehiculo $vehiculo, VehiculoDocumento $documento)
    {
        $this->authorize('documentos.editar');
        abort_unless($documento->vehiculo_id === $vehiculo->id, 404);

        $datos = $request->validated();

        if ($request->hasFile('archivo')) {
            Storage::disk('public')->delete($documento->archivo_url);
            $datos['archivo_url'] = $request->file('archivo')
                ->store('documentos/' . $vehiculo->id, 'public');
        }

        unset($datos['archivo']);
        $documento->update($datos);

        return redirect()
            ->route('vehiculos.documentos', $vehiculo)
            ->with('success', 'Documento actualizado.');
    }

    public function destroy(Vehiculo $vehiculo, VehiculoDocumento $documento)
    {
        $this->authorize('documentos.eliminar');
        abort_unless($documento->vehiculo_id === $vehiculo->id, 404);

        Storage::disk('public')->delete($documento->archivo_url);
        $documento->delete();

        return back()->with('success', 'Documento eliminado del expediente.');
    }
}
```

---

## FASE 5 - Vistas (`resources/views/`)

### 5.1 `resources/views/incidencias/_form.blade.php`

Compartido por `create` y `edit` (el combo de vehículo solo existe al crear).

```blade
@php($esAlta = ! isset($incidencia))

<div class="mb-3">
    @if ($esAlta)
        <label for="vehiculo_id" class="form-label">Vehículo <span class="text-danger">*</span></label>
        <select name="vehiculo_id" id="vehiculo_id"
                class="form-select @error('vehiculo_id') is-invalid @enderror" required>
            <option value="">Selecciona un vehículo…</option>
            @foreach ($vehiculos as $v)
                <option value="{{ $v->id }}"
                        @selected((string) old('vehiculo_id', request('vehiculo_id')) === (string) $v->id)
                        data-cliente="{{ $v->cliente?->nombre_completo ?? '—' }}"
                        data-contrato="{{ $v->contratoActivo?->numero_contrato ?? 'Sin contrato activo' }}">
                    {{ $v->placas }} — {{ trim(($v->marca ?? '') . ' ' . ($v->modelo ?? '')) ?: 'Sin modelo' }}
                </option>
            @endforeach
        </select>
        @error('vehiculo_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

        <div class="row g-2 mt-1">
            <div class="col-md-6">
                <span class="form-label small text-muted mb-0 d-block">Cliente (automático)</span>
                <span id="cliente_txt" class="form-control form-control-sm bg-light">—</span>
            </div>
            <div class="col-md-6">
                <span class="form-label small text-muted mb-0 d-block">Contrato activo (automático)</span>
                <span id="contrato_txt" class="form-control form-control-sm bg-light">—</span>
            </div>
        </div>
    @else
        <span class="form-label small text-muted mb-0 d-block">Vehículo (no se puede cambiar)</span>
        <input type="text" class="form-control" readonly
               value="{{ $incidencia->vehiculo?->placas }} — {{ trim(($incidencia->vehiculo?->marca ?? '') . ' ' . ($incidencia->vehiculo?->modelo ?? '')) }}">
    @endif
</div>

<div class="mb-3">
    <label for="tipo_incidencia_id" class="form-label">Tipo de incidencia <span class="text-danger">*</span></label>
    <select name="tipo_incidencia_id" id="tipo_incidencia_id"
            class="form-select @error('tipo_incidencia_id') is-invalid @enderror" required>
        <option value="">Selecciona un tipo…</option>
        @foreach ($tipos as $t)
            <option value="{{ $t->id }}"
                    @selected((string) old('tipo_incidencia_id', $incidencia->tipo_incidencia_id ?? '') === (string) $t->id)>
                {{ $t->nombre }}
            </option>
        @endforeach
    </select>
    @error('tipo_incidencia_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="fecha_hora" class="form-label">Fecha y hora <span class="text-danger">*</span></label>
    <input type="datetime-local" name="fecha_hora" id="fecha_hora"
           class="form-control @error('fecha_hora') is-invalid @enderror" required
           value="{{ old('fecha_hora', isset($incidencia)
               ? $incidencia->fecha_hora->format('Y-m-d\TH:i')
               : now()->format('Y-m-d\TH:i')) }}">
    @error('fecha_hora')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="descripcion" class="form-label">Descripción</label>
    <textarea name="descripcion" id="descripcion" rows="4"
              class="form-control @error('descripcion') is-invalid @enderror"
              placeholder="Qué ocurrió, dónde, testigos…">{{ old('descripcion', $incidencia->descripcion ?? '') }}</textarea>
    @error('descripcion')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
```

### 5.2 `resources/views/incidencias/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Incidencias')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Incidencias</h4>
    @can('incidencias.crear')
        <a href="{{ route('incidencias.create') }}" class="btn btn-primary">+ Nueva incidencia</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" value="{{ $filtros['q'] }}" class="form-control"
               placeholder="Placa, cliente o descripción">
    </div>
    <div class="col-md-2">
        <select name="estado" class="form-select" aria-label="Estado">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\Incidencia::ESTADOS as $e)
                <option value="{{ $e }}" @selected($filtros['estado'] === $e)>
                    {{ \App\Models\Incidencia::ESTADO_LABELS[$e] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="tipo" class="form-select" aria-label="Tipo">
            <option value="">Todos los tipos</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}" @selected($filtros['tipo'] == $t->id)>{{ $t->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="desde" value="{{ $filtros['desde'] }}" class="form-control" aria-label="Desde">
    </div>
    <div class="col-md-2">
        <input type="date" name="hasta" value="{{ $filtros['hasta'] }}" class="form-control" aria-label="Hasta">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Filtrar</button>
    </div>
    @if (array_filter($filtros))
        <div class="col-auto">
            <a href="{{ route('incidencias.index') }}" class="btn btn-outline-danger">Limpiar</a>
        </div>
    @endif
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Fecha y hora</th><th>Tipo</th><th>Vehículo</th><th>Cliente</th>
                <th class="text-center">Estado</th><th class="text-center">Evidencia</th>
                <th>Registró</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($incidencias as $inc)
                <tr>
                    <td class="text-nowrap">{{ $inc->fecha_hora->format('d/m/Y H:i') }}</td>
                    <td>{{ $inc->tipo?->nombre ?? '—' }}</td>
                    <td class="fw-bold">{{ $inc->vehiculo?->placas ?? '—' }}</td>
                    <td>{{ $inc->cliente?->nombre_completo ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge {{ $inc->estado_clase }}">{{ $inc->estado_label }}</span>
                    </td>
                    <td class="text-center">
                        @if ($inc->archivos_count)
                            <span class="badge bg-secondary">{{ $inc->archivos_count }}</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td><small>{{ $inc->usuario?->name ?? '—' }}</small></td>
                    <td class="text-end">
                        <a href="{{ route('incidencias.show', $inc) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        {{ array_filter($filtros)
                            ? 'No hay incidencias que coincidan con el filtro.'
                            : 'No hay incidencias registradas.' }}
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $incidencias->links() }}</div>
@endsection
```

### 5.3 `resources/views/incidencias/create.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Nueva incidencia')

@section('content')
<h4 class="mb-3">Nueva incidencia</h4>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('incidencias.store') }}" method="POST">
            @csrf
            @include('incidencias._form')

            <div class="d-flex gap-2">
                <button class="btn btn-primary">Registrar incidencia</button>
                <a href="{{ route('incidencias.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Autocompleta cliente y contrato a partir del vehículo elegido (US-0601).
    document.addEventListener('DOMContentLoaded', () => {
        const sel = document.getElementById('vehiculo_id');
        if (!sel) return;

        const pintar = () => {
            const op = sel.options[sel.selectedIndex];
            document.getElementById('cliente_txt').textContent = op?.dataset.cliente || '—';
            document.getElementById('contrato_txt').textContent = op?.dataset.contrato || '—';
        };

        sel.addEventListener('change', pintar);
        pintar();
    });
</script>
@endpush
```

### 5.4 `resources/views/incidencias/edit.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Editar incidencia')

@section('content')
<h4 class="mb-3">
    Editar incidencia #{{ $incidencia->id }}
    <span class="badge {{ $incidencia->estado_clase }}">{{ $incidencia->estado_label }}</span>
</h4>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('incidencias.update', $incidencia) }}" method="POST">
            @csrf @method('PUT')
            @include('incidencias._form')

            <div class="d-flex gap-2">
                <button class="btn btn-primary">Guardar cambios</button>
                <a href="{{ route('incidencias.show', $incidencia) }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
```

### 5.5 `resources/views/incidencias/show.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Detalle de la incidencia')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        Incidencia #{{ $incidencia->id }}
        <span class="badge {{ $incidencia->estado_clase }} ms-2">{{ $incidencia->estado_label }}</span>
    </h4>
    <div class="d-flex gap-2">
        @can('incidencias.editar')
            <a href="{{ route('incidencias.edit', $incidencia) }}" class="btn btn-outline-primary">Editar</a>
        @endcan
        <a href="{{ route('incidencias.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light"><strong>Datos de la incidencia</strong></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Tipo</span>
                    <strong>{{ $incidencia->tipo?->nombre ?? '—' }}</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Fecha y hora</span>
                    <span>{{ $incidencia->fecha_hora->format('d/m/Y H:i') }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Vehículo</span>
                    <span>
                        <strong>{{ $incidencia->vehiculo?->placas ?? '—' }}</strong>
                        {{ trim(($incidencia->vehiculo?->marca ?? '') . ' ' . ($incidencia->vehiculo?->modelo ?? '')) }}
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Cliente</span>
                    <span>{{ $incidencia->cliente?->nombre_completo ?? '—' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Contrato</span>
                    <span>{{ $incidencia->contratoPension?->numero_contrato ?? 'Sin contrato activo' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Registró</span>
                    <span>{{ $incidencia->usuario?->name ?? '—' }}</span>
                </li>
                <li class="list-group-item">
                    <span class="text-muted d-block mb-1">Descripción</span>
                    {{ $incidencia->descripcion ?: 'Sin descripción.' }}
                </li>
            </ul>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light"><strong>Seguimiento</strong></div>
            <div class="card-body">
                {{-- US-0603: cambio de estado --}}
                @can('incidencias.cambiar_estado')
                    @if ($transiciones)
                        <form action="{{ route('incidencias.estado', $incidencia) }}" method="POST" class="row g-2">
                            @csrf
                            <div class="col-md-7">
                                <select name="estado" class="form-select" aria-label="Nuevo estado" required>
                                    @foreach ($transiciones as $nuevo)
                                        <option value="{{ $nuevo }}">
                                            {{ \App\Models\Incidencia::ESTADO_LABELS[$nuevo] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-primary">Cambiar estado</button>
                            </div>
                        </form>
                        <small class="text-muted d-block mt-2">
                            Estados permitidos desde «{{ $incidencia->estado_label }}».
                        </small>
                    @else
                        <p class="mb-0 text-muted">Este estado no admite más cambios.</p>
                    @endif
                @else
                    <p class="mb-0 text-muted">
                        Solo un supervisor o administrador puede cambiar el estado.
                    </p>
                @endcan

                <hr>
                <span class="text-muted d-block mb-1">Acuse</span>
                <span class="text-muted">
                    Creada {{ $incidencia->created_at->format('d/m/Y H:i') }}
                    · actualizada {{ $incidencia->updated_at->format('d/m/Y H:i') }}
                </span>
            </div>
        </div>
    </div>

    {{-- US-0602: evidencia --}}
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Evidencia ({{ $incidencia->archivos->count() }})</strong></div>
            <div class="card-body">
                @if ($incidencia->archivos->isEmpty())
                    <p class="text-muted mb-3">Sin evidencia adjunta.</p>
                @else
                    <div class="row g-3 mb-4">
                        @foreach ($incidencia->archivos as $a)
                            <div class="col-6 col-md-3">
                                <div class="card h-100 border-light shadow-sm">
                                    @if ($a->tipo_archivo === 'FOTO')
                                        <img src="{{ $a->ruta }}" alt="{{ $a->nombre_archivo }}"
                                             class="card-img-top" style="height:150px;object-fit:cover;">
                                    @elseif ($a->tipo_archivo === 'VIDEO')
                                        <video src="{{ $a->ruta }}" controls
                                               class="w-100" style="height:150px;object-fit:cover;"></video>
                                    @else
                                        <div class="d-flex align-items-center justify-content-center bg-light"
                                             style="height:150px;">
                                            <a href="{{ $a->ruta }}" target="_blank" rel="noopener"
                                               class="btn btn-sm btn-outline-dark">Abrir documento</a>
                                        </div>
                                    @endif
                                    <div class="card-body p-2 d-flex justify-content-between align-items-start gap-1">
                                        <span class="small text-truncate" title="{{ $a->nombre_archivo }}">
                                            {{ $a->nombre_archivo ?: basename($a->url) }}
                                            <span class="text-muted d-block">
                                                {{ $a->tipo_label }} · {{ $a->created_at->format('d/m/Y H:i') }}
                                            </span>
                                        </span>
                                        @can('incidencias.editar')
                                            <form action="{{ route('incidencias.archivos.destroy', [$incidencia, $a]) }}"
                                                  method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar esta evidencia?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" aria-label="Eliminar">✕</button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @can('incidencias.editar')
                    <form action="{{ route('incidencias.archivos.store', $incidencia) }}"
                          method="POST" enctype="multipart/form-data" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-3">
                            <label for="tipo_archivo" class="form-label">Tipo de evidencia</label>
                            <select name="tipo_archivo" id="tipo_archivo" class="form-select" required>
                                @foreach (\App\Models\IncidenciaArchivo::TIPOS as $t)
                                    <option value="{{ $t }}">
                                        {{ \App\Models\IncidenciaArchivo::TIPO_LABELS[$t] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="archivo" class="form-label">Archivo</label>
                            <input type="file" name="archivo" id="archivo" class="form-control" required>
                            <small class="text-muted">Foto ≤ 2 MB · Video ≤ 20 MB · Documento ≤ 10 MB</small>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100">Subir</button>
                        </div>
                        @error('archivo')
                            <div class="col-12"><span class="text-danger small">{{ $message }}</span></div>
                        @enderror
                    </form>
                @endcan
            </div>
        </div>
    </div>

    {{-- Puente con el Sprint 5: estado del vehículo ahora mismo --}}
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Vehículo</strong>
                @can('documentos.ver')
                    <a href="{{ route('vehiculos.documentos', $incidencia->vehiculo) }}"
                       class="btn btn-sm btn-outline-secondary">Ver expediente de documentos</a>
                @endcan
            </div>
            <div class="card-body d-flex justify-content-between flex-wrap gap-2">
                <span>
                    <strong>{{ $incidencia->vehiculo?->placas ?? '—' }}</strong>
                    {{ trim(($incidencia->vehiculo?->marca ?? '') . ' ' . ($incidencia->vehiculo?->modelo ?? '')) }}
                    · {{ $incidencia->vehiculo?->tipoVehiculo?->nombre ?? '—' }}
                    · propietario {{ $incidencia->cliente?->nombre_completo ?? '—' }}
                </span>
                @if ($incidencia->vehiculo?->entradaAbierta)
                    <span>
                        <span class="badge bg-success">Adentro</span>
                        desde {{ $incidencia->vehiculo->entradaAbierta->fecha_hora->format('d/m/Y H:i') }}
                        <a href="{{ route('accesos.show', $incidencia->vehiculo->entradaAbierta) }}"
                           class="btn btn-sm btn-outline-primary ms-2">Ver acceso</a>
                    </span>
                @else
                    <span class="text-muted"><span class="badge bg-secondary">Fuera del recinto</span></span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
```

### 5.6 `resources/views/tipos_incidencia/_form.blade.php`

```blade
<div class="mb-3">
    <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
    <input type="text" name="nombre" id="nombre" maxlength="80" required
           class="form-control @error('nombre') is-invalid @enderror"
           value="{{ old('nombre', $tipo->nombre ?? '') }}">
    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="descripcion" class="form-label">Descripción</label>
    <textarea name="descripcion" id="descripcion" rows="2" maxlength="255"
              class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $tipo->descripcion ?? '') }}</textarea>
    @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-check mb-3">
    <input type="hidden" name="activo" value="0">
    <input type="checkbox" name="activo" id="activo" value="1" class="form-check-input"
           @checked(old('activo', $tipo->activo ?? true))>
    <label for="activo" class="form-check-label">Activo (disponible para nuevas incidencias)</label>
</div>
```

### 5.7 `resources/views/tipos_incidencia/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Tipos de incidencia')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Tipos de incidencia</h4>
    <a href="{{ route('tipos-incidencia.create') }}" class="btn btn-primary">+ Nuevo tipo</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control"
               placeholder="Buscar por nombre o descripción">
    </div>
    <div class="col-md-3">
        <select name="activo" class="form-select" aria-label="Estado">
            <option value="">Todos</option>
            <option value="1" @selected(request('activo') === '1')>Activos</option>
            <option value="0" @selected(request('activo') === '0')>Inactivos</option>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Filtrar</button></div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Nombre</th><th>Descripción</th><th class="text-center">Estado</th>
                <th class="text-center">Incidencias</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($tipos as $t)
                <tr>
                    <td class="fw-bold">{{ $t->nombre }}</td>
                    <td><small>{{ $t->descripcion ?: '—' }}</small></td>
                    <td class="text-center">
                        <span class="badge {{ $t->activo ? 'bg-success' : 'bg-secondary' }}">
                            {{ $t->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="text-center">{{ $t->incidencias_count }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('tipos-incidencia.edit', $t) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form action="{{ route('tipos-incidencia.destroy', $t) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Eliminar este tipo de incidencia?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No hay tipos de incidencia.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $tipos->links() }}</div>
@endsection
```

### 5.8 `resources/views/tipos_incidencia/create.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Nuevo tipo de incidencia')

@section('content')
<h4 class="mb-3">Nuevo tipo de incidencia</h4>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('tipos-incidencia.store') }}" method="POST">
            @csrf
            @include('tipos_incidencia._form')
            <div class="d-flex gap-2">
                <button class="btn btn-primary">Registrar</button>
                <a href="{{ route('tipos-incidencia.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
```

### 5.9 `resources/views/tipos_incidencia/edit.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Editar tipo de incidencia')

@section('content')
<h4 class="mb-3">Editar tipo de incidencia</h4>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('tipos-incidencia.update', $tipo) }}" method="POST">
            @csrf @method('PUT')
            @include('tipos_incidencia._form')
            <div class="d-flex gap-2">
                <button class="btn btn-primary">Guardar cambios</button>
                <a href="{{ route('tipos-incidencia.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
```

### 5.10 `resources/views/documentos/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Documentos del vehículo')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Documentos del vehículo</h4>
    <small class="text-muted">Alertas a 30 días del vencimiento</small>
</div>

{{-- US-0605: alertas --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card shadow-sm border-danger">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">Vencidos</h6>
                <h2 class="mb-0 {{ $vencidos ? 'text-danger' : '' }}">{{ $vencidos }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-warning">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">Por vencer (30 días)</h6>
                <h2 class="mb-0 {{ $porVencer ? 'text-warning' : '' }}">{{ $porVencer }}</h2>
            </div>
        </div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" value="{{ $filtros['q'] }}" class="form-control"
               placeholder="Buscar por placa o marca">
    </div>
    <div class="col-md-3">
        <select name="tipo" class="form-select" aria-label="Tipo de documento">
            <option value="">Todos los tipos</option>
            @foreach (\App\Models\VehiculoDocumento::TIPOS as $t)
                <option value="{{ $t }}" @selected($filtros['tipo'] === $t)>
                    {{ \App\Models\VehiculoDocumento::TIPO_LABELS[$t] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="estado" class="form-select" aria-label="Vigencia">
            <option value="">Todas las vigencias</option>
            <option value="VENCIDO" @selected($filtros['estado'] === 'VENCIDO')>Vencidos</option>
            <option value="POR_VENCER" @selected($filtros['estado'] === 'POR_VENCER')>Por vencer</option>
            <option value="VIGENTE" @selected($filtros['estado'] === 'VIGENTE')>Vigentes</option>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Filtrar</button></div>
    @if (array_filter($filtros))
        <div class="col-auto">
            <a href="{{ route('documentos.index') }}" class="btn btn-outline-danger">Limpiar</a>
        </div>
    @endif
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Vehículo</th><th>Tipo</th><th>Número</th><th>Emisión</th>
                <th>Vencimiento</th><th class="text-center">Vigencia</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($documentos as $d)
                <tr>
                    <td>
                        <span class="fw-bold">{{ $d->vehiculo?->placas ?? '—' }}</span>
                        <small class="text-muted d-block">
                            {{ trim(($d->vehiculo?->marca ?? '') . ' ' . ($d->vehiculo?->modelo ?? '')) }}
                        </small>
                    </td>
                    <td>{{ $d->tipo_label }}</td>
                    <td><small>{{ $d->numero ?: '—' }}</small></td>
                    <td><small>{{ $d->fecha_emision?->format('d/m/Y') ?? '—' }}</small></td>
                    <td><small>{{ $d->fecha_vencimiento?->format('d/m/Y') ?? 'Sin vencimiento' }}</small></td>
                    <td class="text-center">
                        <span class="badge {{ $d->vigencia_clase }}">
                            {{ $d->vigencia_label }}
                            @if ($d->vigencia_label !== 'Vigente' && $d->diasParaVencer() !== null)
                                · {{ $d->diasParaVencer() }} días
                            @endif
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('vehiculos.documentos', $d->vehiculo) }}"
                           class="btn btn-sm btn-outline-primary">Expediente</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        {{ array_filter($filtros)
                            ? 'No hay documentos que coincidan con el filtro.'
                            : 'No hay documentos registrados.' }}
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $documentos->links() }}</div>
@endsection
```

### 5.11 `resources/views/documentos/expediente.blade.php`

La misma página sirve de alta y de edición: si llega `?editar=ID` (o `old('editar')`)
el formulario se convierte en `PUT` sobre ese documento.

```blade
@extends('layouts.app')
@section('title', 'Expediente del vehículo')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        Expediente de documentos
        <span class="fs-5 text-muted">— {{ $vehiculo->placas }}</span>
    </h4>
    <a href="{{ route('vehiculos.index') }}" class="btn btn-outline-secondary">Volver a vehículos</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Documentos ({{ $documentos->count() }})</strong>
                <small class="text-muted">
                    {{ $vehiculo->cliente?->nombre_completo ?? 'Sin propietario' }}
                    · {{ trim(($vehiculo->marca ?? '') . ' ' . ($vehiculo->modelo ?? '')) }}
                </small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Tipo</th><th>Número</th><th>Emisión</th><th>Vencimiento</th>
                        <th class="text-center">Vigencia</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($documentos as $d)
                        <tr class="{{ $d->vigencia_label === 'Vencido' ? 'table-danger' : '' }}">
                            <td class="fw-bold">{{ $d->tipo_label }}</td>
                            <td><small>{{ $d->numero ?: '—' }}</small></td>
                            <td><small>{{ $d->fecha_emision?->format('d/m/Y') ?? '—' }}</small></td>
                            <td><small>{{ $d->fecha_vencimiento?->format('d/m/Y') ?? 'Sin vencimiento' }}</small></td>
                            <td class="text-center">
                                <span class="badge {{ $d->vigencia_clase }}">{{ $d->vigencia_label }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                @can('documentos.editar')
                                    <a href="{{ route('vehiculos.documentos', ['vehiculo' => $vehiculo, 'editar' => $d->id]) }}"
                                       class="btn btn-sm btn-outline-primary">Editar</a>
                                @endcan
                                @can('documentos.eliminar')
                                    <form action="{{ route('vehiculos.documentos.destroy', [$vehiculo, $d]) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('¿Eliminar este documento del expediente?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Este vehículo no tiene documentos en su expediente.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        {{-- El modo edición se mantiene también tras un 422: el formulario PUT
             lleva un campo oculto `editar` que reaparece en `old()`. --}}
        @php($editando = old('editar') ?: request('editar'))
        @php($docEdit = $editando ? $documentos->firstWhere('id', $editando) : null)
        @php($modoEdicion = $docEdit && auth()->user()->can('documentos.editar'))

        @if ($modoEdicion)
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-white">
                    <strong>Editar: {{ $docEdit->tipo_label }}</strong>
                </div>
                <div class="card-body">
                    <form action="{{ route('vehiculos.documentos.update', [$vehiculo, $docEdit]) }}"
                          method="POST" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        <input type="hidden" name="editar" value="{{ $docEdit->id }}">
                        @include('documentos._form', ['documento' => $docEdit])
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary">Guardar cambios</button>
                            <a href="{{ route('vehiculos.documentos', $vehiculo) }}"
                               class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        @else
            {{-- Modo alta --}}
            @can('documentos.crear')
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><strong>Agregar documento</strong></div>
                    <div class="card-body">
                        <form action="{{ route('vehiculos.documentos.store', $vehiculo) }}"
                              method="POST" enctype="multipart/form-data">
                            @csrf
                            @include('documentos._form', ['documento' => null])
                            <button class="btn btn-primary">Agregar al expediente</button>
                        </form>
                    </div>
                </div>
            @endcan
        @endif
    </div>
</div>
@endsection
```

### 5.12 `resources/views/documentos/_form.blade.php`

```blade
<div class="mb-3">
    <label for="tipo_documento" class="form-label">Tipo de documento <span class="text-danger">*</span></label>
    <select name="tipo_documento" id="tipo_documento" required
            class="form-select @error('tipo_documento') is-invalid @enderror">
        <option value="">Selecciona…</option>
        @foreach (\App\Models\VehiculoDocumento::TIPOS as $t)
            <option value="{{ $t }}"
                    @selected(old('tipo_documento', $documento->tipo_documento ?? '') === $t)>
                {{ \App\Models\VehiculoDocumento::TIPO_LABELS[$t] }}
            </option>
        @endforeach
    </select>
    @error('tipo_documento')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="numero" class="form-label">Número / póliza</label>
    <input type="text" name="numero" id="numero" maxlength="50"
           class="form-control @error('numero') is-invalid @enderror"
           value="{{ old('numero', $documento->numero ?? '') }}">
    @error('numero')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-2 mb-3">
    <div class="col-md-6">
        <label for="fecha_emision" class="form-label">Fecha de emisión</label>
        <input type="date" name="fecha_emision" id="fecha_emision"
               class="form-control @error('fecha_emision') is-invalid @enderror"
               value="{{ old('fecha_emision', isset($documento) && $documento->fecha_emision
                   ? $documento->fecha_emision->format('Y-m-d') : '') }}">
        @error('fecha_emision')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="fecha_vencimiento" class="form-label">Fecha de vencimiento</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento"
               class="form-control @error('fecha_vencimiento') is-invalid @enderror"
               value="{{ old('fecha_vencimiento', isset($documento) && $documento->fecha_vencimiento
                   ? $documento->fecha_vencimiento->format('Y-m-d') : '') }}">
        @error('fecha_vencimiento')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label for="archivo" class="form-label">
        Escaneo del documento {{ isset($documento) ? '(reemplaza el actual)' : '' }}
    </label>
    <input type="file" name="archivo" id="archivo"
           class="form-control @error('archivo') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.webp">
    <small class="text-muted">PDF o imagen, máximo 5 MB. Opcional.</small>
    @error('archivo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

@if (isset($documento) && $documento->archivo_url)
    <p class="small">
        Archivo actual:
        <a href="{{ asset('storage/'.$documento->archivo_url) }}" target="_blank" rel="noopener">ver</a>
    </p>
@endif
```

---

## FASE 6 - Rutas, menú, dashboard y enlaces

### 6.1 `routes/web.php` - 20 rutas nuevas

**Fragmento: añadir** a los `use` del archivo:

```php
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\TipoIncidenciaController;
```

**Fragmento: añadir** al final del grupo `Route::middleware(['auth'])`, después
del bloque del Sprint 5:

```php
    // Sprint 6 — Módulo 10 (incidencias y documentos del vehículo)
    // ¡Las rutas estáticas van ANTES del resource! (lección de Sprint 4/5)
    Route::patch('incidencias/{incidencia}/estado', [IncidenciaController::class, 'cambiarEstado'])
        ->name('incidencias.estado');
    Route::post('incidencias/{incidencia}/archivos', [IncidenciaController::class, 'subirArchivo'])
        ->name('incidencias.archivos.store');
    Route::delete('incidencias/{incidencia}/archivos/{archivo}', [IncidenciaController::class, 'eliminarArchivo'])
        ->name('incidencias.archivos.destroy');
    Route::resource('incidencias', IncidenciaController::class)->except(['destroy']);

    Route::resource('tipos-incidencia', TipoIncidenciaController::class)
        ->parameters(['tipos-incidencia' => 'tipo'])
        ->except(['show']);

    Route::get('documentos', [DocumentoController::class, 'index'])->name('documentos.index');
    Route::get('vehiculos/{vehiculo}/documentos', [DocumentoController::class, 'expediente'])
        ->name('vehiculos.documentos');
    Route::post('vehiculos/{vehiculo}/documentos', [DocumentoController::class, 'store'])
        ->name('vehiculos.documentos.store');
    Route::put('vehiculos/{vehiculo}/documentos/{documento}', [DocumentoController::class, 'update'])
        ->name('vehiculos.documentos.update');
    Route::delete('vehiculos/{vehiculo}/documentos/{documento}', [DocumentoController::class, 'destroy'])
        ->name('vehiculos.documentos.destroy');
```

Conteo: `incidencias` 3 + 6 (`resource` sin `destroy`) = 9 · `tipos-incidencia`
6 · `documentos` 1 · expediente 4 → **20 rutas (94 → 114)**.

```powershell
C:\php\php.exe artisan route:list --json | ConvertFrom-Json | Measure-Object | Select-Object Count
# 114
```

### 6.2 `resources/views/layouts/app.blade.php` - menú

**Fragmento: reemplazar** la línea 100 (el `<span>` deshabilitado «Incidencias»)
por un grupo colapsable, siguiendo el patrón del menú «Espacios»:

```blade
            @canany(['incidencias.ver', 'documentos.ver', 'incidencias.tipos'])
            <li class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center
                          {{ request()->routeIs('incidencias.*', 'tipos-incidencia.*', 'documentos.*', 'vehiculos.documentos*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   data-bs-toggle="collapse" href="#menuIncidencias" role="button"
                   aria-expanded="{{ request()->routeIs('incidencias.*', 'tipos-incidencia.*', 'documentos.*', 'vehiculos.documentos*') ? 'true' : 'false' }}"
                   aria-controls="menuIncidencias">
                    Incidencias
                    <span class="small">&#9662;</span>
                </a>
                <div class="collapse {{ request()->routeIs('incidencias.*', 'tipos-incidencia.*', 'documentos.*', 'vehiculos.documentos*') ? 'show' : '' }}"
                     id="menuIncidencias">
                    <ul class="nav flex-column ps-3">
                        @can('incidencias.ver')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('incidencias.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                               href="{{ route('incidencias.index') }}">Incidencias</a>
                        </li>
                        @endcan
                        @can('documentos.ver')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('documentos.*', 'vehiculos.documentos*') ? 'text-white fw-bold' : 'text-white-50' }}"
                               href="{{ route('documentos.index') }}">Documentos</a>
                        </li>
                        @endcan
                        @can('incidencias.tipos')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('tipos-incidencia.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                               href="{{ route('tipos-incidencia.index') }}">Tipos de incidencia</a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcanany
```

> Si un rol solo tiene `documentos.ver` (CAJERO), el grupo aparece con la única
> entrada «Documentos»: los `@can` de adentro filtran cada hijo.

### 6.3 `app/Http/Controllers/HomeController.php` - alerta de documentos

**Fragmento: añadir** antes del `return view(...)`:

```php
        // US-0605: documentos vencidos o por vencer (30 días).
        $documentosAlerta = Gate::allows('documentos.ver')
            ? VehiculoDocumento::alertas()->with('vehiculo')->get()
            : null;

        return view('home', compact('porVencer', 'adentro', 'documentosAlerta'));
```

**Fragmento: añadir** al `use` del inicio del archivo:

```php
use App\Models\VehiculoDocumento;
```

### 6.4 `resources/views/home.blade.php` - tarjeta de alertas

**Fragmento: añadir** dentro de `<div class="row g-3">`, después de la tarjeta
«Ingresos del mes» y **antes** del bloque `@if ($porVencer->isNotEmpty())`:

```blade
        {{-- US-0605: documentos vencidos o por vencer (30 días) --}}
        @if ($documentosAlerta !== null && $documentosAlerta->isNotEmpty())
            <div class="col-12">
                <div class="card shadow-sm border-danger">
                    <div class="card-header bg-danger-subtle d-flex justify-content-between align-items-center">
                        <strong>Documentos vencidos o por vencer ({{ $documentosAlerta->count() }})</strong>
                        <a href="{{ route('documentos.index') }}" class="btn btn-sm btn-outline-dark">Ver todos</a>
                    </div>
                    <ul class="list-group list-group-flush">
                        @foreach ($documentosAlerta->take(5) as $doc)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <strong>{{ $doc->vehiculo?->placas }}</strong> — {{ $doc->tipo_label }}
                                    <small class="text-muted">
                                        vence {{ $doc->fecha_vencimiento?->format('d/m/Y') }}
                                    </small>
                                </span>
                                <span class="badge {{ $doc->vigencia_clase }}">
                                    {{ $doc->vigencia_label }}
                                    @if ($doc->diasParaVencer() !== null)
                                        · {{ $doc->diasParaVencer() }} días
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($documentosAlerta->count() > 5)
                        <div class="card-footer small text-muted">
                            … y {{ $documentosAlerta->count() - 5 }} documento(s) más.
                        </div>
                    @endif
                </div>
            </div>
        @endif
```

### 6.5 Enlaces desde otras pantallas

**Fragmento: añadir** en `resources/views/vehiculos/index.blade.php`, dentro de
la celda de acciones (antes del botón «Editar»):

```blade
                        @can('documentos.ver')
                            <a href="{{ route('vehiculos.documentos', $v) }}"
                               class="btn btn-sm btn-outline-secondary">Documentos</a>
                        @endcan
```

**Fragmento: añadir** en `resources/views/accesos/show.blade.php`, justo antes
de `@endsection` (cierra el puente del Apéndice C de la guía 5):

```blade
    {{-- Sprint 6: incidencias del vehículo (US-0601) --}}
    @can('incidencias.ver')
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Incidencias del vehículo</strong>
                @can('incidencias.crear')
                    <a class="btn btn-sm btn-outline-primary"
                       href="{{ route('incidencias.create', ['vehiculo_id' => $acceso->vehiculo_id]) }}">
                        + Nueva incidencia
                    </a>
                @endcan
            </div>
            <div class="list-group list-group-flush">
                @forelse ($acceso->vehiculo->incidencias()->latest('fecha_hora')->take(5)->get() as $inc)
                    <a href="{{ route('incidencias.show', $inc) }}"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>
                            <strong>{{ $inc->tipo?->nombre ?? 'Sin tipo' }}</strong>
                            — {{ $inc->fecha_hora->format('d/m/Y H:i') }}
                            <small class="text-muted d-block">{{ \Illuminate\Support\Str::limit($inc->descripcion, 90) }}</small>
                        </span>
                        <span class="badge {{ $inc->estado_clase }}">{{ $inc->estado_label }}</span>
                    </a>
                @empty
                    <div class="list-group-item text-muted">Este vehículo no tiene incidencias registradas.</div>
                @endforelse
            </div>
        </div>
    @endcan
```

### 6.6 Compilar y sintaxis

No hay cambios en `resources/js/app.js` ni dependencias npm nuevas; de todos
modos se recompila para confirmar:

```powershell
npm run build
Get-ChildItem app\Http\Controllers\IncidenciaController.php,
              app\Http\Controllers\TipoIncidenciaController.php,
              app\Http\Controllers\DocumentoController.php,
              app\Http\Requests\IncidenciaRequest.php,
              app\Http\Requests\IncidenciaEstadoRequest.php,
              app\Http\Requests\ArchivoRequest.php,
              app\Http\Requests\TipoIncidenciaRequest.php,
              app\Http\Requests\DocumentoRequest.php,
              app\Models\TipoIncidencia.php,
              app\Models\Incidencia.php,
              app\Models\IncidenciaArchivo.php,
              app\Models\VehiculoDocumento.php,
              app\Models\Vehiculo.php,
              app\Http\Controllers\HomeController.php,
              database\seeders\TipoIncidenciaSeeder.php,
              database\seeders\RolPermisoSeeder.php,
              database\seeders\DatabaseSeeder.php |
    ForEach-Object { C:\php\php.exe -l $_ }
# 17/17 sintaxis OK
```

---

## FASE 7 - Verificación

Servidor: `C:\php\php.exe artisan serve --port=8002` → `http://127.0.0.1:8002`.

### 7.0 Datos mínimos (la BD casi sin datos operativos)

Con `admin@pension.mx` / `password`:

> Los pasos 1 y 2 ya se ejecutaron el 10/10/2026 (cliente «Juan Perez Lopez»
> y vehículo `ABC1234`); solo falta el vehículo 3.

1. **Clientes → + Nuevo cliente:** nombre «Juan Perez Lopez» (teléfono
   `5551234567`, correo `juan@demo.test`) → guardar.
2. **Vehículos → + Nuevo vehículo:** placa `ABC1234`, marca Nissan, modelo
   Versa, tipo **Automovil**, propietario «Juan Perez Lopez», activo → guardar.
3. **Vehículos → + Nuevo vehículo:** placa `XYZ9999`, marca Mazda, modelo 3,
   tipo **Automovil**, mismo propietario → guardar.
4. Opcional (para ver el contrato precargado): crear un contrato de pensión
   ACTIVA para `ABC1234` (Pensiones → Nuevo contrato).

### 7.1 Checklist por historia de usuario

**US-0601 — registrar incidencia** (usuario `seguridad@pension.mx`):

- [ ] El menú lateral muestra el grupo **Incidencias** con la entrada «Incidencias».
- [ ] `GET /accesos` no toca nada; la incidencia nace desde **Incidencias → + Nueva incidencia** (`GET /incidencias/create` → 200).
- [ ] Al elegir el vehículo `ABC1234`, los campos **Cliente** y **Contrato activo** se autocompletan solos (JS).
- [ ] Guardar con fecha/hora **futura** (+2 días) → 422 «La fecha y hora de la incidencia no puede ser futura.»
- [ ] Guardar **sin tipo** → 422 «Selecciona el tipo de incidencia.»
- [ ] Guardar con tipo «Daño», fecha actual y descripción «Golpe en el costado derecho del estacionamiento» → redirige a `/incidencias/1` con «Incidencia registrada…» y badge **Abierta** (rojo).
- [ ] En BD: `incidencias` fila con `vehiculo_id` = vehículo, `cliente_id` = Juan Perez Lopez (derivado), `usuario_id` = seguridad, `estado = ABIERTA`, `contrato_pension_id` = contrato ACTIVA o `NULL`.
- [ ] `cajero@pension.mx` en `GET /incidencias/create` → **403** y el menú solo le muestra «Documentos».
- [ ] Desde la ficha de un acceso (`/accesos/{id}`) el bloque **«Incidencias del vehículo»** lista la incidencia con su badge.

**US-0602 — evidencia** (usuario `seguridad@pension.mx`, en la ficha de la incidencia):

- [ ] Subir con tipo **Foto** un `.txt` → 422 «La evidencia debe ser una imagen (jpg, png o webp).»
- [ ] Subir una foto JPG (~300 KB) → se ve la miniatura en la galería.
- [ ] En BD: fila en `incidencia_archivos` con `tipo_archivo = FOTO`, `url = incidencias/1/<hash>.jpg`, `nombre_archivo` original y `mime_type = image/jpeg`.
- [ ] El archivo **existe en disco**: `storage/app/public/incidencias/1/…` y carga en `http://127.0.0.1:8002/storage/incidencias/1/…`.
- [ ] Subir tipo **Video** (mp4) y tipo **Documento** (pdf) → cada uno aparece con su render (video / enlace «Abrir documento»).
- [ ] Video > 20 MB → 422 «El archivo supera el tamaño máximo permitido.»
- [ ] Botón ✕ de una evidencia → «¿Eliminar esta evidencia?» → fila y archivo borrados.
- [ ] `supervisor@pension.mx` también puede subir/borrar (tiene `incidencias.editar`); `recepcion@pension.mx` recibe **403** al subir.

**US-0603 — estados** (usuario `supervisor@pension.mx`):

- [ ] En la ficha, el bloque **Seguimiento** ofrece «En proceso» y «Cerrada» → elegir «En proceso» → «Cambiar estado» → badge **En proceso** (ámbar).
- [ ] Desde «En proceso» el select ofrece «Abierta» y «Cerrada» → cerrar → badge **Cerrada** (verde).
- [ ] Desde «Cerrada» el select solo ofrece **«Abierta»** (reabrir) → al reabrir vuelve el badge rojo.
- [ ] Enviar un estado no permitido (p. ej. `POST incidencias/{id}/estado` con `estado=EN PROCESO` estando **Cerrada**, vía fetch con `Accept: application/json`) → 422 «Ese estado no es válido o no está permitido desde el estado actual.»
- [ ] `seguridad@pension.mx` → **403** en `PATCH /incidencias/1/estado` (no tiene `cambiar_estado`); igual RECEPCION y CAJERO.
- [ ] `GET /incidencias/1` para `cajero@pension.mx` → **403**.

**US-0604 — tipos de incidencia** (usuario `admin@pension.mx`):

- [ ] **Incidencias → Tipos de incidencia** muestra los **7 tipos sembrados** (Daño, Accidente, Intento de acceso, Objeto perdido, Vehículo abandonado, Problema de pago, Otro) con su conteo de incidencias.
- [ ] + Nuevo tipo «Vandalismo» → queda **Activo** con conteo 0.
- [ ] Guardar dos tipos con el mismo nombre → 422 «Ya existe un tipo de incidencia con ese nombre.»
- [ ] Eliminar «Vandalismo» (sin incidencias) → se borra: «Tipo de incidencia eliminado.»
- [ ] Eliminar «Daño» (tiene incidencias) → **no se borra**: se desactiva y aparece «El tipo está en uso: se desactivó en lugar de eliminarlo.»
- [ ] Desactivar un tipo y abrir **Nueva incidencia** → ese tipo ya no está en el combo (solo activos).
- [ ] `supervisor@pension.mx` y `seguridad@pension.mx` en `GET /tipos-incidencia` → **403**.

**US-0605 — expediente y alertas** (usuario `admin@pension.mx`):

- [ ] **Vehículos** muestra el botón **«Documentos»** en cada fila → abre `/vehiculos/1/documentos`.
- [ ] Agregar **Tarjeta de circulación**, número `TC-12345`, emisión `2025-01-01`, vencimiento **ayer** + PDF → la fila queda resaltada con badge **Vencido** (rojo) y en BD `estado = VENCIDO`.
- [ ] Agregar **Seguro** con vencimiento a **10 días** → badge **Por vencer** (ámbar) y `estado = VIGENTE`.
- [ ] Agregar **Otro** con vencimiento dentro de **1 año** → badge **Vigente** (verde).
- [ ] Quitar la fecha de vencimiento de un documento → badge «Vigente» y `estado = VIGENTE` (el servidor decide, no el usuario).
- [ ] `GET /documentos` muestra las tarjetas **Vencidos** y **Por vencer** con los conteos, filtros por placa/tipo/vigencia y botón «Expediente» por fila.
- [ ] El dashboard muestra la tarjeta **«Documentos vencidos o por vencer (n)»** con enlace «Ver todos».
- [ ] Editar con `?editar=ID` cambia el formulario a modo edición (`PUT`) y «Cancelar» regresa al alta.
- [ ] Eliminar un documento → se borra la fila **y** el archivo de `storage/app/public/documentos/…`.
- [ ] `cajero@pension.mx` puede **ver** `GET /documentos` (200) pero `POST/PUT/DELETE` → **403**; `recepcion@pension.mx` sí puede crear (200 con payload completo, 422 con payload vacío).

### 7.2 Matriz de permisos (las 20 rutas)

Orden de columnas: ADMIN · SEGURIDAD · RECEPCION · CAJERO · SUPERVISOR · INVITADO.
`login` = sin sesión → `302 /login` (HTML) o `401` (JSON).

| # | Ruta | Permiso | ADMIN | SEGURIDAD | RECEPCION | CAJERO | SUPERVISOR | INVITADO |
|---|---|---|---|---|---|---|---|---|
| 1 | `GET /incidencias` | incidencias.ver | 200 | 200 | 200 | 403 | 200 | login |
| 2 | `GET /incidencias/create` | incidencias.crear | 200 | 200 | 403 | 403 | 403 | login |
| 3 | `POST /incidencias` | incidencias.crear | 422 | 422 | 403 | 403 | 403 | login |
| 4 | `GET /incidencias/{id}` | incidencias.ver | 200 | 200 | 200 | 403 | 200 | login |
| 5 | `GET /incidencias/{id}/edit` | incidencias.editar | 200 | 200 | 403 | 403 | 200 | login |
| 6 | `PUT /incidencias/{id}` | incidencias.editar | 422 | 422 | 403 | 403 | 422 | login |
| 7 | `PATCH /incidencias/{id}/estado` | incidencias.cambiar_estado | 422 | 403 | 403 | 403 | 422 | login |
| 8 | `POST /incidencias/{id}/archivos` | incidencias.editar | 422 | 422 | 403 | 403 | 422 | login |
| 9 | `DELETE /incidencias/{id}/archivos/{a}` | incidencias.editar | 302 | 302 | 403 | 403 | 302 | login |
| 10 | `GET /tipos-incidencia` | incidencias.tipos | 200 | 403 | 403 | 403 | 403 | login |
| 11 | `GET /tipos-incidencia/create` | incidencias.tipos | 200 | 403 | 403 | 403 | 403 | login |
| 12 | `POST /tipos-incidencia` | incidencias.tipos | 422 | 403 | 403 | 403 | 403 | login |
| 13 | `GET /tipos-incidencia/{t}/edit` | incidencias.tipos | 200 | 403 | 403 | 403 | 403 | login |
| 14 | `PUT /tipos-incidencia/{t}` | incidencias.tipos | 422 | 403 | 403 | 403 | 403 | login |
| 15 | `DELETE /tipos-incidencia/{t}` | incidencias.tipos | 302 | 403 | 403 | 403 | 403 | login |
| 16 | `GET /documentos` | documentos.ver | 200 | 200 | 200 | 200 | 200 | login |
| 17 | `GET /vehiculos/{v}/documentos` | documentos.ver | 200 | 200 | 200 | 200 | 200 | login |
| 18 | `POST /vehiculos/{v}/documentos` | documentos.crear | 422 | 403 | 422 | 403 | 403 | login |
| 19 | `PUT /vehiculos/{v}/documentos/{d}` | documentos.editar | 422 | 403 | 403 | 403 | 403 | login |
| 20 | `DELETE /vehiculos/{v}/documentos/{d}` | documentos.eliminar | 302 | 403 | 403 | 403 | 403 | login |

Notas de lectura:

- **`422`** = el permiso SÍ está y la validación corrió con payload vacío
  (decisión 5: el `FormRequest` evalúa el permiso **antes** que las reglas, así
  que 403 y 422 nunca se confunden).
- **`302`** = redirección autorizada (`back()` con flash o `redirect()`).
- Con payload válido, las filas 3, 6, 12, 14, 18 y 19 devuelven **200/302**.
- Las filas 9, 15 y 20 (`DELETE`) se prueban con **ids reales** creados en
  7.1; con un id inexistente la respuesta sería **404** (no 403/302).
- Se prueba con `fetch` y cabecera `Accept: application/json` para que el
  `INVITADO` rinda **401** (sin eso, Chrome reporta status 0 por el redirect).

### 7.3 Comprobaciones en base de datos

```php
// Script temporal (los one-liners de tinker fallan con ?-> y comillas)
DB::table('permissions')->count();          // 49
DB::table('tipos_incidencia')->count();     // 7  (+1 si se creó "Vandalismo")
DB::table('incidencias')->count();          // >= 1
DB::table('incidencia_archivos')->count();  // >= 1
DB::table('vehiculo_documentos')->count();  // >= 3
DB::table('users')->count();                // 5 (sin cambios)
// Reparto por rol:
// ADMINISTRADOR 9 · SEGURIDAD 4 · RECEPCION 3 · SUPERVISOR 4 · CAJERO 1 (nuevos)
```

Además: `php artisan migrate` sigue diciendo «Nothing to migrate» (27
migraciones, ninguna nueva).

### 7.4 Errores nuevos en el log

```powershell
Select-String -Path storage\logs\laravel.log -Pattern 'local.ERROR' |
    Where-Object { $_.Line -match '2026-10-(09|10)' }
```

Esperado: **ningún** error nuevo de la aplicación. Si apareciera un `SQLSTATE`
por `storage/` → revisar Apéndice B-B1 (`php artisan storage:link --force`).

## Apéndice A - Fuentes

| Fuente | Qué se tomó |
|---|---|
| `PLAN-SCRUM.md` §7 (Sprint 6) | US-0601…US-0605 (29 pts), tablas `incidencias`, `tipos_incidencia`, `incidencia_archivos`, `vehiculo_documentos`. |
| `MIGRACIONES.md` §5.5, §5.16, §5.17, §5.19 | Esquema exacto de las 4 tablas: `estado` VARCHAR con valores comentados, `cliente_id NOT NULL`, `fecha_vencimiento` con índice para alertas, `url`/`archivo_url` de 500 caracteres. |
| `MIGRACIONES.md` §5.5 (comentario) | Los 7 tipos del catálogo: Daño, Accidente, Intento de acceso, Objeto perdido, Vehículo abandonado, Problema de pago, Otro. |
| `GUIA-SPRINT5-ENTRADAS-SALIDAS-ACCESOS.md` Apéndice C | Puente: `Vehiculo.entradaAbierta()`, `foto_entrada`/`foto_salida` y «historial por vehículo»; `accesos.ver` sigue mando sobre el expediente de accesos. |
| `GUIA-LOGIN-MODULO1.md` §Vehículos | Patrón de subida real: `VehiculoController::guardarFoto()` con `$request->file('foto')->store(..., 'public')` y `asset('storage/…')`; su apéndice documenta `php artisan storage:link`. |
| `app/Models/ContratoPension.php` | `TRANSICIONES`, `ESTADO_LABELS/CLASSES`, `scopePorVencer()`, `diasParaVencer()`, `DIAS_AVISO` → mismos patrones para `Incidencia` y `VehiculoDocumento`. |
| `app/Http/Controllers/TipoVehiculoController.php` | CRUD de catálogo con «en uso → desactivar» y `withCount` en el índice. |
| `resources/views/contratos/show.blade.php` | Bloque «Cambiar estado» con select de transiciones permitidas. |
| `resources/views/layouts/app.blade.php` | Menú colapsable (grupo Espacios) y placeholders «Accesos»/«Incidencias». |
| Código de referencia (estilo) | `PagoController`, `TipoVehiculoRequest`, `vehiculos/index.blade.php`, `partials/alerts.blade.php` (flash con SweetAlert2). |

## Apéndice B - Riesgos y desviaciones previstas

| # | Riesgo | Impacto | Mitigation |
|---|---|---|---|
| B1 | Falta el symlink `public/storage` (o se rompe). | Las evidencias suben pero dan 404 al abrir. | El symlink ya existe en este entorno; si fallara: `php artisan storage:link --force`. Verificado en FASE 7 US-0602. |
| B2 | Carpeta `storage/app/public/incidencias/` sin permisos de escritura. | 500 al subir. | Mismo disco que ya usa `guardarFoto()` desde la Guía 1; si fallara, revisar permisos de Windows sobre `storage/`. |
| B3 | `incidencias.tipo_incidencia_id` es `restrictOnDelete`. | Un `DELETE` en un tipo usado revienta con error 500 del motor. | `TipoIncidenciaController::destroy()` comprueba `incidencias()->exists()` y **desactiva** en lugar de borrar (decisión 10). |
| B4 | `Route::resource('vehiculos')` no tiene `show`. | No había dónde colgar el expediente por vehículo. | Ruta propia `vehiculos/{vehiculo}/documentos` (decisión 11), más el índice global `/documentos` para las alertas. |
| B5 | `incidencias.cliente_id` es `NOT NULL`. | Un vehículo sin cliente rompería el alta. | Se deriva del vehículo al guardar (decisión 3); los vehículos creados en la Guía 1 siempre tienen cliente. |
| B6 | `incidencias.usuario_id` es `restrictOnDelete`. | Borrar un usuario con incidencias fallará. | Dejar para Sprint 7: ahí se decide si se desactivan usuarios (ya lo hacen con `activo`) en vez de borrarlos. |
| B7 | `vehiculo_documentos.estado` es una columna escrita y a la vez derivada de la fecha. | Lista y alertas podrían discrepar. | El hook `saving` del modelo recalcula en **toda** escritura; los filtros `scopeAlertas`/`scopeFiltrar` se apoyan en `fecha_vencimiento`, no en la columna. |
| B8 | La guía 5 declaró «evidencia por URL, sin `storage/`». | Contradicción aparente con este sprint. | Aclaración (decisión 6): esa regla aplica a `foto_entrada`/`foto_salida` de cámaras; la subida de archivos del Módulo 10 usa `storage/`, como el Módulo 3 desde la Guía 1. |
| B9 | Video > 20 MB o formato no listado (`mov`, `avi`). | 422 al subir. | Mensaje explícito por tipo en `ArchivoRequest::messages()`; formatos permitidos en la ayuda de la interfaz. |
| B10 | El vehículo no tiene contrato `ACTIVA`. | `contrato_pension_id` queda `NULL`. | Esperado (columna `nullable`); la ficha lo muestra como «Sin contrato activo». |

## Apéndice C - Alcance siguiente (Sprint 7)

Según `PLAN-SCRUM.md`: **Sprint 7 — Usuarios, permisos y bitácora (Módulo 12)**:
CRUD de usuarios, **matriz de permisos módulo × acción** (US-0702), asignación
de roles, ocultado de rutas sin permiso (US-0704) y **bitácora** con datos
anteriores/nuevos (US-0705/0706).

Lo que deja preparado este sprint:

- Los **49 permisos** (los 40 previos + `incidencias.*` y `documentos.*`) ya
  están sembrados con `modulo`/`accion`, listos para la matriz del US-0702.
- Todas las vistas del Módulo 10 ya usan `@can`/`@canany` (menú, botones y
  bloques), que es lo que US-0704 pide formalizar.
- `incidencias` y `vehiculo_documentos` son tablas ideales para el arranque de
  la bitácora (campos `created_at`/`updated_at` y `usuario_id` completos).

## Apéndice D - Registro de ejecución

**Estado del Sprint 6:** ⬜ Pendiente · 🟡 En curso · ✅ Completado

**Fecha de ejecución:** __/10/2026  **Ejecutó:** ______________________

### D.1 Comandos ejecutados

| # | Comandos (tal cual se corrieron) | ✅/❌ | Notas |
|---|---|---|---|
| 1 | `C:\php\php.exe artisan migrate` | ⬜ | Debe decir «Nothing to migrate» (27 migraciones, 0 nuevas). |
| 2 | `C:\php\php.exe artisan db:seed --class=TipoIncidenciaSeeder` | ⬜ | 7 tipos de incidencia. |
| 3 | `C:\php\php.exe artisan db:seed --class=RolPermisoSeeder` | ⬜ | 49 permisos (40 previos intactos). |
| 4 | `C:\php\php.exe artisan route:list --json` | ⬜ | 114 rutas (20 de Módulo 10). |
| 5 | `Get-ChildItem … \| ForEach { C:\php\php.exe -l $_ }` | ⬜ | 17/17 OK. |
| 6 | `npm run build` | ⬜ | Sin cambios de JS: solo confirma que compila. |
| 7 | Servidor `C:\php\php.exe artisan serve --port=8002` | ⬜ | `http://127.0.0.1:8002`. |

### D.2 Historias de usuario

| ID | ¿Entregado? | Evidencia / pantalla |
|---|---|---|
| US-0601 | ⬜ | |
| US-0602 | ⬜ | |
| US-0603 | ⬜ | |
| US-0604 | ⬜ | |
| US-0605 | ⬜ | |

> La evidencia es por URL (decisión 7 del Sprint 5: sin capturas de pantalla).

### D.3 Checklist §7.1

| Bloque | Total | ✅ |
|---|---|---|
| 7.0 Datos mínimos | 4 | |
| US-0601 | 9 | |
| US-0602 | 8 | |
| US-0603 | 6 | |
| US-0604 | 7 | |
| US-0605 | 10 | |

### D.4 Matriz de permisos (resultado real)

| # | Ruta | ADMIN | SEGURIDAD | RECEPCION | CAJERO | SUPERVISOR | INVITADO |
|---|---|---|---|---|---|---|---|
| 1 | `GET /incidencias` | | | | | | |
| 2 | `GET /incidencias/create` | | | | | | |
| 3 | `POST /incidencias` | | | | | | |
| 4 | `GET /incidencias/{id}` | | | | | | |
| 5 | `GET /incidencias/{id}/edit` | | | | | | |
| 6 | `PUT /incidencias/{id}` | | | | | | |
| 7 | `PATCH /incidencias/{id}/estado` | | | | | | |
| 8 | `POST /incidencias/{id}/archivos` | | | | | | |
| 9 | `DELETE /incidencias/{id}/archivos/{a}` | | | | | | |
| 10-15 | `tipos-incidencia` (6 rutas) | | | | | | |
| 16 | `GET /documentos` | | | | | | |
| 17 | `GET /vehiculos/{v}/documentos` | | | | | | |
| 18 | `POST /vehiculos/{v}/documentos` | | | | | | |
| 19 | `PUT /vehiculos/{v}/documentos/{d}` | | | | | | |
| 20 | `DELETE /vehiculos/{v}/documentos/{d}` | | | | | | |

### D.5 Desviaciones detectadas

| # | Desviación de la guía | Cómo se resolvió |
|---|---|---|
| 1 | | |

### D.6 Datos creados en este sprint

| Dato | Cantidad esperada | Real |
|---|---|---|
| `permissions` | 49 | |
| `rutas` | 114 | |
| `tipos_incidencia` | 7 | |
| `incidencias` | ≥ 1 (las de las pruebas) | |
| `incidencia_archivos` | ≥ 1 | |
| `vehiculo_documentos` | ≥ 3 | |
| `users` | 5 (sin cambios) | |
| Archivos en `storage/app/public/incidencias/` | ≥ 1 | |

### D.7 Cierre

- [ ] Los 17 archivos pasan `php -l`.
- [ ] `npm run build` sin errores.
- [ ] 114 rutas y 49 permisos.
- [ ] Checklist §7.1 completo (sin casillas en `⬜`).
- [ ] Matriz §7.2 coincide con la tabla esperada.
- [ ] Sin errores nuevos de la aplicación en `storage/logs/laravel.log`.
- [ ] Este Apéndice D rellenado y **Estado del Sprint 6 = ✅ Completado**.

