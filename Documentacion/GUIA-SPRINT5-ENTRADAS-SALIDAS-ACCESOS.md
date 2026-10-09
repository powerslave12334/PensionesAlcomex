# Guía paso a paso - Sprint 5: Entradas y salidas / Accesos (Módulos 5 y 9)

Guía ejecutable al estilo de `GUIA-SPRINT4-PAGOS-Y-CAJA.md`: cada bloque de código
es un archivo **completo** (se copia tal cual), salvo que el encabezado diga
«fragmento: añadir» o «fragmento: reemplazar». Al final queda el **Apéndice D**
para registrar la ejecución real.

> **Reglas de la casa (no romper):** nunca `php artisan ui bootstrap --auth`,
> nunca `php artisan migrate:fresh` (solo `db:seed --class=...` y `migrate`
> aditivo), nunca editar la BD a mano fuera de los seeders, y todo texto en
> español con acentos escrito desde la herramienta de edición (PowerShell
> rompe el UTF-8).

---

## 0. Alcance

### Historias de usuario (`PLAN-SCRUM.md` §7)

| ID | Historia de usuario | Pts | Prioridad | En esta guía |
|---|---|---|---|---|
| US-0501 | Como **seguridad/recepción**, quiero registrar la **entrada** de un vehículo con fecha/hora, cajón, usuario y evidencia, para dejar el movimiento. | 8 | M | ✅ completo |
| US-0502 | Como **seguridad/recepción**, quiero registrar la **salida**, calcular el tiempo de permanencia y el cobro correspondiente, para facturar el tiempo. | 13 | M | ✅ completo |
| US-0503 | Como **cliente**, quiero un **código QR** por contrato para entrar y salir, para agilizar el acceso. | 8 | S | ✅ QR real (librería npm) |
| US-0504 | Como **seguridad**, quiero leer **tarjeta RFID** asociada al vehículo, para registrar el acceso sin intervención manual. | 8 | S | ✅ mock (UID en BD) |
| US-0505 | Como **sistema**, quiero la lectura automática de placas (**LPR/ANPR**) con las fotos de entrada y salida, para validar el vehículo. | 13 | C | ✅ mock (captura manual de placa + foto) |
| US-0506 | Como **sistema**, quiero controlar las **barreras** (abrir/cerrar) desde el registro de acceso, para integrar hardware. | 8 | C | ✅ mock (`dispositivos`) |
| US-0507 | Como **supervisor**, quiero el listado de entradas/salidas con filtros por fecha y vehículo, para auditar el flujo. | 5 | M | ✅ completo |
| | **Total** | **63** → comprometer **40** (0505/0506 con mock) | | |

**BD:** `movimientos_acceso`, `puertas`, `dispositivos` **ya existen** (Sprint 0,
están vacías) + 2 migraciones aditivas nuevas.

### Estado actual (tras Sprint 4)

- Rutas **79**, permisos **36**, usuarios 4 (`admin`, `cajero`, `supervisor`,
  `seguridad`), 10 tarifas (5 MENSUAL + HORA $25 / DIA $180 / NOCHE $120 /
  ANUAL / EVENTO de **Automovil**), 5 tipos de vehículo, 1 vehículo
  `ABC1234` (cliente 1, **sin** `uid_rfid`), 2 contratos (`PEN-2026-0002` ACTIVA
  con cajón A-01), 3 pagos (`REC-2026-0001` PAGADO, `0002` CANCELADO,
  `0003` PENDIENTE).
- Espacios: `A-01` (zona 1) **OCUPADO** por el contrato, `A-01` (zona 2)
  DISPONIBLE, `A-02` (zona 1) RESERVADO.
- `puertas` = 0, `dispositivos` = 0, `movimientos_acceso` = 0.
- Menú «Accesos» está como `<span class="nav-link disabled">`.
- Dashboard: la tarjeta «Vehículos dentro» está en `—` con la leyenda «Sprint 8».
- Servidor dev en `http://127.0.0.1:8002` (el 8000 lo ocupa otra aplicación);
  `php` del PATH resuelve a 8.5.11 → usar `C:\php\php.exe` (8.1.18) cuando se
  necesite salida limpia (`route:list --json`).

### Artefactos que crea este sprint

| Tipo | Archivo |
|---|---|
| Migración | `database/migrations/2026_10_08_220000_add_cobro_to_movimientos_acceso_table.php` |
| Migración | `database/migrations/2026_10_08_220100_add_uid_rfid_to_vehiculos_table.php` |
| Seeder | `database/seeders/DispositivoSeeder.php`, `database/seeders/PuertaSeeder.php` |
| Seeder | `database/seeders/RolPermisoSeeder.php` (+ módulo `accesos`), `DatabaseSeeder.php` |
| Modelo | `app/Models/MovimientoAcceso.php`, `Puerta.php`, `Dispositivo.php` |
| Modelo | `app/Models/Vehiculo.php` (+`uid_rfid`), `ContratoPension.php` (+movimientos) |
| Request | `app/Http/Requests/EntradaRequest.php`, `SalidaRequest.php` |
| Controlador | `app/Http/Controllers/AccesoController.php` |
| Vistas | `resources/views/accesos/{index,create,salida,show,rfid,lpr,qr,dispositivos}.blade.php` |
| JS | `resources/js/app.js` (+ `npm i qrcode-generator`) |
| Rutas/menú | `routes/web.php` (13 rutas → **92**), `layouts/app.blade.php`, `HomeController`, `home.blade.php` |

---

## 1. Decisiones del sprint

1. **Alcance completo con mocks.** QR/RFID/LPR/barrera no tienen hardware: se
   implementan como flujos funcionales contra la BD (`uid_rfid`, `placas`,
   `configuracion.barrera`), sin leer puertos seriales ni cámaras.
2. **Dos migraciones aditivas** sobre tablas ya existentes. Se ejecuta
   `php artisan migrate` (valido, no es `migrate:fresh`) y solo agregan columnas.
3. **Pareja ENTRADA/SALIDA.** La fila `SALIDA` guarda `entrada_id` (FK a la misma
   tabla). *Entrada abierta* = fila `ENTRADA` sin `SALIDA` que la referencie.
   Regla dura: **un solo movimiento abierto por vehículo** (validado en servidor).
4. **Permanencia** = minutos entre `fecha_hora` de la ENTRADA y el de la SALIDA,
   guardado en `permanencia_minutos` (nunca se recalcula después; es un dato
   histórico).
5. **Cobro por permanencia solo a visitantes.** Si el vehículo tiene contrato
   `ACTIVA` vigente → `estado_cobro = NO_APLICA` y `cobro = 0` (el pensionado ya
   pagó la mensualidad). Visitante → tarifa `HORA` (por defecto), `DIA` o
   `NOCHE` vigente del **tipo de vehículo**:
   `unidades = max(1, ceil(minutos / bloque))` con bloques 60 / 1440 / 720 min,
   `cobro = round(unidades × precio, 2)`. **El total se recalcula en servidor**,
   igual que en Sprint 4.
6. **El cobro de visita NO crea una fila en `pagos`**: `pagos.contrato_pension_id`
   es `NOT NULL` (migración Sprint 0) y un visitante no tiene contrato. El cobro
   vive en la SALIDA (`cobro`, `estado_cobro` NO_APLICA/PENDIENTE/COBRADO,
   `metodo_pago_id`). El cruce con caja queda para los reportes del Sprint 8
   (Apéndice C).
7. **Evidencia por URL, no por disco.** `foto_entrada` / `foto_salida` guardan una
   URL (`max:500`), igual que `vehiculos.foto_url`: no se usa `storage/` ni
   `UploadedFile` en este proyecto.
8. **Cajón:** al ENTRAR el cajón es opcional. Si el elegido está `DISPONIBLE`
   pasa a `OCUPADO`; si es el cajón asignado al contrato del vehículo se usa sin
   tocar el estado; cualquier otro estado (`RESERVADO`, `MANTENIMIENTO`,
   `BLOQUEADO` o ajeno) rechaza la entrada. Al SALIR se devuelve a `DISPONIBLE`
   solo si no hay asignación vigente ni otra entrada abierta en ese cajón.
9. **QR real** con `qrcode-generator` (npm, sin dependencias): el payload es la
   URL `…/accesos/create?qr=NUMERO_CONTRATO`, que al abrirla precarga la entrada
   con `metodo_acceso = QR`. Si `npm install` fallara por red, la vista degrada
   mostrando el código alfanumérico (Apéndice B).
10. **RFID y LPR conmutan entrada/salida.** `POST /accesos/rfid` y
    `POST /accesos/lpr` buscan el vehículo (por `uid_rfid` o `placas`) y: si está
    **fuera** registran ENTRADA, si está **adentro** registran SALIDA. Así el
    mock imita el comportamiento de un torniquete real.
11. **Barrera mock:** `dispositivos` tipo `BARRERA` guardan
    `configuracion = {"barrera": "ABIERTA"|"CERRADA"}` y `ultimo_contacto = now()`
    (el campo `estado` de la migración sigue siendo la salud del equipo:
    ACTIVO/INACTIVO/FALLA).
12. **Permisos nuevos (36 → 40):** `accesos.ver`, `accesos.registrar`,
    `accesos.cobrar`, `accesos.configurar`. Reparto:
    ADMINISTRADOR 4 · SEGURIDAD `ver+registrar+cobrar` · RECEPCION
    `ver+registrar+cobrar` · CAJERO `ver+cobrar` · SUPERVISOR solo `ver`.
13. **13 rutas nuevas (79 → 92)** y **las estáticas antes del `resource`**
    (lección de Sprint 4: `pagos/corte` casi se come `pagos/{pago}`).
14. **El dashboard se aprovecha:** la tarjeta «Vehículos dentro» pasa de `—` a
    `MovimientoAcceso::abiertas()->count()` (solo con `accesos.ver`).
15. **Sin dependencias de otras guías:** se puede ejecutar este sprint solo;
    solo lee datos de Sprint 2/3/4 (vehículos, cajones, contratos, métodos de
    pago).
16. **Zona horaria de la app = `America/Mexico_City`** (antes `UTC`). El equipo
    corre en «Central Standard Time (Mexico)»: con la app en UTC, los
    `datetime-local` del navegador y `now()` del servidor se desfasaban 6 h y el
    Sprint 5 lo delata de inmediato (RFID/LPR marcan `now()` y la caseta captura
    la hora local). Es un cambio en `config/app.php`, no en datos.

---

## FASE 1 - Migraciones nuevas (2 archivos aditivos)

### 1.1 Crear los archivos

```powershell
# Nombres exactos: timestamp > 2026_10_06_211900 (la última migración actual)
php artisan make:migration add_cobro_to_movimientos_acceso_table --table=movimientos_acceso
php artisan make:migration add_uid_rfid_to_vehiculos_table --table=vehiculos
```

Si ya existieran (corrida repetida), no hace falta regenerarlos: se editan tal
como están.

### 1.2 `database/migrations/2026_10_08_220000_add_cobro_to_movimientos_acceso_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 5 (US-0502): datos de la SALIDA que cierra una ENTRADA.
     * Tabla ya poblada por el Sprint 0; aquí solo se agregan columnas.
     */
    public function up(): void
    {
        Schema::table('movimientos_acceso', function (Blueprint $table) {
            $table->foreignId('entrada_id')
                ->nullable()
                ->after('puerta_id')
                ->constrained('movimientos_acceso')
                ->nullOnDelete();

            $table->unsignedInteger('permanencia_minutos')
                ->nullable()
                ->after('entrada_id');

            $table->foreignId('tarifa_id')
                ->nullable()
                ->after('permanencia_minutos')
                ->constrained('tarifas')
                ->nullOnDelete();

            $table->decimal('cobro', 12, 2)->default(0)->after('tarifa_id');

            $table->string('estado_cobro', 15)
                ->default('NO_APLICA')
                ->after('cobro');

            $table->foreignId('metodo_pago_id')
                ->nullable()
                ->after('estado_cobro')
                ->constrained('metodos_pago')
                ->nullOnDelete();

            $table->index(['estado_cobro']);
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_acceso', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entrada_id');
            $table->dropConstrainedForeignId('tarifa_id');
            $table->dropConstrainedForeignId('metodo_pago_id');
            $table->dropColumn(['permanencia_minutos', 'cobro', 'estado_cobro']);
        });
    }
};
```

### 1.3 `database/migrations/2026_10_08_220100_add_uid_rfid_to_vehiculos_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * US-0504: UID de la tarjeta RFID pegada al parabrisas.
     * En una integración real lo escribe el lector; aquí lo carga el alta
     * del vehículo y lo usa el formulario mock de RFID.
     */
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->string('uid_rfid', 40)
                ->nullable()
                ->unique()
                ->after('placas');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropUnique(['uid_rfid']);
            $table->dropColumn('uid_rfid');
        });
    }
};
```

### 1.4 Ejecutar

```powershell
php artisan migrate           # solo las 2 nuevas (las 25 previas ya corrieron)
php artisan migrate:status    # 27 en verde
```

Esperado: `Running [2026_10_08_220000_...]` y
`Running [2026_10_08_220100_...]` → `Done`.

---

## FASE 2 - Seeders: dispositivos, puertas y permisos `accesos.*`

### 2.1 `database/seeders/DispositivoSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DispositivoSeeder extends Seeder
{
    /**
     * Catálogo de hardware (hoja "dispositivos" del Excel).
     * Idempotente: updateOrCreate por nombre.
     */
    public function run(): void
    {
        $dispositivos = [
            [
                'nombre' => 'Camara entrada principal',
                'tipo'   => 'CAMARA',
                'marca'  => 'Hikvision',
                'modelo' => 'DS-2CD2143G2',
                'ubicacion' => 'Caseta de entrada, mastil derecho',
                'configuracion' => null,
            ],
            [
                'nombre' => 'Camara salida principal',
                'tipo'   => 'CAMARA',
                'marca'  => 'Hikvision',
                'modelo' => 'DS-2CD2143G2',
                'ubicacion' => 'Caseta de salida, mastil izquierdo',
                'configuracion' => null,
            ],
            [
                'nombre' => 'Lector RFID caseta',
                'tipo'   => 'LECTOR_RFID',
                'marca'  => 'HID',
                'modelo' => 'Signo 20',
                'ubicacion' => 'Caseta de entrada, poste',
                'configuracion' => ['frecuencia' => '13.56 MHz'],
            ],
            [
                'nombre' => 'Lector QR caseta',
                'tipo'   => 'LECTOR_QR',
                'marca'  => 'Honeywell',
                'modelo' => 'Granit 1991i',
                'ubicacion' => 'Caseta de entrada, ventanilla',
                'configuracion' => null,
            ],
            [
                'nombre' => 'LPR entrada',
                'tipo'   => 'LECTOR_PLACAS',
                'marca'  => 'PlateRecognizer',
                'modelo' => 'ANPR-cam',
                'ubicacion' => 'Arco de entrada',
                'configuracion' => ['pais' => 'MX'],
            ],
            [
                'nombre' => 'Barrera entrada',
                'tipo'   => 'BARRERA',
                'marca'  => 'FAAC',
                'modelo' => 'S418',
                'ubicacion' => 'Arco de entrada',
                'configuracion' => ['barrera' => 'CERRADA'],
            ],
            [
                'nombre' => 'Barrera salida',
                'tipo'   => 'BARRERA',
                'marca'  => 'FAAC',
                'modelo' => 'S418',
                'ubicacion' => 'Arco de salida',
                'configuracion' => ['barrera' => 'CERRADA'],
            ],
        ];

        foreach ($dispositivos as $dispositivo) {
            DB::table('dispositivos')->updateOrInsert(
                ['nombre' => $dispositivo['nombre']],
                $dispositivo + [
                    'estado' => 'ACTIVO',
                    'activo' => true,
                    'ultimo_contacto' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
```

### 2.2 `database/seeders/PuertaSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PuertaSeeder extends Seeder
{
    /**
     * Las 2 casetas del recinto. Cada puerta apunta a su barrera
     * (FK dispositivos.dispositivo_id → nullOnDelete).
     * Depende de DispositivoSeeder.
     */
    public function run(): void
    {
        $barreraEntrada = DB::table('dispositivos')
            ->where('nombre', 'Barrera entrada')->value('id');
        $barreraSalida = DB::table('dispositivos')
            ->where('nombre', 'Barrera salida')->value('id');

        $puertas = [
            [
                'nombre' => 'Entrada principal',
                'ubicacion' => 'Calle 5 de Mayo, caseta 1',
                'tipo' => 'ENTRADA',
                'dispositivo_id' => $barreraEntrada,
            ],
            [
                'nombre' => 'Salida principal',
                'ubicacion' => 'Calle 5 de Mayo, caseta 2',
                'tipo' => 'SALIDA',
                'dispositivo_id' => $barreraSalida,
            ],
        ];

        foreach ($puertas as $puerta) {
            DB::table('puertas')->updateOrInsert(
                ['nombre' => $puerta['nombre']],
                $puerta + [
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
```

### 2.3 `database/seeders/RolPermisoSeeder.php` - ampliar

**Fragmento: reemplazar** el array `$modulos` (añade el bloque Sprint 5):

```php
        // Permisos: modulo.accion  (hoja "permisos" del Excel)
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
        ];
```

**Fragmento: añadir dentro de cada `syncPermissions([...])`** (las listas
existentes **se reemplazan enteras** por Spatie, así que hay que dejar el
contenido anterior y agregar estas líneas):

```php
        // RECEPCION — Sprint 5: abre y cierra caseta
        'accesos.ver', 'accesos.registrar', 'accesos.cobrar',

        // SUPERVISOR — Sprint 5: solo audita (US-0507)
        'accesos.ver',

        // CAJERO — Sprint 5: liquida el cobro de la salida de visitantes
        'accesos.ver', 'accesos.cobrar',

        // SEGURIDAD — Sprint 5: opera la caseta completa
        'accesos.ver', 'accesos.registrar', 'accesos.cobrar',
```

`ADMINISTRADOR` no se toca: hace `syncPermissions(Permission::all())`.

> El permiso `accesos.configurar` (control de barreras y catálogo de
> dispositivos) queda **solo en ADMINISTRADOR**.

### 2.4 `database/seeders/DatabaseSeeder.php`

**Fragmento: reemplazar** el `run()`:

```php
    public function run(): void
    {
        $this->call([
            ZonaSeeder::class,
            TipoVehiculoSeeder::class,
            TarifaSeeder::class,
            MetodoPagoSeeder::class,     // Sprint 4
            DispositivoSeeder::class,    // Sprint 5 (nuevo)
            PuertaSeeder::class,         // Sprint 5 (nuevo) — requiere dispositivos
            RolPermisoSeeder::class,
        ]);
    }
```

### 2.5 Ejecutar

```powershell
php artisan db:seed --class=DispositivoSeeder     # 7 dispositivos
php artisan db:seed --class=PuertaSeeder          # 2 puertas
php artisan db:seed --class=RolPermisoSeeder      # 40 permisos
```

Verificación:

```powershell
php artisan tinker
>>> DB::table('dispositivos')->count()      // 7
>>> DB::table('puertas')->count()           // 2
>>> DB::table('permissions')->count()       // 40
>>> \Spatie\Permission\Models\Role::findByName('SEGURIDAD')->permissions()->pluck('name')
// ['accesos.cobrar','accesos.registrar','accesos.ver','contratos.ver','espacios.ver']
```

---

## FASE 3 - Modelos

### 3.1 `app/Models/MovimientoAcceso.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MovimientoAcceso extends Model
{
    protected $table = 'movimientos_acceso';

    public const TIPOS = ['ENTRADA', 'SALIDA'];

    public const METODOS = ['MANUAL', 'QR', 'RFID', 'LPR'];

    public const ESTADOS_COBRO = ['NO_APLICA', 'PENDIENTE', 'COBRADO'];

    public const TIPO_LABELS = ['ENTRADA' => 'Entrada', 'SALIDA' => 'Salida'];

    public const METODO_LABELS = [
        'MANUAL' => 'Manual',
        'QR'     => 'Código QR',
        'RFID'   => 'Tarjeta RFID',
        'LPR'    => 'Lectura de placas',
    ];

    public const ESTADO_COBRO_LABELS = [
        'NO_APLICA' => 'Sin cobro',
        'PENDIENTE' => 'Por cobrar',
        'COBRADO'   => 'Cobrado',
    ];

    public const ESTADO_COBRO_CLASSES = [
        'NO_APLICA' => 'bg-secondary',
        'PENDIENTE' => 'bg-warning text-dark',
        'COBRADO'   => 'bg-success',
    ];

    protected $fillable = [
        'vehiculo_id', 'cliente_id', 'contrato_pension_id', 'espacio_id',
        'tipo', 'fecha_hora', 'placas_detectadas', 'usuario_id', 'puerta_id',
        'metodo_acceso', 'foto_entrada', 'foto_salida', 'observaciones',
        // Sprint 5 (migración aditiva)
        'entrada_id', 'permanencia_minutos', 'tarifa_id', 'cobro',
        'estado_cobro', 'metodo_pago_id',
    ];

    protected $casts = [
        'fecha_hora'           => 'datetime',
        'permanencia_minutos'  => 'integer',
        'cobro'                => 'decimal:2',
    ];

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoPension::class, 'contrato_pension_id');
    }

    public function espacio(): BelongsTo
    {
        return $this->belongsTo(Espacio::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function puerta(): BelongsTo
    {
        return $this->belongsTo(Puerta::class);
    }

    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }

    /** En la SALIDA: la ENTRADA que cierra. */
    public function entrada(): BelongsTo
    {
        return $this->belongsTo(self::class, 'entrada_id');
    }

    /** En la ENTRADA: la SALIDA que la cerró (null = sigue adentro). */
    public function salida(): HasOne
    {
        return $this->hasOne(self::class, 'entrada_id');
    }

    /** Entradas sin salida: vehículos que están adentro ahorita. */
    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->where('tipo', 'ENTRADA')->whereDoesntHave('salida');
    }

    public function scopeFiltrar(
        Builder $query,
        ?string $q,
        ?string $tipo,
        ?string $metodo,
        ?string $desde,
        ?string $hasta,
        ?string $estadoCobro
    ): Builder {
        return $query
            ->when($q, function (Builder $b) use ($q) {
                $t = "%{$q}%";
                $b->where(fn (Builder $w) => $w
                    ->where('placas_detectadas', 'like', $t)
                    ->orWhereHas('vehiculo', fn (Builder $v) => $v->buscar($q))
                    ->orWhereHas('cliente', fn (Builder $c) => $c->buscar($q)));
            })
            ->when($tipo, fn (Builder $b, string $v) => $b->where('tipo', $v))
            ->when($metodo, fn (Builder $b, string $v) => $b->where('metodo_acceso', $v))
            ->when($estadoCobro, fn (Builder $b, string $v) => $b->where('estado_cobro', $v))
            ->when($desde, fn (Builder $b) => $b->whereDate('fecha_hora', '>=', $desde))
            ->when($hasta, fn (Builder $b) => $b->whereDate('fecha_hora', '<=', $hasta));
    }

    /** true si la entrada todavía no tiene salida registrada. */
    public function getAbiertaAttribute(): bool
    {
        return $this->tipo === 'ENTRADA' && $this->salida === null;
    }

    /** "2 h 15 min" — null si el movimiento no está cerrado. */
    public function getPermanenciaAttribute(): ?string
    {
        if ($this->permanencia_minutos === null) {
            return null;
        }

        $min = (int) $this->permanencia_minutos;
        $h = intdiv($min, 60);
        $m = $min % 60;

        return $h > 0 ? "{$h} h {$m} min" : "{$m} min";
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_LABELS[$this->tipo] ?? $this->tipo;
    }

    public function getMetodoLabelAttribute(): string
    {
        return self::METODO_LABELS[$this->metodo_acceso] ?? $this->metodo_acceso;
    }

    public function getEstadoCobroLabelAttribute(): string
    {
        return self::ESTADO_COBRO_LABELS[$this->estado_cobro] ?? $this->estado_cobro;
    }

    public function getEstadoCobroClaseAttribute(): string
    {
        return self::ESTADO_COBRO_CLASSES[$this->estado_cobro] ?? 'bg-secondary';
    }
}
```

### 3.2 `app/Models/Puerta.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Puerta extends Model
{
    protected $table = 'puertas';

    public const TIPOS = ['ENTRADA', 'SALIDA'];

    protected $fillable = ['nombre', 'ubicacion', 'tipo', 'dispositivo_id', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoAcceso::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
```

### 3.3 `app/Models/Dispositivo.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispositivo extends Model
{
    protected $table = 'dispositivos';

    public const TIPOS = [
        'CAMARA', 'LECTOR_RFID', 'LECTOR_QR', 'LECTOR_PLACAS',
        'BARRERA', 'SENSOR', 'IMPRESORA',
    ];

    public const TIPO_LABELS = [
        'CAMARA'        => 'Cámara',
        'LECTOR_RFID'   => 'Lector RFID',
        'LECTOR_QR'     => 'Lector QR',
        'LECTOR_PLACAS' => 'Lector de placas (LPR)',
        'BARRERA'       => 'Barrera',
        'SENSOR'        => 'Sensor',
        'IMPRESORA'     => 'Impresora',
    ];

    public const ESTADOS = ['ACTIVO', 'INACTIVO', 'FALLA'];

    protected $fillable = [
        'nombre', 'tipo', 'marca', 'modelo', 'numero_serie', 'ip', 'mac',
        'ubicacion', 'estado', 'configuracion', 'ultimo_contacto', 'activo',
    ];

    protected $casts = [
        'configuracion'   => 'array',
        'ultimo_contacto' => 'datetime',
        'activo'          => 'boolean',
    ];

    public function puertas(): HasMany
    {
        return $this->hasMany(Puerta::class);
    }

    public function esBarrera(): bool
    {
        return $this->tipo === 'BARRERA';
    }

    /** Estado mock de la barrera: ABIERTA / CERRADA. */
    public function getBarreraAttribute(): string
    {
        return $this->configuracion['barrera'] ?? 'CERRADA';
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_LABELS[$this->tipo] ?? $this->tipo;
    }
}
```

### 3.4 `app/Models/Vehiculo.php` - ampliar

**Fragmento: reemplazar** `$fillable` (agrega `uid_rfid`) y añade la relación de
accesos:

```php
    protected $fillable = [
        'cliente_id', 'placas', 'uid_rfid', 'marca', 'modelo', 'anio', 'color',
        'tipo_vehiculo_id', 'vin', 'numero_motor', 'foto_url',
        'observaciones', 'activo',
    ];
```

```php
    public function contratos(): HasMany
    {
        return $this->hasMany(ContratoPension::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoAcceso::class);
    }

    /** Movimiento abierto (el vehículo está dentro del recinto). */
    public function entradaAbierta(): HasOne
    {
        return $this->hasOne(MovimientoAcceso::class)
            ->where('tipo', 'ENTRADA')
            ->whereDoesntHave('salida');
    }
```

Import necesario: `use Illuminate\Database\Eloquent\Relations\HasMany;` y
`use Illuminate\Database\Eloquent\Relations\HasOne;`. La relación `contratos()`
**no existía**: la usa `EntradaRequest` para saber si el cajón elegido es el
asignado a la pensión.

### 3.5 `app/Models/ContratoPension.php` - ampliar

**Fragmento: añadir** (junto a `pagos()`):

```php
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoAcceso::class);
    }
```

---

## FASE 4 - Form Requests

### 4.1 `app/Http/Requests/EntradaRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\Espacio;
use App\Models\MovimientoAcceso;
use App\Models\Vehiculo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntradaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el controlador hace $this->authorize('accesos.registrar')
    }

    public function rules(): array
    {
        return [
            'vehiculo_id' => [
                'required', 'integer', Rule::exists('vehiculos', 'id')->where('activo', true),
                // US-0501: el vehículo debe estar dado de baja activo, y no puede
                // tener otra entrada abierta (una sola pareja entrada/salida).
                function (string $attribute, mixed $value, Closure $fail): void {
                    $vehiculo = Vehiculo::find($value);
                    if (! $vehiculo) {
                        return;
                    }

                    if (! $vehiculo->activo) {
                        $fail("El vehículo {$vehiculo->placas} está dado de baja: no puede entrar.");
                        return;
                    }

                    $abierta = MovimientoAcceso::abiertas()->where('vehiculo_id', $vehiculo->id)->first();
                    if ($abierta) {
                        $fail("El vehículo {$vehiculo->placas} ya está adentro (entrada "
                            . $abierta->fecha_hora->format('d/m/Y H:i') . "). Registra primero su salida.");
                    }
                },
            ],
            'fecha_hora' => [
                'required', 'date',
                // sin viajes en el tiempo (máx. 5 min de tolerancia de reloj)
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || strtotime($value) === false) {
                        return;
                    }
                    if (\Illuminate\Support\Carbon::parse($value)->greaterThan(now()->addMinutes(5))) {
                        $fail('La fecha y hora de entrada no puede ser futura.');
                    }
                },
            ],
            'metodo_acceso' => ['required', 'in:' . implode(',', MovimientoAcceso::METODOS)],
            'espacio_id' => [
                'nullable', 'integer', Rule::exists('espacios', 'id')->where('activo', true),
                // el cajón debe estar libre o ser el asignado al contrato del vehículo
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $espacio = Espacio::find($value);
                    if (! $espacio) {
                        return;
                    }

                    if ($espacio->estado === 'DISPONIBLE') {
                        return;
                    }

                    $vehiculo = Vehiculo::find((int) $this->input('vehiculo_id'));
                    $contrato = $vehiculo
                        ? $vehiculo->contratos()->where('estado', 'ACTIVA')->first()
                        : null;
                    $esElSuyo = $contrato
                        && $contrato->asignaciones()->where('espacio_id', $espacio->id)
                            ->whereNull('fecha_fin')->exists();

                    if (! $esElSuyo) {
                        $fail('El cajón ' . $espacio->codigo . ' está '
                            . strtolower($espacio->estado_label) . ': elige otro.');
                    }
                },
            ],
            'puerta_id' => [
                'nullable', 'integer',
                Rule::exists('puertas', 'id')->where('activo', true),
            ],
            'foto_entrada'  => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehiculo_id.required'     => 'Selecciona el vehículo que entra.',
            'vehiculo_id.exists'       => 'Ese vehículo no existe o está inactivo.',
            'fecha_hora.required'      => 'Indica la fecha y hora de la entrada.',
            'fecha_hora.date'          => 'La fecha y hora de la entrada no es válida.',
            'metodo_acceso.required'   => 'Indica cómo se registró la entrada.',
            'metodo_acceso.in'         => 'Ese método de acceso no es válido.',
            'espacio_id.exists'        => 'Ese cajón no existe.',
            'puerta_id.exists'         => 'Esa puerta no existe.',
            'foto_entrada.max'         => 'La URL de la foto no puede exceder 500 caracteres.',
            'observaciones.max'        => 'Las observaciones no pueden exceder 1000 caracteres.',
        ];
    }
}
```

> `Vehiculo::contratos()` es la relación `HasMany(ContratoPension::class)` que ya
> existe en el modelo (Sprint 3).

### 4.2 `app/Http/Requests/SalidaRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\MovimientoAcceso;
use App\Models\Puerta;
use App\Models\Tarifa;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SalidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el controlador autoriza: accesos.registrar (+ cobrar si cobra)
    }

    public function rules(): array
    {
        return [
            'fecha_hora' => [
                'required', 'date',
                // nunca antes de la entrada, nunca en el futuro
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || strtotime($value) === false) {
                        return;
                    }

                    $salida = Carbon::parse($value);
                    $entrada = $this->route('acceso');

                    if ($entrada instanceof MovimientoAcceso && $salida->lt($entrada->fecha_hora)) {
                        $fail('La salida no puede ser anterior a la entrada ('
                            . $entrada->fecha_hora->format('d/m/Y H:i') . ').');
                    }

                    if ($salida->greaterThan(now()->addMinutes(5))) {
                        $fail('La fecha y hora de salida no puede ser futura.');
                    }
                },
            ],
            'puerta_id' => ['nullable', 'integer', Rule::exists('puertas', 'id')->where('activo', true)],
            // US-0502: tarifa de visita (si es pensionado el controller la ignora)
            'tarifa_id' => [
                'nullable', 'integer', Rule::exists('tarifas', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $tarifa = Tarifa::find($value);
                    if (! $tarifa) {
                        return;
                    }

                    if (! in_array($tarifa->tipo_tarifa, ['HORA', 'DIA', 'NOCHE'], true)) {
                        $fail('Solo se pueden aplicar tarifas de permanencia (por hora, día o noche).');
                        return;
                    }

                    if (! $tarifa->esVigente()) {
                        $fail("La tarifa «{$tarifa->nombre}» no está vigente hoy.");
                    }
                },
            ],
            'estado_cobro' => ['required', 'in:' . implode(',', MovimientoAcceso::ESTADOS_COBRO)],
            // solo se llena si el cobro va a quedarse como COBRADO
            'metodo_pago_id' => [
                'required_if:estado_cobro,COBRADO', 'nullable', 'integer',
                Rule::exists('metodos_pago', 'id')->where('activo', true),
            ],
            'foto_salida'   => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_hora'     => 'fecha y hora de salida',
            'estado_cobro'   => 'estado del cobro',
            'metodo_pago_id' => 'método de pago',
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_hora.required'           => 'Indica la fecha y hora de la salida.',
            'fecha_hora.date'               => 'La fecha y hora de la salida no es válida.',
            'puerta_id.exists'              => 'Esa puerta no existe.',
            'tarifa_id.exists'              => 'Esa tarifa no existe.',
            'estado_cobro.required'         => 'Indica el estado del cobro.',
            'estado_cobro.in'               => 'Ese estado de cobro no es válido.',
            'metodo_pago_id.required_if'    => 'Selecciona con qué método se cobró.',
            'metodo_pago_id.exists'         => 'Ese método de pago no existe o está inactivo.',
            'foto_salida.max'               => 'La URL de la foto no puede exceder 500 caracteres.',
            'observaciones.max'             => 'Las observaciones no pueden exceder 1000 caracteres.',
        ];
    }
}
```

---

## FASE 5 - `app/Http/Controllers/AccesoController.php`

Controlador completo (archivo único). Notas de diseño:

- `crearEntrada()` y `cerrarSalida()` son **métodos privados compartidos**: los
  llaman el formulario manual (`store`/`salida`) y también los mocks RFID/LPR.
- Todo el cálculo (permanencia, cobro, liberación de cajón) se hace **en el
  servidor**; el formulario solo ofrece la tarifa sugerida.
- La barrera es un `Dispositivo` tipo `BARRERA` con
  `configuracion['barrera']` y `ultimo_contacto = now()`.

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntradaRequest;
use App\Http\Requests\SalidaRequest;
use App\Models\ContratoPension;
use App\Models\Dispositivo;
use App\Models\Espacio;
use App\Models\MetodoPago;
use App\Models\MovimientoAcceso;
use App\Models\Puerta;
use App\Models\Tarifa;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccesoController extends Controller
{
    /* ---------------------------------------------------------------- *
     * US-0507 / US-0501 — listado
     * ---------------------------------------------------------------- */

    public function index(Request $request)
    {
        $this->authorize('accesos.ver');

        $request->validate([
            'q'           => ['nullable', 'string', 'max:100'],
            'tipo'        => ['nullable', 'in:' . implode(',', MovimientoAcceso::TIPOS)],
            'metodo'      => ['nullable', 'in:' . implode(',', MovimientoAcceso::METODOS)],
            'estado_cobro'=> ['nullable', 'in:' . implode(',', MovimientoAcceso::ESTADOS_COBRO)],
            'desde'       => ['nullable', 'date'],
            'hasta'       => ['nullable', 'date', 'after_or_equal:desde'],
        ], [
            'hasta.after_or_equal' => 'La fecha «hasta» no puede ser anterior a «desde».',
        ]);

        $q            = $request->string('q')->toString() ?: null;
        $tipo         = $request->string('tipo')->toString() ?: null;
        $metodo       = $request->string('metodo')->toString() ?: null;
        $estadoCobro  = $request->string('estado_cobro')->toString() ?: null;
        $desde        = $request->string('desde')->toString() ?: null;
        $hasta        = $request->string('hasta')->toString() ?: null;

        $consulta = MovimientoAcceso::filtrar($q, $tipo, $metodo, $desde, $hasta, $estadoCobro);

        $movimientos = (clone $consulta)
            ->with(['vehiculo', 'cliente', 'espacio', 'puerta', 'usuario',
                'salida:id,entrada_id,fecha_hora,permanencia_minutos'])
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $resumen = [
            'adentro'    => MovimientoAcceso::abiertas()->count(),
            'entradas'   => (clone $consulta)->where('tipo', 'ENTRADA')->whereDate('fecha_hora', now())->count(),
            'salidas'    => (clone $consulta)->where('tipo', 'SALIDA')->whereDate('fecha_hora', now())->count(),
            'porCobrar'  => (clone $consulta)->where('estado_cobro', 'PENDIENTE')->sum('cobro'),
        ];

        return view('accesos.index', [
            'movimientos' => $movimientos,
            'resumen'     => $resumen,
            'filtros'     => [
                'q' => $q, 'tipo' => $tipo, 'metodo' => $metodo,
                'estado_cobro' => $estadoCobro, 'desde' => $desde, 'hasta' => $hasta,
            ],
            'tieneFiltro' => array_filter([$q, $tipo, $metodo, $estadoCobro, $desde, $hasta], fn ($v) => $v)
                !== [],
        ]);
    }

    /* ---------------------------------------------------------------- *
     * US-0501 — alta manual de entrada (y precarga QR, US-0503)
     * ---------------------------------------------------------------- */

    public function create(Request $request)
    {
        $this->authorize('accesos.registrar');

        $prefill = [
            'vehiculo_id'    => $request->filled('vehiculo_id') ? (int) $request->vehiculo_id : '',
            'metodo_acceso'  => $request->filled('qr') ? 'QR' : 'MANUAL',
            'fecha_hora'     => now()->format('Y-m-d\TH:i'),
            'placas_preview' => null,
            'contrato'       => null,
        ];

        // US-0503: el QR lleva el número de contrato; si coincide con uno ACTIVO
        // el formulario ya trae vehículo y método QR precargados.
        if ($request->filled('qr')) {
            $contrato = ContratoPension::with('vehiculo')
                ->where('numero_contrato', $request->string('qr')->toString())
                ->where('estado', 'ACTIVA')
                ->first();

            if (! $contrato) {
                return redirect()->route('accesos.create')->with(
                    'error',
                    'El código QR no corresponde a una pensión activa.'
                );
            }

            if (! $contrato->vehiculo?->activo) {
                return redirect()->route('accesos.create')->with(
                    'error',
                    'La pensión ' . $contrato->numero_contrato . ' tiene el vehículo dado de baja.'
                );
            }

            $prefill['vehiculo_id']    = $contrato->vehiculo_id;
            $prefill['contrato']       = $contrato;
            $prefill['placas_preview'] = $contrato->vehiculo->placas;
        }

        return view('accesos.create', $this->formEntrada($prefill));
    }

    public function store(EntradaRequest $request)
    {
        $this->authorize('accesos.registrar');

        $datos     = $request->validated();
        $vehiculo  = Vehiculo::with('cliente')->findOrFail($datos['vehiculo_id']);
        $contrato  = $this->contratoActivo($vehiculo);
        $entrada   = $this->crearEntrada($vehiculo, $contrato, $datos);

        return redirect()->route('accesos.show', $entrada)->with(
            'success',
            "Entrada registrada para {$vehiculo->placas} a las {$entrada->fecha_hora->format('H:i')} h."
        );
    }

    /* ---------------------------------------------------------------- *
     * US-0502 — salida, permanencia y cobro
     * ---------------------------------------------------------------- */

    public function salida(MovimientoAcceso $acceso)
    {
        $this->authorize('accesos.registrar');

        if ($acceso->tipo !== 'ENTRADA' || $acceso->salida()->exists()) {
            return redirect()->route('accesos.show', $acceso)
                ->with('error', 'Ese movimiento no es una entrada abierta.');
        }

        $acceso->load(['vehiculo', 'cliente', 'contrato', 'espacio']);

        $contrato = $this->contratoActivo($acceso->vehiculo);

        return view('accesos.salida', [
            'acceso'          => $acceso,
            'contrato'        => $contrato,
            'permanenciaMin'  => (int) $acceso->fecha_hora->diffInMinutes(now()),
            'tarifas'         => $contrato ? collect() : $this->tarifasPermanencia($acceso->vehiculo),
            'tarifaSugerida'  => $contrato ? null : $this->tarifaSugerida($acceso->vehiculo),
            'puertas'         => Puerta::activas()->where('tipo', 'SALIDA')->get(),
            'metodos'         => MetodoPago::activos()->get(),
        ]);
    }

    public function cerrarSalida(SalidaRequest $request, MovimientoAcceso $acceso)
    {
        $this->authorize('accesos.registrar');

        if ($acceso->tipo !== 'ENTRADA' || $acceso->salida()->exists()) {
            return redirect()->route('accesos.show', $acceso)
                ->with('error', 'Ese movimiento ya está cerrado.');
        }

        $datos = $request->validated();

        if (($datos['estado_cobro'] ?? null) === 'COBRADO') {
            $this->authorize('accesos.cobrar');
        }

        [$salida, $cobro] = $this->cerrarSalidaInterna($acceso, $datos);

        $mensaje = "Salida registrada para {$acceso->vehiculo?->placas}: "
            . $salida->permanencia . ' de permanencia.';

        if ($cobro > 0) {
            $mensaje .= $salida->estado_cobro === 'COBRADO'
                ? ' Cobrado $' . number_format($cobro, 2) . '.'
                : ' Por cobrar $' . number_format($cobro, 2) . '.';
        }

        return redirect()->route('accesos.show', $acceso)->with('success', $mensaje);
    }

    /* ---------------------------------------------------------------- *
     * US-0503 — QR del contrato
     * ---------------------------------------------------------------- */

    public function qr()
    {
        $this->authorize('accesos.ver');

        return view('accesos.qr', [
            'contratos' => ContratoPension::with('vehiculo', 'cliente')
                ->where('estado', 'ACTIVA')
                ->orderBy('numero_contrato')
                ->get(),
        ]);
    }

    /* ---------------------------------------------------------------- *
     * US-0504 — RFID (mock: el lector "entra" el UID)
     * ---------------------------------------------------------------- */

    public function rfid()
    {
        $this->authorize('accesos.ver');

        return view('accesos.rfid', [
            'vehiculos' => Vehiculo::with('cliente', 'entradaAbierta')
                ->where('activo', true)
                ->orderBy('placas')
                ->get(),
            'movimientos' => MovimientoAcceso::with(['vehiculo', 'usuario'])
                ->orderByDesc('fecha_hora')->limit(10)->get(),
        ]);
    }

    public function leerRfid(Request $request)
    {
        $this->authorize('accesos.registrar');

        $datos = $request->validate([
            'uid' => ['required', 'string', 'max:40'],
        ], ['uid.required' => 'Escribe o captura el UID de la tarjeta.'], ['uid' => 'UID']);

        $vehiculo = Vehiculo::where('uid_rfid', trim($datos['uid']))->where('activo', true)->first();

        if (! $vehiculo) {
            return back()->withInput()->withErrors([
                'uid' => 'No hay ninguna tarjeta activa registrada con ese UID.',
            ]);
        }

        return $this->conmutar($vehiculo, 'RFID');
    }

    /* ---------------------------------------------------------------- *
     * US-0505 — LPR (mock: se captura la placa que "leyó" la cámara)
     * ---------------------------------------------------------------- */

    public function lpr()
    {
        $this->authorize('accesos.ver');

        return view('accesos.lpr', [
            'vehiculos' => Vehiculo::where('activo', true)->orderBy('placas')->get(),
            'movimientos' => MovimientoAcceso::with(['vehiculo', 'usuario'])
                ->orderByDesc('fecha_hora')->limit(10)->get(),
        ]);
    }

    public function leerLpr(Request $request)
    {
        $this->authorize('accesos.registrar');

        $datos = $request->validate([
            'placas' => ['required', 'string', 'max:20'],
            'foto'   => ['nullable', 'string', 'max:500'],
        ], [
            'placas.required' => 'Captura las placas que detectó la cámara.',
        ], ['placas' => 'placas', 'foto' => 'foto']);

        $placas = strtoupper(trim($datos['placas']));
        $vehiculo = Vehiculo::whereRaw('UPPER(placas) = ?', [$placas])
            ->where('activo', true)->first();

        if (! $vehiculo) {
            return back()->withInput()->withErrors([
                'placas' => "La placa {$placas} no está registrada en el sistema.",
            ]);
        }

        return $this->conmutar($vehiculo, 'LPR', [
            'foto_entrada' => trim((string) ($datos['foto'] ?? '')) ?: null,
        ]);
    }

    /* ---------------------------------------------------------------- *
     * US-0506 — dispositivos y barreras (mock de hardware)
     * ---------------------------------------------------------------- */

    public function dispositivos()
    {
        $this->authorize('accesos.configurar');

        return view('accesos.dispositivos', [
            'dispositivos' => Dispositivo::orderBy('tipo')->orderBy('nombre')->get(),
            'puertas'      => Puerta::with('dispositivo')->orderBy('id')->get(),
        ]);
    }

    public function barrera(Request $request)
    {
        $this->authorize('accesos.configurar');

        $datos = $request->validate([
            'dispositivo_id' => ['required', 'integer', Rule::exists('dispositivos', 'id')->where('tipo', 'BARRERA')],
            'accion'         => ['required', 'in:ABRIR,CERRAR'],
        ], [
            'dispositivo_id.required' => 'Selecciona la barrera.',
            'dispositivo_id.exists'   => 'Ese dispositivo no es una barrera.',
            'accion.required'         => 'Indica la acción.',
            'accion.in'               => 'La acción solo puede ser abrir o cerrar.',
        ], ['dispositivo_id' => 'barrera', 'accion' => 'acción']);

        $barrera = Dispositivo::findOrFail($datos['dispositivo_id']);

        $barrera->update([
            'configuracion'   => ['barrera' => $datos['accion'] === 'ABRIR' ? 'ABIERTA' : 'CERRADA'],
            'ultimo_contacto' => now(),
        ]);

        return back()->with(
            'success',
            "Barrera «{$barrera->nombre}» " . strtolower($datos['accion'] === 'ABRIR' ? 'abierta' : 'cerrada') . '.'
        );
    }

    /* ---------------------------------------------------------------- *
     * Detalle (US-0501 / US-0507)
     * ---------------------------------------------------------------- */

    public function show(MovimientoAcceso $acceso)
    {
        $this->authorize('accesos.ver');

        $acceso->load([
            'vehiculo.tipoVehiculo', 'cliente', 'contrato', 'espacio',
            'usuario', 'puerta', 'tarifa', 'metodoPago',
            'entrada.vehiculo', 'salida',
        ]);

        return view('accesos.show', ['acceso' => $acceso]);
    }

    /* ================================================================ *
     * Núcleo compartido
     * ================================================================ */

    /**
     * Entrada (manual, QR, RFID o LPR). Si el cajón estaba DISPONIBLE pasa a
     * OCUPADO; si es el cajón asignado al contrato se usa sin mover el estado.
     */
    private function crearEntrada(Vehiculo $vehiculo, ?ContratoPension $contrato, array $datos): MovimientoAcceso
    {
        return DB::transaction(function () use ($vehiculo, $contrato, $datos) {
            $espacio = null;

            if (! empty($datos['espacio_id'])) {
                $espacio = Espacio::lockForUpdate()->findOrFail($datos['espacio_id']);

                if ($espacio->estado === 'DISPONIBLE') {
                    $espacio->update(['estado' => 'OCUPADO']);
                }
            }

            return MovimientoAcceso::create([
                'vehiculo_id'          => $vehiculo->id,
                'cliente_id'           => $vehiculo->cliente_id ?? $contrato?->cliente_id,
                'contrato_pension_id'  => $contrato?->id,
                'espacio_id'           => $espacio?->id,
                'tipo'                 => 'ENTRADA',
                'fecha_hora'           => Carbon::parse($datos['fecha_hora']),
                'placas_detectadas'    => $vehiculo->placas,
                'usuario_id'           => Auth::id(),
                'puerta_id'            => $datos['puerta_id'] ?? null,
                'metodo_acceso'        => $datos['metodo_acceso'],
                'foto_entrada'         => $datos['foto_entrada'] ?? null,
                'observaciones'        => $datos['observaciones'] ?? null,
                'estado_cobro'         => 'NO_APLICA',
                'cobro'                => 0,
            ]);
        });
    }

    /**
     * Crea la fila SALIDA, calcula permanencia y cobro, y libera el cajón.
     * Devuelve [salida, cobro].
     */
    private function cerrarSalidaInterna(MovimientoAcceso $entrada, array $datos): array
    {
        return DB::transaction(function () use ($entrada, $datos) {
            $salidaFecha = Carbon::parse($datos['fecha_hora']);
            $minutos     = max(0, (int) $entrada->fecha_hora->diffInMinutes($salidaFecha));

            $contrato  = $this->contratoActivo($entrada->vehiculo);
            $visitante = $contrato === null;

            $cobro     = 0.0;
            $tarifaId  = null;
            $estado    = 'NO_APLICA';
            $metodoId  = null;

            if ($visitante) {
                $estado = $datos['estado_cobro'] ?? 'PENDIENTE';
                $tarifa = ! empty($datos['tarifa_id'])
                    ? Tarifa::find($datos['tarifa_id'])
                    : $this->tarifaSugerida($entrada->vehiculo);

                if ($estado === 'NO_APLICA') {
                    $cobro = 0.0;
                    $tarifaId = null;
                } elseif ($tarifa) {
                    $cobro    = $this->calcularCobro($minutos, $tarifa);
                    $tarifaId = $tarifa->id;
                    $metodoId = $estado === 'COBRADO' ? ($datos['metodo_pago_id'] ?? null) : null;
                    if ($estado === 'COBRADO' && ! $metodoId) {
                        throw ValidationException::withMessages([
                            'metodo_pago_id' => 'Selecciona con qué método se cobró.',
                        ]);
                    }
                } else {
                    $estado = 'PENDIENTE';
                }
            }

            $salida = MovimientoAcceso::create([
                'entrada_id'           => $entrada->id,
                'vehiculo_id'          => $entrada->vehiculo_id,
                'cliente_id'           => $entrada->cliente_id,
                'contrato_pension_id'  => $entrada->contrato_pension_id,
                'espacio_id'           => $entrada->espacio_id,
                'tipo'                 => 'SALIDA',
                'fecha_hora'           => $salidaFecha,
                'placas_detectadas'    => $entrada->placas_detectadas,
                'usuario_id'           => Auth::id(),
                'puerta_id'            => $datos['puerta_id'] ?? null,
                'metodo_acceso'        => $datos['metodo_acceso'] ?? 'MANUAL',
                'permanencia_minutos'  => $minutos,
                'tarifa_id'            => $tarifaId,
                'cobro'                => $cobro,
                'estado_cobro'         => $estado,
                'metodo_pago_id'       => $metodoId,
                'foto_salida'          => $datos['foto_salida'] ?? null,
                'observaciones'        => $datos['observaciones'] ?? null,
            ]);

            $this->liberarCajon($entrada);

            return [$salida, $cobro];
        });
    }

    /**
     * RFID/LPR: si el vehículo está fuera entra, si está adentro sale.
     * Es el comportamiento de un torniquete real (mock del sprint).
     */
    private function conmutar(Vehiculo $vehiculo, string $metodo, array $extra = [])
    {
        $abierta = MovimientoAcceso::abiertas()
            ->where('vehiculo_id', $vehiculo->id)
            ->orderByDesc('fecha_hora')
            ->first();

        if ($abierta) {
            $contrato = $this->contratoActivo($vehiculo);

            [$salida, $cobro] = $this->cerrarSalidaInterna($abierta, [
                'fecha_hora'     => now(),
                'metodo_acceso'  => $metodo,
                'estado_cobro'   => $contrato ? 'NO_APLICA' : 'PENDIENTE',
                'puerta_id'      => Puerta::activas()->where('tipo', 'SALIDA')->value('id'),
                'foto_salida'    => $extra['foto_salida'] ?? $extra['foto_entrada'] ?? null,
            ]);

            $mensaje = "Salida {$metodo} de {$vehiculo->placas}: {$salida->permanencia}.";
            if ($cobro > 0) {
                $mensaje .= ' Por cobrar $' . number_format($cobro, 2) . '.';
            }

            return redirect()->route('accesos.show', $abierta)->with('success', $mensaje);
        }

        $entrada = $this->crearEntrada($vehiculo, $this->contratoActivo($vehiculo), [
            'fecha_hora'     => now(),
            'metodo_acceso'  => $metodo,
            'puerta_id'      => Puerta::activas()->where('tipo', 'ENTRADA')->value('id'),
            'foto_entrada'   => $extra['foto_entrada'] ?? null,
        ]);

        return redirect()->route('accesos.show', $entrada)->with(
            'success',
            "Entrada {$metodo} de {$vehiculo->placas} a las {$entrada->fecha_hora->format('H:i')} h."
        );
    }

    /** Contrato ACTIVA vigente (fecha_inicio ≤ hoy ≤ fecha_fin). */
    private function contratoActivo(Vehiculo $vehiculo): ?ContratoPension
    {
        return $vehiculo->contratos()
            ->where('estado', 'ACTIVA')
            ->whereDate('fecha_inicio', '<=', now())
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', now()))
            ->first();
    }

    /** Tarifa por defecto para visitantes: HORA del tipo, si no, cualquier HORA vigente. */
    private function tarifaSugerida(Vehiculo $vehiculo): ?Tarifa
    {
        $base = Tarifa::vigente()->whereIn('tipo_tarifa', ['HORA', 'DIA', 'NOCHE'])
            ->orderByRaw("FIELD(tipo_tarifa, 'HORA', 'DIA', 'NOCHE')");

        return (clone $base)->where('tipo_vehiculo_id', $vehiculo->tipo_vehiculo_id)->first()
            ?? (clone $base)->first();
    }

    /** Tarifas de permanencia vigentes para el <select> de la salida. */
    private function tarifasPermanencia(Vehiculo $vehiculo): \Illuminate\Support\Collection
    {
        return Tarifa::vigente()
            ->whereIn('tipo_tarifa', ['HORA', 'DIA', 'NOCHE'])
            ->with('tipoVehiculo')
            ->orderByRaw("FIELD(tipo_tarifa, 'HORA', 'DIA', 'NOCHE')")
            ->orderBy('tipo_vehiculo_id')
            ->get()
            ->sortBy(fn ($t) => $t->tipo_vehiculo_id === $vehiculo->tipo_vehiculo_id ? 0 : 1)
            ->values();
    }

    /**
     * Bloques de cobro: 60 min (HORA), 720 min (NOCHE = 12 h), 1440 min (DIA).
     * Fracción incompleta se cobra completa, mínimo 1 unidad.
     */
    private function calcularCobro(int $minutos, Tarifa $tarifa): float
    {
        $bloque = ['HORA' => 60, 'NOCHE' => 720, 'DIA' => 1440][$tarifa->tipo_tarifa] ?? 60;

        $unidades = max(1, (int) ceil($minutos / $bloque));

        return round($unidades * (float) $tarifa->precio, 2);
    }

    /** Devuelve el cajón a DISPONIBLE si nadie más lo tiene tomado. */
    private function liberarCajon(MovimientoAcceso $entrada): void
    {
        if (! $entrada->espacio_id) {
            return;
        }

        $espacio = Espacio::lockForUpdate()->find($entrada->espacio_id);

        if (! $espacio || $espacio->estado !== 'OCUPADO') {
            return;
        }

        $asignado = $espacio->asignaciones()->whereNull('fecha_fin')->exists();
        $otroAdentro = MovimientoAcceso::abiertas()
            ->where('espacio_id', $espacio->id)
            ->where('id', '!=', $entrada->id)
            ->exists();

        if (! $asignado && ! $otroAdentro) {
            $espacio->update(['estado' => 'DISPONIBLE']);
        }
    }

    /** Datos comunes del formulario de entrada. */
    private function formEntrada(array $prefill = []): array
    {
        return [
            'vehiculos' => Vehiculo::with('cliente', 'tipoVehiculo')
                ->where('activo', true)
                ->orderBy('placas')
                ->get(),
            'espacios' => Espacio::where('activo', true)
                ->whereIn('estado', ['DISPONIBLE'])
                ->orderBy('codigo')
                ->get(),
            // Cajones tomados por una pensión vigente: se ofrecen aunque estén
            // OCUPADOS (la validación exige que sean del contrato del vehículo).
            'espaciosContrato' => Espacio::whereHas('asignaciones', function ($q) {
                $q->whereNull('fecha_fin')->whereHas('contrato', function ($qq) {
                    $qq->where('estado', 'ACTIVA');
                });
            })->with('zona')->orderBy('codigo')->get(),
            'puertas' => Puerta::activas()->where('tipo', 'ENTRADA')->get(),
            'prefill' => $prefill,
        ];
    }
}
```

---

## FASE 6 - Vistas (`resources/views/accesos/`)

Ocho vistas nuevas. Todas usan el layout `layouts.app`, `partials.alerts` (SweetAlert)
y el mismo estilo de tablas/filtros que `pagos/index.blade.php`.

### 6.1 `resources/views/accesos/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Accesos (entradas y salidas)')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Accesos <span class="fs-6 text-muted">(entradas y salidas)</span></h4>
    <div class="d-flex gap-2">
        @can('accesos.configurar')
            <a href="{{ route('accesos.dispositivos') }}" class="btn btn-outline-secondary">Dispositivos</a>
        @endcan
        @can('accesos.ver')
            <a href="{{ route('accesos.qr') }}" class="btn btn-outline-secondary">QR</a>
            <a href="{{ route('accesos.rfid') }}" class="btn btn-outline-secondary">RFID</a>
            <a href="{{ route('accesos.lpr') }}" class="btn btn-outline-secondary">LPR</a>
        @endcan
        @can('accesos.registrar')
            <a href="{{ route('accesos.create') }}" class="btn btn-primary">+ Nueva entrada</a>
        @endcan
    </div>
</div>

{{-- US-0507: resumen con los mismos filtros --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card shadow-sm text-center"><div class="card-body py-3">
            <div class="fs-4 fw-bold text-info">{{ $resumen['adentro'] }}</div>
            <small class="text-muted">Vehículos adentro ahora</small>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center"><div class="card-body py-3">
            <div class="fs-4 fw-bold">{{ $resumen['entradas'] }}</div>
            <small class="text-muted">Entradas de hoy (en el filtro)</small>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center"><div class="card-body py-3">
            <div class="fs-4 fw-bold">{{ $resumen['salidas'] }}</div>
            <small class="text-muted">Salidas de hoy (en el filtro)</small>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center"><div class="card-body py-3">
            <div class="fs-4 fw-bold text-warning">${{ number_format((float) $resumen['porCobrar'], 2) }}</div>
            <small class="text-muted">Cobros de visita pendientes</small>
        </div></div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" value="{{ $filtros['q'] }}" class="form-control"
               placeholder="Buscar placas, vehículo o cliente">
    </div>
    <div class="col-md-2">
        <select name="tipo" class="form-select" aria-label="Tipo">
            <option value="">Entradas y salidas</option>
            @foreach (\App\Models\MovimientoAcceso::TIPOS as $t)
                <option value="{{ $t }}" @selected($filtros['tipo'] === $t)>
                    {{ \App\Models\MovimientoAcceso::TIPO_LABELS[$t] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="metodo" class="form-select" aria-label="Método de acceso">
            <option value="">Todos los métodos</option>
            @foreach (\App\Models\MovimientoAcceso::METODOS as $m)
                <option value="{{ $m }}" @selected($filtros['metodo'] === $m)>
                    {{ \App\Models\MovimientoAcceso::METODO_LABELS[$m] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="estado_cobro" class="form-select" aria-label="Estado del cobro">
            <option value="">Todo cobro</option>
            @foreach (\App\Models\MovimientoAcceso::ESTADOS_COBRO as $e)
                <option value="{{ $e }}" @selected($filtros['estado_cobro'] === $e)>
                    {{ \App\Models\MovimientoAcceso::ESTADO_COBRO_LABELS[$e] }}
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
            <a href="{{ route('accesos.index') }}" class="btn btn-outline-light">Limpiar</a>
        @endif
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Fecha y hora</th><th>Tipo</th><th>Vehículo</th><th>Cliente</th>
                <th>Cajón</th><th>Método</th><th>Puerta</th><th>Permanencia</th>
                <th class="text-end">Cobro</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($movimientos as $m)
                <tr class="{{ $m->abierta ? 'table-warning' : '' }}">
                    <td class="text-nowrap">{{ $m->fecha_hora->format('d/m/Y H:i') }}</td>
                    <td>
                        <span class="badge {{ $m->tipo === 'ENTRADA' ? 'bg-primary' : 'bg-dark' }}">
                            {{ $m->tipo_label }}
                        </span>
                    </td>
                    <td>
                        <strong>{{ $m->vehiculo?->placas ?? $m->placas_detectadas ?? '—' }}</strong><br>
                        <small class="text-muted">
                            {{ trim(($m->vehiculo?->marca . ' ' . $m->vehiculo?->modelo) ?: '—') }}
                        </small>
                    </td>
                    <td>{{ $m->cliente?->nombre_completo ?? '—' }}</td>
                    <td>{{ $m->espacio?->codigo ?? '—' }}</td>
                    <td class="small">{{ $m->metodo_label }}</td>
                    <td class="small">{{ $m->puerta?->nombre ?? '—' }}</td>
                    <td class="text-nowrap">
                        @if ($m->tipo === 'ENTRADA')
                            @if ($m->salida)
                                {{ $m->salida->permanencia }}
                            @elseif ($m->abierta)
                                <span class="badge bg-info text-dark">Adentro</span>
                            @else
                                —
                            @endif
                        @else
                            {{ $m->permanencia ?? '—' }}
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if ($m->estado_cobro === 'NO_APLICA' || $m->tipo === 'ENTRADA')
                            —
                        @else
                            <span class="badge {{ $m->estado_cobro_clase }}">
                                {{ $m->estado_cobro_label }}
                            </span>
                            ${{ number_format((float) $m->cobro, 2) }}
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if ($m->abierta && \Illuminate\Support\Facades\Gate::allows('accesos.registrar'))
                            <a href="{{ route('accesos.salida', $m) }}" class="btn btn-sm btn-warning">
                                Registrar salida
                            </a>
                        @endif
                        @can('accesos.ver')
                            <a href="{{ route('accesos.show', $m) }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        No hay movimientos{{ $tieneFiltro ? ' que coincidan con el filtro' : ' registrados' }}.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $movimientos->links() }}</div>
@endsection
```

### 6.2 `resources/views/accesos/create.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Nueva entrada')

@section('content')
<h4 class="mb-3">Registrar entrada</h4>

@if ($prefill['contrato'])
    <div class="alert alert-info">
        <strong>QR leído:</strong> contrato {{ $prefill['contrato']->numero_contrato }}
        ({{ $prefill['contrato']->cliente?->nombre_completo }}) — vehículo
        {{ $prefill['contrato']->vehiculo?->placas }}.
        Método de acceso: <strong>Código QR</strong>.
    </div>
@endif

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('accesos.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="vehiculo_id" class="form-label">Vehículo <span class="text-danger">*</span></label>
                        <select name="vehiculo_id" id="vehiculo_id" class="form-select @error('vehiculo_id') is-invalid @enderror" required>
                            <option value="">— Selecciona un vehículo —</option>
                            @foreach ($vehiculos as $v)
                                <option value="{{ $v->id }}" @selected((string) old('vehiculo_id', $prefill['vehiculo_id']) === (string) $v->id)>
                                    {{ $v->placas }} · {{ $v->marca }} {{ $v->modelo }} ·
                                    {{ $v->cliente?->nombre_completo }}
                                </option>
                            @endforeach
                        </select>
                        @error('vehiculo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_hora" class="form-label">Fecha y hora <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_hora" id="fecha_hora"
                                   value="{{ old('fecha_hora', $prefill['fecha_hora']) }}"
                                   class="form-control @error('fecha_hora') is-invalid @enderror" required>
                            @error('fecha_hora') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="metodo_acceso" class="form-label">Método de acceso <span class="text-danger">*</span></label>
                            <select name="metodo_acceso" id="metodo_acceso" class="form-select @error('metodo_acceso') is-invalid @enderror" required>
                                @foreach (\App\Models\MovimientoAcceso::METODOS as $m)
                                    <option value="{{ $m }}" @selected(old('metodo_acceso', $prefill['metodo_acceso']) === $m)>
                                        {{ \App\Models\MovimientoAcceso::METODO_LABELS[$m] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('metodo_acceso') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="espacio_id" class="form-label">Cajón</label>
                            <select name="espacio_id" id="espacio_id" class="form-select @error('espacio_id') is-invalid @enderror">
                                <option value="">Sin cajón (solo tránsito)</option>
                                @foreach ($espaciosContrato as $e)
                                    <option value="{{ $e->id }}"
                                        @selected((string) old('espacio_id') === (string) $e->id)>
                                        {{ $e->codigo }} — cajón de la pensión ({{ $e->zona?->nombre }})
                                    </option>
                                @endforeach
                                @foreach ($espacios as $e)
                                    <option value="{{ $e->id }}" @selected((string) old('espacio_id') === (string) $e->id)>
                                        {{ $e->codigo }} · {{ $e->zona?->nombre }} · {{ $e->estado_label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('espacio_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Los cajones ocupados/reservados solo se eligen si son el de la pensión.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="puerta_id" class="form-label">Puerta / caseta</label>
                            <select name="puerta_id" id="puerta_id" class="form-select @error('puerta_id') is-invalid @enderror">
                                <option value="">— Sin registro de caseta —</option>
                                @foreach ($puertas as $p)
                                    <option value="{{ $p->id }}" @selected((string) old('puerta_id') === (string) $p->id)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                            @error('puerta_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="foto_entrada" class="form-label">Foto de evidencia (URL)</label>
                        <input type="url" name="foto_entrada" id="foto_entrada" maxlength="500"
                               value="{{ old('foto_entrada') }}" placeholder="https://…"
                               class="form-control @error('foto_entrada') is-invalid @enderror">
                        @error('foto_entrada') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" rows="2" maxlength="1000"
                                  class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones') }}</textarea>
                        @error('observaciones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Registrar entrada</button>
                        <a href="{{ route('accesos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
```

### 6.3 `resources/views/accesos/salida.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Registrar salida')

@section('content')
<h4 class="mb-3">Registrar salida</h4>

<div class="row justify-content-center">
    <div class="col-lg-9">
        {{-- Ficha de la entrada que se cierra --}}
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">
                            {{ $acceso->vehiculo?->placas }}
                            <span class="badge bg-primary">Entrada</span>
                        </h5>
                        <div class="text-muted">
                            {{ trim(($acceso->vehiculo?->marca . ' ' . $acceso->vehiculo?->modelo) ?: '') }}
                            · {{ $acceso->cliente?->nombre_completo ?? 'Sin cliente' }}
                        </div>
                        <div class="small text-muted">
                            Entró {{ $acceso->fecha_hora->format('d/m/Y H:i') }}
                            · {{ $acceso->metodo_label }}
                            · Cajón {{ $acceso->espacio?->codigo ?? '—' }}
                            · Registró {{ $acceso->usuario?->name ?? '—' }}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-5 fw-bold" id="permanenciaVivo">
                            {{ (int) \Illuminate\Support\Carbon::parse($acceso->fecha_hora)->diffInMinutes(now()) }} min
                        </div>
                        <small class="text-muted">Permanencia al abrir esta página</small>
                    </div>
                </div>
            </div>
        </div>

        @if ($contrato)
            <div class="alert alert-success">
                <strong>Pensión activa ({{ $contrato->numero_contrato }}):</strong>
                la salida no genera cobro, el cliente ya paga la mensualidad.
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('accesos.salida.store', $acceso) }}" id="salidaForm">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_hora" class="form-label">Fecha y hora de salida <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_hora" id="fecha_hora"
                                   value="{{ old('fecha_hora', now()->format('Y-m-d\TH:i')) }}"
                                   class="form-control @error('fecha_hora') is-invalid @enderror"
                                   data-entrada="{{ $acceso->fecha_hora->format('Y-m-d H:i:s') }}" required>
                            @error('fecha_hora') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="puerta_id" class="form-label">Puerta / caseta</label>
                            <select name="puerta_id" id="puerta_id" class="form-select @error('puerta_id') is-invalid @enderror">
                                <option value="">— Sin registro de caseta —</option>
                                @foreach ($puertas as $p)
                                    <option value="{{ $p->id }}" @selected((string) old('puerta_id') === (string) $p->id)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                            @error('puerta_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if ($contrato)
                        <input type="hidden" name="estado_cobro" value="NO_APLICA">
                    @else
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tarifa_id" class="form-label">Tarifa de permanencia <span class="text-danger">*</span></label>
                                <select name="tarifa_id" id="tarifa_id" class="form-select @error('tarifa_id') is-invalid @enderror">
                                    @foreach ($tarifas as $t)
                                        <option value="{{ $t->id }}"
                                            data-precio="{{ $t->precio }}"
                                            data-bloque="{{ ['HORA' => 60, 'NOCHE' => 720, 'DIA' => 1440][$t->tipo_tarifa] ?? 60 }}"
                                            @selected((string) old('tarifa_id', $tarifaSugerida?->id) === (string) $t->id)>
                                            {{ $t->etiqueta }}{{ $t->id === $tarifaSugerida?->id ? ' (sugerida)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('tarifa_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado del cobro <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3 pt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="estado_cobro"
                                               id="cob_pendiente" value="PENDIENTE"
                                               @checked(old('estado_cobro', 'PENDIENTE') === 'PENDIENTE')>
                                        <label class="form-check-label" for="cob_pendiente">Dejar por cobrar</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="estado_cobro"
                                               id="cob_cobrado" value="COBRADO"
                                               @checked(old('estado_cobro') === 'COBRADO')>
                                        <label class="form-check-label" for="cob_cobrado">Cobrar ahora</label>
                                    </div>
                                </div>
                                @error('estado_cobro') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3" id="bloqueMetodo" hidden>
                            <label for="metodo_pago_id" class="form-label">Método de cobro <span class="text-danger">*</span></label>
                            <select name="metodo_pago_id" id="metodo_pago_id" class="form-select @error('metodo_pago_id') is-invalid @enderror">
                                <option value="">— Selecciona —</option>
                                @foreach ($metodos as $m)
                                    <option value="{{ $m->id }}" @selected((string) old('metodo_pago_id') === (string) $m->id)>{{ $m->nombre }}</option>
                                @endforeach
                            </select>
                            @error('metodo_pago_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="alert alert-light border mb-3">
                            <span class="text-muted">Permanencia:</span>
                            <strong id="minVivo">{{ $permanenciaMin }} min</strong>
                            <span class="text-muted ms-3">Cobro estimado:</span>
                            <strong id="estimado">$0.00</strong>
                            <span class="text-muted ms-3 d-none d-sm-inline">El servidor recalcula el importe definitivo.</span>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="foto_salida" class="form-label">Foto de evidencia (URL)</label>
                        <input type="url" name="foto_salida" id="foto_salida" maxlength="500"
                               value="{{ old('foto_salida') }}" placeholder="https://…"
                               class="form-control @error('foto_salida') is-invalid @enderror">
                        @error('foto_salida') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" rows="2" maxlength="1000"
                                  class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones') }}</textarea>
                        @error('observaciones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-warning">Registrar salida</button>
                        <a href="{{ route('accesos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('salidaForm');
    const entrada = document.querySelector('[name=fecha_hora]');
    const tarifa  = document.getElementById('tarifa_id');
    const minTxt  = document.getElementById('minVivo');
    const estTxt  = document.getElementById('estimado');
    const bloque  = document.getElementById('bloqueMetodo');
    if (!form || !entrada) return;

    function minutos() {
        const fin = entrada.value ? new Date(entrada.value.replace('T', ' ')) : null;
        const ini = new Date(entrada.dataset.entrada.replace(' ', ' '));
        if (!fin) return 0;
        return Math.max(0, Math.round((fin - ini) / 60000));
    }

    function pintar() {
        const min = minutos();
        if (minTxt) minTxt.textContent = min + ' min';
        if (tarifa && estTxt) {
            const opt  = tarifa.selectedOptions[0];
            const pre  = parseFloat(opt?.dataset.precio || 0);
            const blq  = parseInt(opt?.dataset.bloque || 60, 10);
            const und  = Math.max(1, Math.ceil(min / (blq || 60)));
            estTxt.textContent = '$' + (und * pre).toFixed(2);
        }
    }

    function pintarMetodo() {
        if (!bloque) return;
        const cobrado = document.querySelector('input[name=estado_cobro]:checked')?.value === 'COBRADO';
        bloque.hidden = !cobrado;
        document.getElementById('metodo_pago_id').required = cobrado;
    }

    form.addEventListener('change', pintar);
    form.addEventListener('input', pintar);
    document.querySelectorAll('input[name=estado_cobro]')
        .forEach((r) => r.addEventListener('change', pintarMetodo));

    pintar();
    pintarMetodo();
    setInterval(pintar, 30000);
})();
</script>
@endpush
@endsection
```

> `@push('scripts')` requiere `@stack('scripts')` en el layout: se agrega en la
> **FASE 7** (junto al menú). Sin ese stack el script no se imprime.

### 6.4 `resources/views/accesos/show.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Detalle del acceso')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        Movimiento #{{ $acceso->id }}
        <span class="badge {{ $acceso->tipo === 'ENTRADA' ? 'bg-primary' : 'bg-dark' }}">{{ $acceso->tipo_label }}</span>
        @if ($acceso->abierta)
            <span class="badge bg-info text-dark">Sin salida</span>
        @endif
    </h4>
    <div class="d-flex gap-2">
        @if ($acceso->abierta && \Illuminate\Support\Facades\Gate::allows('accesos.registrar'))
            <a href="{{ route('accesos.salida', $acceso) }}" class="btn btn-warning">Registrar salida</a>
        @endif
        <a href="{{ route('accesos.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Movimiento</strong></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Fecha y hora</span>
                    <strong>{{ $acceso->fecha_hora->format('d/m/Y H:i') }}</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Tipo</span><span>{{ $acceso->tipo_label }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Método de acceso</span><span>{{ $acceso->metodo_label }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Puerta</span><span>{{ $acceso->puerta?->nombre ?? '—' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Cajón</span><span>{{ $acceso->espacio?->codigo ?? '—' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Registró</span><span>{{ $acceso->usuario?->name ?? '—' }}</span>
                </li>
                <li class="list-group-item">
                    <span class="text-muted d-block">Observaciones</span>
                    {{ $acceso->observaciones ?: '—' }}
                </li>
            </ul>
        </div>

        @if ($acceso->foto_entrada || $acceso->foto_salida)
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-light"><strong>Evidencia</strong></div>
                <div class="card-body">
                    @if ($acceso->foto_entrada)
                        <p class="mb-1 small text-muted">Entrada:</p>
                        <img src="{{ $acceso->foto_entrada }}" class="img-fluid rounded mb-3" alt="Evidencia de entrada">
                    @endif
                    @if ($acceso->foto_salida)
                        <p class="mb-1 small text-muted">Salida:</p>
                        <img src="{{ $acceso->foto_salida }}" class="img-fluid rounded" alt="Evidencia de salida">
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Vehículo y cliente</strong></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Placas</span><strong>{{ $acceso->vehiculo?->placas ?? $acceso->placas_detectadas ?? '—' }}</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Vehículo</span>
                    <span>{{ trim(($acceso->vehiculo?->marca . ' ' . $acceso->vehiculo?->modelo . ' ' . $acceso->vehiculo?->anio) ?: '—') }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Cliente</span><span>{{ $acceso->cliente?->nombre_completo ?? '—' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Contrato</span>
                    <span>{{ $acceso->contrato?->numero_contrato ?? 'Visitante (sin pensión)' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Permanencia</span>
                    <strong>{{ $acceso->permanencia ?? $acceso->salida?->permanencia ?? 'En curso…' }}</strong>
                </li>
                @if ($acceso->tipo === 'ENTRADA' && $acceso->salida && $acceso->salida->estado_cobro !== 'NO_APLICA')
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Cobro en la salida</span>
                        <strong>${{ number_format((float) $acceso->salida->cobro, 2) }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Estado del cobro</span>
                        <span class="badge {{ $acceso->salida->estado_cobro_clase }}">{{ $acceso->salida->estado_cobro_label }}</span>
                    </li>
                @endif
                @if ($acceso->tipo === 'SALIDA')
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Tarifa aplicada</span>
                        <span>{{ $acceso->tarifa?->nombre ?? '—' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Cobro</span>
                        <strong>${{ number_format((float) $acceso->cobro, 2) }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Estado del cobro</span>
                        <span class="badge {{ $acceso->estado_cobro_clase }}">{{ $acceso->estado_cobro_label }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Método de cobro</span>
                        <span>{{ $acceso->metodoPago?->nombre ?? '—' }}</span>
                    </li>
                @endif
            </ul>
        </div>

        <div class="d-flex gap-2 mt-3">
            @if ($acceso->entrada)
                <a href="{{ route('accesos.show', $acceso->entrada) }}" class="btn btn-outline-primary btn-sm">
                    Ver entrada #{{ $acceso->entrada->id }}
                </a>
            @endif
            @if ($acceso->salida)
                <a href="{{ route('accesos.show', $acceso->salida) }}" class="btn btn-outline-dark btn-sm">
                    Ver salida #{{ $acceso->salida->id }}
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
```

### 6.5 `resources/views/accesos/rfid.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Acceso con tarjeta RFID')

@section('content')
<h4 class="mb-3">Acceso con tarjeta RFID <span class="fs-6 text-muted">(lector simulado)</span></h4>

<div class="alert alert-secondary">
    En el caso real el lector envía el UID por serial/TCP. Aquí se captura el UID
    (o se pulsa «Leer» en la fila del vehículo): si el vehículo está <strong>fuera</strong>
    registra <strong>entrada</strong>, si está <strong>adentro</strong> registra <strong>salida</strong>.
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Leer tarjeta</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('accesos.rfid.leer') }}" id="formRfid">
                    @csrf
                    <div class="mb-3">
                        <label for="uid" class="form-label">UID de la tarjeta <span class="text-danger">*</span></label>
                        <input type="text" name="uid" id="uid" maxlength="40" required autofocus
                               value="{{ old('uid') }}" placeholder="Ej. A1B2C3D4"
                               class="form-control form-control-lg @error('uid') is-invalid @enderror">
                        @error('uid') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-primary w-100">Leer tarjeta</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-light"><strong>Vehículos con tarjeta</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Placas</th><th>UID</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($vehiculos as $v)
                        <tr>
                            <td><strong>{{ $v->placas }}</strong></td>
                            <td class="small">{{ $v->uid_rfid ?? '—' }}</td>
                            <td>
                                @if ($v->entradaAbierta)
                                    <span class="badge bg-info text-dark">Adentro</span>
                                @else
                                    <span class="badge bg-secondary">Fuera</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($v->uid_rfid && \Illuminate\Support\Facades\Gate::allows('accesos.registrar'))
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-leer="{{ $v->uid_rfid }}">Leer</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Últimos movimientos</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Fecha y hora</th><th>Tipo</th><th>Vehículo</th><th>Método</th><th>Usuario</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($movimientos as $m)
                        <tr>
                            <td>{{ $m->fecha_hora->format('d/m/Y H:i') }}</td>
                            <td><span class="badge {{ $m->tipo === 'ENTRADA' ? 'bg-primary' : 'bg-dark' }}">{{ $m->tipo_label }}</span></td>
                            <td>{{ $m->vehiculo?->placas ?? $m->placas_detectadas ?? '—' }}</td>
                            <td>{{ $m->metodo_label }}</td>
                            <td>{{ $m->usuario?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Todavía no hay movimientos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('[data-leer]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('uid').value = btn.dataset.leer;
            document.getElementById('formRfid').submit();
        });
    });
</script>
@endpush
@endsection
```

### 6.6 `resources/views/accesos/lpr.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Acceso por lectura de placas (LPR)')

@section('content')
<h4 class="mb-3">Lectura de placas (LPR) <span class="fs-6 text-muted">(cámara simulada)</span></h4>

<div class="alert alert-secondary">
    La cámara reconoce la placa y dispara la barrera. Aquí se captura lo que la
    cámara «leyó»: <strong>fuera</strong> → entrada, <strong>adentro</strong> → salida.
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Placa detectada</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('accesos.lpr.leer') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="placas" class="form-label">Placas <span class="text-danger">*</span></label>
                        <input type="text" name="placas" id="placas" maxlength="20" required autofocus
                               value="{{ old('placas') }}" placeholder="Ej. ABC1234"
                               class="form-control form-control-lg text-uppercase @error('placas') is-invalid @enderror">
                        @error('placas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="foto" class="form-label">URL de la foto del censo</label>
                        <input type="url" name="foto" id="foto" maxlength="500"
                               value="{{ old('foto') }}" placeholder="https://…"
                               class="form-control @error('foto') is-invalid @enderror">
                        @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-primary w-100">Procesar lectura</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-light"><strong>Placas registradas</strong></div>
            <ul class="list-group list-group-flush">
                @foreach ($vehiculos as $v)
                    <li class="list-group-item d-flex justify-content-between">
                        <span><strong>{{ $v->placas }}</strong>
                            <small class="text-muted">— {{ $v->marca }} {{ $v->modelo }}</small></span>
                        @if ($v->entradaAbierta)
                            <span class="badge bg-info text-dark">Adentro</span>
                        @else
                            <span class="badge bg-secondary">Fuera</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Últimos movimientos</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Fecha y hora</th><th>Tipo</th><th>Vehículo</th><th>Método</th><th>Usuario</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($movimientos as $m)
                        <tr>
                            <td>{{ $m->fecha_hora->format('d/m/Y H:i') }}</td>
                            <td><span class="badge {{ $m->tipo === 'ENTRADA' ? 'bg-primary' : 'bg-dark' }}">{{ $m->tipo_label }}</span></td>
                            <td>{{ $m->vehiculo?->placas ?? $m->placas_detectadas ?? '—' }}</td>
                            <td>{{ $m->metodo_label }}</td>
                            <td>{{ $m->usuario?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Todavía no hay movimientos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
```

### 6.7 `resources/views/accesos/qr.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Código QR de acceso')

@section('content')
<h4 class="mb-3">Código QR de acceso <span class="fs-6 text-muted">(US-0503)</span></h4>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Contrato activo</strong></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="contrato" class="form-label">Pensión</label>
                    <select id="contrato" class="form-select">
                        <option value="">— Selecciona —</option>
                        @foreach ($contratos as $c)
                            <option value="{{ $c->numero_contrato }}">
                                {{ $c->numero_contrato }} · {{ $c->cliente?->nombre_completo }} ·
                                {{ $c->vehiculo?->placas }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Al escanearlo se abre la alta de entrada con el vehículo y el
                        método QR precargados.
                    </div>
                </div>

                <div id="qrBox" class="text-center border rounded p-3 bg-white"
                     style="min-height: 220px;">
                    <span class="text-muted">Selecciona un contrato para generar el código.</span>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
                    <a href="{{ route('accesos.index') }}" class="btn btn-outline-secondary">Volver</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="alert alert-light border">
            <strong>Qué hace el QR:</strong>
            <ul class="mb-0">
                <li>El cliente lo lleva impreso o en el celular.</li>
                <li>La caseta lo escanea → <code>/accesos/create?qr=PEN-2026-0002</code>.</li>
                <li>El formulario trae vehículo y método <strong>QR</strong> precargados
                    (el operador solo confirma con «Registrar entrada»).</li>
            </ul>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const select = document.getElementById('contrato');
        const caja   = document.getElementById('qrBox');

        select.addEventListener('change', () => {
            const numero = select.value;
            if (!numero) {
                caja.innerHTML = '<span class="text-muted">Selecciona un contrato para generar el código.</span>';
                return;
            }
            const url = location.origin + '/accesos/create?qr=' + encodeURIComponent(numero);
            caja.innerHTML = '<div class="d-flex flex-column align-items-center gap-2">'
                + '<div class="qr-render" data-qr="' + url.replace(/"/g, '&quot;') + '"></div>'
                + '<small class="text-muted">' + numero + '</small></div>';
            if (window.renderQr) {
                window.renderQr(caja.querySelector('.qr-render'));
            }
        });
    })();
</script>
@endpush
@endsection
```

### 6.8 `resources/views/accesos/dispositivos.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Dispositivos y barreras')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Dispositivos y barreras <span class="fs-6 text-muted">(US-0506)</span></h4>
    <a href="{{ route('accesos.index') }}" class="btn btn-outline-secondary">Volver</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Hardware instalado</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Dispositivo</th><th>Tipo</th><th>Ubicación</th><th>Estado</th><th>Último contacto</th><th></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($dispositivos as $d)
                        <tr>
                            <td>
                                <strong>{{ $d->nombre }}</strong><br>
                                <small class="text-muted">{{ $d->marca }} {{ $d->modelo }}</small>
                            </td>
                            <td>{{ $d->tipo_label }}</td>
                            <td class="small">{{ $d->ubicacion ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $d->estado === 'ACTIVO' ? 'bg-success' : ($d->estado === 'FALLA' ? 'bg-danger' : 'bg-secondary') }}">
                                    {{ $d->estado }}
                                </span>
                            </td>
                            <td class="small">{{ $d->ultimo_contacto?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                @if ($d->esBarrera())
                                    <span class="badge {{ $d->barrera === 'ABIERTA' ? 'bg-success' : 'bg-dark' }}">
                                        {{ $d->barrera }}
                                    </span>
                                    <form method="POST" action="{{ route('accesos.barrera') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="dispositivo_id" value="{{ $d->id }}">
                                        <button name="accion" value="ABRIR" class="btn btn-sm btn-outline-success">Abrir</button>
                                        <button name="accion" value="CERRAR" class="btn btn-sm btn-outline-dark">Cerrar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><strong>Puertas (casetas)</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Puerta</th><th>Tipo</th><th>Barrera</th><th>Activa</th></tr></thead>
                    <tbody>
                    @foreach ($puertas as $p)
                        <tr>
                            <td>
                                <strong>{{ $p->nombre }}</strong><br>
                                <small class="text-muted">{{ $p->ubicacion }}</small>
                            </td>
                            <td>{{ $p->tipo }}</td>
                            <td class="small">{{ $p->dispositivo?->nombre ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $p->activo ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $p->activo ? 'Sí' : 'No' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
```

---

## FASE 7 - Rutas, menú, dashboard y JavaScript

### 7.1 `routes/web.php` - 13 rutas nuevas

**Fragmento: añadir** el import arriba:

```php
use App\Http\Controllers\AccesoController;
```

**Fragmento: añadir** al final del grupo `auth` (después de las rutas de pagos):

```php
    // Sprint 5 — Módulos 5 y 9 (accesos)
    // ¡Misma lección que pagos/corte! Las rutas estáticas van ANTES del
    // resource: si no, accesos/{acceso} se comería «qr», «rfid», etc.
    Route::get('accesos/qr', [AccesoController::class, 'qr'])->name('accesos.qr');
    Route::get('accesos/rfid', [AccesoController::class, 'rfid'])->name('accesos.rfid');
    Route::post('accesos/rfid', [AccesoController::class, 'leerRfid'])->name('accesos.rfid.leer');
    Route::get('accesos/lpr', [AccesoController::class, 'lpr'])->name('accesos.lpr');
    Route::post('accesos/lpr', [AccesoController::class, 'leerLpr'])->name('accesos.lpr.leer');
    Route::post('accesos/barrera', [AccesoController::class, 'barrera'])->name('accesos.barrera');
    Route::get('accesos/dispositivos', [AccesoController::class, 'dispositivos'])->name('accesos.dispositivos');
    Route::get('accesos/{acceso}/salida', [AccesoController::class, 'salida'])->name('accesos.salida');
    Route::post('accesos/{acceso}/salida', [AccesoController::class, 'cerrarSalida'])->name('accesos.salida.store');
    Route::resource('accesos', AccesoController::class)->only(['index', 'create', 'store', 'show']);
```

Recuento: 79 rutas previas + 9 manuales + 4 del `resource` = **92 rutas**.

Verificación:

```powershell
C:\php\php.exe artisan route:list --json | ConvertFrom-Json |
    Where-Object { $_.uri -like 'accesos*' } |
    Select-Object method, uri, name | Format-Table -AutoSize
```

### 7.2 `resources/views/layouts/app.blade.php` - menú y stack de scripts

**Fragmento: reemplazar** la línea del menú deshabilitado (línea 94):

```blade
            @can('accesos.ver')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('accesos.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                       href="{{ route('accesos.index') }}">Accesos</a>
                </li>
            @endcan
```

**Fragmento: añadir** justo antes de `</body>` (línea 127) para que funcionen
los `@push('scripts')` de las vistas RFID/LPR/QR/salida:

```blade
@stack('scripts')
</body>
```

### 7.3 `app/Http/Controllers/HomeController.php`

**Fragmento: reemplazar** `index()`:

```php
    public function index()
    {
        // Solo quien puede ver contratos recibe la alerta (US-0307).
        $porVencer = Gate::allows('contratos.ver')
            ? ContratoPension::porVencer()->with('cliente')->get()
            : collect();

        // US-0501: vehículos con entrada abierta (Sprint 5).
        $adentro = Gate::allows('accesos.ver')
            ? MovimientoAcceso::abiertas()->count()
            : null;

        return view('home', compact('porVencer', 'adentro'));
    }
```

Import: `use App\Models\MovimientoAcceso;`.

### 7.4 `resources/views/home.blade.php` - tarjeta «Vehículos dentro»

**Fragmento: reemplazar** la primera tarjeta (líneas 8–14):

```blade
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                <h6 class="text-muted">Vehículos dentro</h6>
                <h2 class="mb-0">{{ $adentro ?? '—' }}</h2>
                <small class="text-muted">
                    {{ $adentro === null ? 'Sin permiso accesos.ver' : 'Entradas sin salida' }}
                </small>
            </div></div>
        </div>
```

### 7.5 `resources/js/app.js` - QR

**Archivo completo** (agrega `qrcode-generator` y el helper `renderQr`):

```js
import './bootstrap';
import Swal from 'sweetalert2';
import qrcode from 'qrcode-generator';

window.Swal = Swal;

/**
 * Dibuja el QR del `data-qr` dentro del elemento.
 * Si la librería no está instalada o el texto es demasiado largo para el
 * tipo de versión elegido, deja el texto plano (la vista sigue siendo usable).
 */
window.renderQr = function (el) {
    if (!el) return;

    const texto = el.dataset.qr || '';
    if (!texto) return;

    const dibujar = (tipo) => {
        const qr = qrcode(tipo, 'M');
        qr.addData(texto);
        qr.make();
        return qr.createSvgTag(4, 2);
    };

    try {
        el.innerHTML = dibujar(0);   // 0 = autodetección de versión
    } catch (e1) {
        try {
            el.innerHTML = dibujar(10); // versión fija (≈174 bytes en nivel M)
        } catch (e2) {
            el.textContent = texto;     // degradación: URL visible
        }
    }
};
```

### 7.6 Instalar la librería y compilar

```powershell
npm install qrcode-generator
npm run build
```

Esperado: `qrcode-generator@2.0.4` en `package.json` y el build con
`app-*.css` / `app-*.js` nuevos en `public/build`. La API de esa versión es
`qrcode(tipo, 'M')` + `createSvgTag(ancho, margen)` con **export por defecto**
(`import qrcode from 'qrcode-generator'`).

> **Sin red / npm falla:** la vista QR degrada sola (el `catch` deja la URL en
> texto plano). Continúa con el resto del sprint y anota la desviación en el
> Apéndice B.

### 7.7 Sintaxis

```powershell
Get-ChildItem app\Http\Controllers\AccesoController.php,
              app\Http\Requests\EntradaRequest.php,
              app\Http\Requests\SalidaRequest.php,
              app\Models\MovimientoAcceso.php,
              app\Models\Puerta.php,
              app\Models\Dispositivo.php,
              app\Models\Vehiculo.php,
              app\Models\ContratoPension.php,
              database\seeders\DispositivoSeeder.php,
              database\seeders\PuertaSeeder.php,
              database\seeders\RolPermisoSeeder.php,
              database\seeders\DatabaseSeeder.php,
              app\Http\Controllers\HomeController.php,
              database\migrations\2026_10_08_220000_add_cobro_to_movimientos_acceso_table.php,
              database\migrations\2026_10_08_220100_add_uid_rfid_to_vehiculos_table.php |
    ForEach-Object { php -l $_.FullName }
```

Esperado: **`No syntax errors detected`** en los 15 archivos.

---

## FASE 8 - Verificación

### 8.1 Checklist funcional (usuario `admin@pension.mx` / `password`)

> Servidor: `C:\php\php.exe artisan serve --port=8002` → `http://127.0.0.1:8002`
> (el puerto 8000 está ocupado por otra aplicación). Las respuestas `200/403`
> se pueden comprobar con `fetch(..., {headers:{'Accept':'application/json'}})`
> (con `redirect:'manual'` el fetch devuelve status 0).

**US-0501 — Entrada manual**

- [x] Menú «Accesos» visible y cliclable para admin/seguridad/recepción/cajero/supervisor.
- [x] `GET /accesos` → 200 con 4 tarjetas (Adentro / Entradas hoy / Salidas hoy / Por cobrar).
- [x] `GET /accesos/create` → 200; combos: vehículos activos, cajones DISPONIBLE
      + cajón de la pensión, puertas ENTRADA.
- [x] Registrar entrada de `ABC1234` (pensión) con cajón `A-01` (zona 1) → redirige
      a `/accesos/{id}` con «Entrada registrada…»; el cajón pasa a `OCUPADO`.
- [x] Volver a registrar una entrada del mismo vehículo → error
      «ya está adentro (entrada …)».
- [x] Intentar entrar con cajón `A-02` (RESERVADO) → «El cajón A-02 está reservado: elige otro.»
- [x] Quitar el cajón («Sin cajón») y registrar → movimiento sin `espacio_id` y sin
      cambios en los estados.
- [x] Ver el movimiento → ficha con placa, cliente, contrato, método, puerta, usuario.

**US-0502 — Salida, permanencia y cobro**

- [x] `GET /accesos/{id}/salida` de la entrada de `ABC1234` → aviso verde
      «Pensión activa… no genera cobro» y campo `estado_cobro` oculto en `NO_APLICA`.
- [x] Registrar la salida → «Salida registrada… X min de permanencia» (sin «Por cobrar»).
- [x] Ver la salida → `Estado del cobro: Sin cobro`, `Tarifa: —`, `Cobro: $0.00`.
- [x] El cajón `A-01` (zona 1) vuelve a `DISPONIBLE` (al estar asignado a la pensión
      se conserva `OCUPADO` por la asignación vigente: verificar con
      `Espacio::where('codigo','A-01')->first()->estado`).
- [x] Entrada de un vehículo **sin contrato** (o con contrato vencido/cancelado):
      la salida muestra tarifas, tarifa sugerida `HORA` y «Cobro estimado».
- [x] 90 minutos con tarifa HORA $25 → estimado `$50.00` en pantalla y
      `$50.00` guardado (bloques de 60 min, fracción completa).
- [x] Cambiar a `NOCHE` $120 (720 min) con 90 min → `$120.00`.
- [x] Salida con «Dejar por cobrar» → `estado_cobro = PENDIENTE`, `metodo_pago_id = null`.
- [x] Salida con «Cobrar ahora» sin método → 422 «Selecciona con qué método se cobró.»
- [x] Salida con «Cobrar ahora» + Efectivo → `COBRADO`, sin fila nueva en `pagos`
      (`SELECT COUNT(*) FROM pagos` sigue en 3).
- [x] Hora de salida anterior a la entrada → error «La salida no puede ser anterior…».
- [x] Hora futura → «La fecha y hora de salida no puede ser futura.»
- [x] El listado muestra `Adentro` en la entrada abierta y la permanencia en la salida.

**US-0503 — QR**

- [x] `GET /accesos/qr` → 200 con el selector de pensiones activas.
- [x] Elegir `PEN-2026-0002` → el QR se dibuja (`svg` dentro de `#qrBox`).
- [x] Abrir `http://127.0.0.1:8002/accesos/create?qr=PEN-2026-0002` → alerta
      «QR leído: contrato PEN-2026-0002…», vehículo y método **QR** precargados.
- [x] `?qr=PEN-9999-9999` → «El código QR no corresponde a una pensión activa.»

**US-0504 — RFID (mock)**

- [x] `GET /accesos/rfid` → 200 con UID del vehículo y botón «Leer».
- [x] Con `ABC1234` fuera: «Leer» → **entrada** RFID; el listado lo marca «Adentro».
- [x] Pulsar «Leer» otra vez → **salida** RFID con permanencia.
- [x] UID desconocido (`ZZZZ`) → «No hay ninguna tarjeta activa registrada con ese UID.»

**US-0505 — LPR (mock)**

- [x] `GET /accesos/lpr` → 200 con placas registradas.
- [x] Placa `ABC1234` + URL de foto → **entrada** LPR; la salida LPR respeta la foto
      (`foto_salida`).
- [x] Placa `QQQQ999` → «La placa QQQQ999 no está registrada en el sistema.»

**US-0506 — Barreras (mock)**

- [x] `GET /accesos/dispositivos` → 200 con 7 dispositivos y 2 puertas.
- [x] «Abrir» en *Barrera entrada* → `ABIERTA`, `ultimo_contacto` actualizado, flash
      «Barrera «Barrera entrada» abierta.»
- [x] «Cerrar» → `CERRADA`.
- [x] Verificar en BD:
      `Dispositivo::where('tipo','BARRERA')->get(['nombre','configuracion'])`.

**US-0507 — Auditoría**

- [x] Filtros: `tipo=SALIDA`, `metodo=RFID`, `desde/hasta` y `estado_cobro=PENDIENTE`
      reducen la tabla; «Limpiar» restaura.
- [x] Búsqueda por placas o nombre de cliente.
- [x] Paginación (15 por página) conserva los filtros (`withQueryString`).
- [x] Dashboard: «Vehículos dentro» refleja las entradas abiertas (y `—` para quien
      no tiene `accesos.ver`).

### 8.2 Matriz de permisos (las 13 rutas)

Códigos: `200` = OK · `403` = rechazado · `302/login` = sin sesión · `422` =
validación (solo aplica al reenviar un formulario).

| Ruta | ADMIN | SEGURIDAD | RECEPCION | CAJERO | SUPERVISOR | INVITADO |
|---|---|---|---|---|---|---|
| `GET /accesos` | 200 | 200 | 200 | 200 | 200 | login |
| `GET /accesos/create` | 200 | 200 | 200 | **403** | **403** | login |
| `POST /accesos` | 200 | 200 | 200 | **403** | **403** | login |
| `GET /accesos/{id}` | 200 | 200 | 200 | 200 | 200 | login |
| `GET /accesos/{id}/salida` | 200 | 200 | 200 | **403** | **403** | login |
| `POST /accesos/{id}/salida` | 200 | 200 | 200 | **403** | **403** | login |
| `GET /accesos/qr` | 200 | 200 | 200 | 200 | 200 | login |
| `GET /accesos/rfid` | 200 | 200 | 200 | 200 | 200 | login |
| `POST /accesos/rfid` | 200 | 200 | 200 | **403** | **403** | login |
| `GET /accesos/lpr` | 200 | 200 | 200 | 200 | 200 | login |
| `POST /accesos/lpr` | 200 | 200 | 200 | **403** | **403** | login |
| `GET /accesos/dispositivos` | 200 | **403** | **403** | **403** | **403** | login |
| `POST /accesos/barrera` | 200 | **403** | **403** | **403** | **403** | login |

Además, dentro de `POST /accesos/{id}/salida`: un **CAJERO** con `accesos.cobrar`
pero sin `accesos.registrar` queda fuera por la ruta (403); quien sí llega y
elige «Cobrar ahora» necesita `accesos.cobrar` (SEGURADMIN/SEGURIDAD/CAJERO la
tienen; con `accesos.registrar` sin `accesos.cobrar` → 403).

Comprobación por usuario (Playwright o `fetch` con cookies de sesión):

```js
// Abrir sesión con cada usuario y pedir la ruta con Accept: application/json
await fetch('/accesos/dispositivos', { headers: { 'Accept': 'application/json' } });
// → 200 con admin, 403 con seguridad
```

### 8.3 Comprobaciones en base de datos

```powershell
php artisan tinker
>>> \App\Models\MovimientoAcceso::count()
>>> \App\Models\MovimientoAcceso::abiertas()->count()
>>> \App\Models\MovimientoAcceso::where('tipo','SALIDA')->get(['id','entrada_id','permanencia_minutos','cobro','estado_cobro','metodo_pago_id'])
>>> DB::table('dispositivos')->count()   // 7
>>> DB::table('puertas')->count()        // 2
>>> DB::table('permissions')->count()    // 40
>>> DB::table('pagos')->count()          // 3 (el cobro de visita NO crea pagos)
```

### 8.4 Errores nuevos en el log

```powershell
Select-String -Path storage\logs\laravel.log -Pattern 'ERROR|CRITICAL' -Context 0,3 |
    Select-Object -Last 10
```

Esperado: sin entradas nuevas desde el arranque del sprint.

---

## Apéndice A - Fuentes

| Fuente | Qué se tomó |
|---|---|
| `PLAN-SCRUM.md` §7 (Sprint 5) | US-0501…US-0507 (63 pts), tablas `movimientos_acceso`, `puertas`, `dispositivos`, alcance con mocks para RFID/LPR/barrera, compromiso de 40 pts. |
| `PLAN-SCRUM.md` §8 (riesgos) | Permisos semeados desde el Sprint 0 y guardia en cada módulo. |
| `MIGRACIONES.md` §5.6, §5.10, §5.11, §5.14, §5.15 | Esquema de `dispositivos`, `tarifas`, `puertas`, `pagos`, `movimientos_acceso`. |
| `GUIA-SPRINT4-PAGOS-Y-CAJA.md` Apéndice C | Nota de continuidad: la **permanencia → cobro por hora** se atiende aquí y se enlaza con `pagos` en Sprint 8. |
| `GUIA-SPRINT3-TARIFAS-Y-PENSIONES.md` | `Tarifa::scopeVigente()`, `esVigente()`, `etiqueta`, `ContratoPension::ESTADOS`. |
| `app/Models/Espacio.php`, `ContratoPension.php` | Estados de cajón, `asignacionVigiente()`, regla de una asignación vigente por cajón. |
| Código de referencia (estilo) | `PagoController`, `PagoRequest`, `pagos/index.blade.php`, `layouts/app.blade.php`. |

## Apéndice B - Riesgos y desviaciones previstas

| # | Riesgo | Impacto | Mitigation |
|---|---|---|---|
| B1 | `npm install qrcode-generator` falla (sin red). | US-0503 sin QR gráfico. | El `renderQr()` degrada a URL en texto; la ruta QR y la precarga siguen funcionando. Registrar en Apéndice D. |
| B2 | Librería npm con API distinta a la esperada (`createSvgTag`). | QR no se dibuja. | `try/catch` con versión fija y luego texto plano; probar en la verificación US-0503. |
| B3 | `FIELD(tipo_tarifa, …)` es de MySQL. | Fallaría si la BD se cambia de motor. | El proyecto está en MySQL (Sprint 0); si se migra, sustituir por `orderByRaw("CASE tipo_tarifa WHEN … END")`. |
| B4 | Dos entradas simultáneas del mismo vehículo (doble click). | Doble movimiento abierto. | El closure de `EntradaRequest` vuelve a consultar al guardar; el `DB::transaction` + índice por `vehiculo_id` contienen el caso. |
| B5 | Suelta el cajón mientras otra entrada abierta lo usa. | Estado `DISPONIBLE` falso. | `liberarCajon()` comprueba asignación vigente **y** otras entradas abiertas antes de liberar. |
| B6 | El cobro de visita no tiene fila en `pagos` (`contrato_pension_id NOT NULL`). | Reportes de caja no lo ven. | A propósito (decisión 6): se cuenta en el Sprint 8 desde `movimientos_acceso.estado_cobro = COBRADO`. |
| B7 | `lockForUpdate()` en tablas con pocas filas. | Bloqueo innecesario. | Solo se usa al entrar/salir (pocos cientos de filas); aceptable en este volumen. |
| B8 | Reloj del servidor distinto al del navegador. | Validación de fechas «futuras». | Tolerancia de 5 minutos en `EntradaRequest` y `SalidaRequest`. |

## Apéndice C - Alcance siguiente (Sprint 6)

Según `PLAN-SCRUM.md`: **Sprint 6 — Incidencias y documentos del vehículo
(Módulo 10)**: registro de daños/golpes con fotos y quién los reportó,
historial por vehículo y su incidencia en la salida (se leerá
`movimientos_acceso` para saber el estado del vehículo al entrar y al salir).

Puente preparado en este sprint:

- `movimientos_acceso.foto_entrada` / `foto_salida` ya guardan URLs de evidencia.
- `Vehiculo.entradaAbierta()` permite saber en qué momento estaba el vehículo.
- `accesos.ver` seguirá siendo el permiso para ver el expediente de accesos del
  vehículo (no tocar el módulo de permisos de Sprint 7).

## Apéndice D - Registro de ejecución

**Estado del Sprint 5:** ⬜ Pendiente · 🟡 En curso · **✅ Completado**

**Fecha de ejecución:** 09/10/2026  **Ejecutó:** desarrollo (verificación en navegador con Playwright)

### D.1 Comandos ejecutados

| # | Comandos (tal cual se corrieron) | ✅/❌ | Notas |
|---|---|---|---|
| 1 | `php artisan migrate` | ✅ | 2 migraciones nuevas (`…_add_cobro_to_movimientos_acceso_table`, `…_add_uid_rfid_to_vehiculos_table`); 27 en total. |
| 2 | `php artisan db:seed --class=DispositivoSeeder` | ✅ | 7 equipos (2 cámaras, 2 lectores RFID/QR, 1 LPR, 2 barreras). |
| 3 | `php artisan db:seed --class=PuertaSeeder` | ✅ | 2 puertas (Entrada/Salida principal) con su barrera. |
| 4 | `php artisan db:seed --class=RolPermisoSeeder` | ✅ | 40 permisos: `accesos.ver/registrar/cobrar/configurar` repartidos (ADMIN 4, SEGURIDAD 3, RECEPCION 3, CAJERO 2, SUPERVISOR 1). |
| 5 | `php artisan route:list --json` (con `C:\php\php.exe`) | ✅ | 92 rutas (13 de `accesos.*`). |
| 6 | `Get-ChildItem … \| ForEach { C:\php\php.exe -l $_ }` | ✅ | 15/15 OK (0 fallos). |
| 7 | `npm install qrcode-generator` | ✅ | `qrcode-generator@2.0.4` en `package.json`. |
| 8 | `npm run build` | ✅ | `public/build/assets/app-Ce7hIDs1.js` (+css). |
| 9 | Servidor `C:\php\php.exe artisan serve --port=8002` | ✅ | `http://127.0.0.1:8002` (el 8000 está ocupado). |
| 10 | `config/app.php` → `'timezone' => 'America/Mexico_City'` | ✅ | Ver decisión 16 y desviación D.5-1: `now()` y el reloj del navegador quedaron sincronizados (11:03). |
| 11 | `php artisan tinker --execute=…` + scripts PHP temporales (`_tmp_*.php`, borrados) | ✅ | Los scripts `tinker` de una línea fallan con `?->`/comillas; se usaron archivos temporales. |

### D.2 Historias de usuario

| ID | ¿Entregado? | Evidencia / pantalla |
|---|---|---|
| US-0501 | ✅ | `GET /accesos/create` con el cajón de la pensión en el combo; entrada manual `#1` de `ABC1234` con cajón `A-01` → `/accesos/1`; 422 «ya está adentro» y 422 «El cajón A-02 está reservado: elige otro.»; entrada sin cajón OK. |
| US-0502 | ✅ | Pensionado: `/accesos/1/salida` con aviso verde y `estado_cobro` oculto `NO_APLICA` → salida `#4` (2 min, $0.00). Visitante 90 min → salida `#3` `COBRADO $50.00` (HORA); NOCHE 30 min → `#6` `PENDIENTE $120.00`; LPR de visitante → `#44` `PENDIENTE $25.00`; UI de visitante con tarifa sugerida y «Cobro estimado» (90 min → $50.00, NOCHE → $120.00). 422 sin método, 422 futura, 422 anterior. `pagos` sigue en 3. |
| US-0503 | ✅ | `GET /accesos/qr` dibuja el SVG (136 px) de `PEN-2026-0002`; `/accesos/create?qr=PEN-2026-0002` precarga alerta + vehículo + método **QR**; `?qr=PEN-9999-9999` → «El código QR no corresponde a una pensión activa.» |
| US-0504 | ✅ | `GET /accesos/rfid` con UID y «Leer»: entrada `#7` y salida `#8` con `A1B2C3D4` (método RFID, puertas 1 y 2); UID `ZZZZ` → «No hay ninguna tarjeta activa registrada con ese UID.»; la lista marca «Adentro». |
| US-0505 | ✅ | `GET /accesos/lpr`: entrada `#43` con `foto_entrada` y salida `#44` con `foto_salida`; placa `QQQQ999` → «La placa QQQQ999 no está registrada en el sistema.» |
| US-0506 | ✅ | `GET /accesos/dispositivos` con 7 dispositivos y 2 puertas; barrera entrada `CERRADA → ABIERTA → CERRADA` con `ultimo_contacto` actualizado. |
| US-0507 | ✅ | Filtros `tipo/metodo/estado_cobro/desde+hasta/q` (individual y combinados), estado vacío «No hay movimientos que coincidan con el filtro.», «Limpiar», paginación 15/página que conserva filtros, y tarjeta «Vehículos dentro» del dashboard 0 → 1 → 0. La rama «sin permiso» (`—` / «Sin permiso accesos.ver») se verificó en código: los 5 roles tienen `accesos.ver`, así que no se pudo ejercitar en navegador. |

> La evidencia es por URL (decisión 7: sin `storage/`), no por capturas.

### D.3 Checklist §8.1

| Bloque | Total | ✅ |
|---|---|---|
| US-0501 | 8 | 8 |
| US-0502 | 13 | 13 |
| US-0503 | 4 | 4 |
| US-0504 | 4 | 4 |
| US-0505 | 3 | 3 |
| US-0506 | 4 | 4 |
| US-0507 | 4 | 4 |
| **Total** | **40** | **40** |

> El total de la guía original (39) no cuadraba: US-0502 tiene 13 puntos, no 14,
> y US-0504/US-0506 tienen 4, no 3. Recuento hecho sobre los encabezados.

### D.4 Matriz de permisos (resultado real)

| Ruta | ADMIN | SEGURIDAD | RECEPCION | CAJERO | SUPERVISOR | INVITADO |
|---|---|---|---|---|---|---|
| `GET /accesos` | 200 | 200 | 200 | 200 | 200 | login |
| `GET /accesos/create` | 200 | 200 | 200 | **403** | **403** | login |
| `POST /accesos` | 200 | 200 | 200 | **403** | **403** | login |
| `GET /accesos/{id}` | 200 | 200 | 200 | 200 | 200 | login |
| `GET /accesos/{id}/salida` | 200 | 200 | 200 | **403** | **403** | login |
| `POST /accesos/{id}/salida` | 302 | 302 | 302 | **403** | **403** | login |
| `GET /accesos/qr` | 200 | 200 | 200 | 200 | 200 | login |
| `GET /accesos/rfid` | 200 | 200 | 200 | 200 | 200 | login |
| `POST /accesos/rfid` | 422 | 422 | 422 | **403** | **403** | login |
| `GET /accesos/lpr` | 200 | 200 | 200 | 200 | 200 | login |
| `POST /accesos/lpr` | 422 | 422 | 422 | **403** | **403** | login |
| `GET /accesos/dispositivos` | 200 | **403** | **403** | **403** | **403** | login |
| `POST /accesos/barrera` | 422 | **403** | **403** | **403** | **403** | login |

Notas de lectura:

- **`login`** = sin sesión: `302 → /login` con `Accept: text/html` y `401` con
  `Accept: application/json` (mismo bloqueo, dos formatos).
- **`422`** en los POST con `FormRequest` (`store`, `salida`) y en `rfid`/`lpr`/
  `barrera` con cuerpo vacío: la validación corre antes que el `authorize` del
  controlador, así que un 422 **sí** significa «llegó» y un 403 «no pudo
  llegar». Para `POST /accesos` y `POST /accesos/{id}/salida` se repitió la
  prueba con **payload válido**: 200 (crea) / 302 (cierra) para los autorizados
  y 403 para CAJERO/SUPERVISOR, sin dejar datos de prueba (se cerraron/borraron).
- La matriz coincide con la esperada del §8.2 en las 13 rutas × 6 usuarios.

Se creó el usuario de demostración `recepcion@pension.mx` (rol RECEPCION) para
poder llenar esa columna: no existía usuario con ese rol (desviación D.5-6).

### D.5 Desviaciones detectadas

| # | Desviación de la guía | Cómo se resolvió |
|---|---|---|
| 1 | La app estaba en `'timezone' => 'UTC'` y el equipo en hora de México: `now()` marcaba 6 h más que los `datetime-local` del navegador. | `config/app.php` → `'America/Mexico_City'` (decisión 16). Se borraron las 2 entradas de prueba y se rehizo toda la verificación con horas coherentes. |
| 2 | `formEntrada()` pasaba `espacioContrato` (singular, dependía del vehículo preseleccionado): el cajón de la pensión **nunca** aparecía en el combo. | Se cambió a `espaciosContrato` (colección de cajones con asignación vigente, etiquetados con su zona) y el combo hace `@foreach`. Guía FASE 5 y FASE 6.2 actualizadas. |
| 3 | La guía esperaba `qrcode-generator@1.4.4`. | Instaló `2.0.4` (API: `qrcode(tipo, 'M')` + `createSvgTag` con export por defecto). Verificado dibujando el SVG; actualizado en §7.6. |
| 4 | La salida LPR **perdía la foto**: `conmutar()` solo leía `extra['foto_salida']` y `leerLpr()` envía `foto_entrada`. | Fallback en `conmutar()`: `$extra['foto_salida'] ?? $extra['foto_entrada'] ?? null`. Verificado de nuevo: entrada `#43` con foto y salida `#44` con foto. |
| 5 | La ficha de una ENTRADA cerrada mostraba «Permanencia: En curso…» y ocultaba el cobro del visitante. | `show.blade.php`: `permanencia ?? salida?->permanencia ?? 'En curso…'` + bloque «Cobro en la salida / Estado». |
| 6 | No existía usuario con rol RECEPCION para la columna de la matriz. | Creado `recepcion@pension.mx` / `password` con rol RECEPCION (mismo patrón que el resto de usuarios demo). |
| 7 | Hacía falta un vehículo **sin contrato** para verificar los cobros y no había. | Creado el vehículo visitante `XYZ9999` (Nissan Versa, cliente 1, tipo 1, `uid_rfid = E5F6G7H8`). Por eso el caso «placa no registrada» de US-0505 usa `QQQQ999`. |
| 8 | `tinker` rechaza scripts de una línea con `?->`, `foreach` y ciertas comillas. | Scripts PHP temporales en la raíz (`_tmp_*.php`) con `bootstrap/app.php`, borrados al terminar. |
| 9 | 3 líneas `ERROR` nuevas en `storage/logs/laravel.log` del día (11:12, 11:14, 11:21): una columna inexistente (`registrado_por`) y dos parseos de `tinker`, todos de mis propios scripts de prueba. | Ninguna viene del código del sprint; los scripts se corrigieron/borraron. Los errores de la fecha 08/10 son anteriores al sprint. |

### D.6 Datos creados en este sprint

| Dato | Cantidad esperada | Real |
|---|---|---|
| `dispositivos` | 7 | **7** |
| `puertas` | 2 | **2** |
| `permissions` | 40 | **40** |
| `rutas` | 92 | **92** |
| `movimientos_acceso` (pruebas) | al menos 2 (1 pareja ENTRADA+SALIDA) | **14** (7 parejas), **0 abiertas** |
| `uid_rfid` de `ABC1234` | `A1B2C3D4` | `A1B2C3D4` (y `XYZ9999` = `E5F6G7H8`) |
| `pagos` | **3** (sin cambios) | **3** |
| `users` | 4 | **5** (`recepcion@pension.mx` nuevo, D.5-6) |
| Saldo «Por cobrar» | — | **$145.00** (dos salidas `PENDIENTE`: $120.00 NOCHE + $25.00 LPR) |

Detalle de los movimientos al cierre (fecha local 09/10/2026):

| # | Tipo | Placas | Hora | Método | Permanencia | Cobro |
|---|---|---|---|---|---|---|
| 1 | ENTRADA | ABC1234 | 11:03 | MANUAL | — | — |
| 2 | ENTRADA | XYZ9999 | 09:34 | MANUAL | — | — |
| 3 | SALIDA | XYZ9999 | 11:04 | MANUAL | 1 h 30 min | **$50.00 COBRADO** (HORA ×2) |
| 4 | SALIDA | ABC1234 | 11:05 | MANUAL | 2 min | $0.00 NO_APLICA |
| 5 | ENTRADA | XYZ9999 | 10:36 | MANUAL | — | — |
| 6 | SALIDA | XYZ9999 | 11:06 | MANUAL | 30 min | **$120.00 PENDIENTE** (NOCHE) |
| 7 | ENTRADA | ABC1234 | 11:07 | RFID | — | — |
| 8 | SALIDA | ABC1234 | 11:09 | RFID | 1 min | $0.00 NO_APLICA |
| 9 | ENTRADA | ABC1234 | 11:10 | LPR | — | — |
| 10 | SALIDA | ABC1234 | 11:10 | LPR | 0 min | $0.00 NO_APLICA |
| 43 | ENTRADA | XYZ9999 | 11:24 | LPR (foto entrada) | — | — |
| 44 | SALIDA | XYZ9999 | 11:24 | LPR (foto salida) | 0 min | **$25.00 PENDIENTE** (mín. 1 unidad) |
| 45 | ENTRADA | XYZ9999 | 11:25 | MANUAL | — | — |
| 46 | SALIDA | XYZ9999 | 11:26 | MANUAL | 1 min | **$25.00 COBRADO** (Efectivo) |

### D.7 Cierre

- [x] Los 15 archivos pasan `php -l`.
- [x] `npm run build` sin errores.
- [x] 92 rutas y 40 permisos.
- [x] Checklist §8.1 completo (40/40 puntos).
- [x] Matriz §8.2 coincide con la tabla esperada (ver D.4).
- [x] Sin errores nuevos de la aplicación en `storage/logs/laravel.log`
      (las 3 entradas `ERROR` del 09/10 son de scripts de prueba propios, ver D.5-9).
- [x] Este Apéndice D rellenado y **Estado del Sprint 5 = ✅ Completado**.
