# Migraciones — Sistema de Pensión Vehicular

Documento generado a partir de `BD-Sistema-Pension-Vehicular.xlsx` (hojas: Esquema, Tablas, Campos, Relaciones).

- **Framework:** Laravel 10 · PHP 8.1
- **Base de datos:** MySQL (`pensiones`)
- **Paquete de roles/permisos:** `spatie/laravel-permission ^6.25`

---

## 1. Decisiones de diseño

| Tema | Decisión |
|---|---|
| Usuarios | Se conserva la tabla **`users`** de Laravel. Se agregan las columnas extra del Excel (`telefono`, `activo`, `ultimo_acceso`). `password_hash` → `password`, `nombre` → `name`. |
| Roles / permisos | **No** se crean `roles`, `permisos`, `usuario_roles`, `rol_permisos` del Excel. Se usa Spatie (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) + 1 migración `ALTER` que agrega `roles.descripcion` y `permissions.modulo` / `permissions.accion`. |
| Alcance | Las 19 tablas restantes (MVP + Fase 2) en un solo lote, ordenadas por dependencias de FK. |
| ENUM | `$table->enum()` de MySQL con los valores exactos del Excel. |
| Timestamps | El Excel omite `created_at/updated_at` en algunas tablas y solo `created_at` en `movimientos_acceso`, `bitacora` e `incidencia_archivos`. Se agrega `timestamps()` completo en todas (requerido por Eloquent). Única desviación respecto al Excel. |

### 1.1 Cobertura de las 24 tablas del Excel

| Tabla en el Excel | Origen |
|---|---|
| `roles`, `permisos`, `usuario_roles`, `rol_permisos` | Spatie (ya migrado) |
| `usuarios` | `users` de Laravel (editada) |
| Las 19 restantes | **Migraciones nuevas de este documento** |

### 1.2 Reglas generales

**Mapeo de tipos**

| Excel | Laravel / MySQL |
|---|---|
| `VARCHAR(n)` | `string(n)` |
| `TEXT`, `JSON/TEXT` | `text()`, `json()` |
| `DECIMAL(12,2)` | `decimal('campo', 12, 2)` |
| `BOOLEAN` | `boolean()` |
| `DATETIME` | `dateTime()` |
| `DATE` | `date()` |
| `TIME` | `time()` |
| `SMALLINT` | `smallInteger()` |
| `BIGINT` (FK) | `foreignId(...)->constrained()` |

**Comportamiento de las FK**

| Tipo de relación | Regla |
|---|---|
| Catálogos / entidades de negocio referenciadas | `constrained()` → `RESTRICT ON DELETE` (default) |
| Registros hijos puros (`asignaciones_espacio`, `incidencia_archivos`, `vehiculo_documentos`) | `->cascadeOnDelete()` |
| FK opcionales (`contrato_pension_id`, `puerta_id`, `espacio_id`, `dispositivo_id`) | `->nullable()->constrained()->nullOnDelete()` |
| `bitacora.usuario_id` | `->nullable()->constrained()->nullOnDelete()` (no perder auditoría) |

**Borrado lógico:** no se usa `softDeletes`; se usa la columna `activo` del Excel.

---

## 2. Prerrequisito antes de ejecutar

La extensión `pdo_mysql` no está habilitada y hoy `php artisan migrate` falla con `could not find driver`.

```ini
; C:\php\php.ini  (línea ~945) — quitar el punto y coma
extension=pdo_mysql
```

```powershell
php -m | Select-String pdo_mysql   # debe aparecer
# Crear la BD si no existe
mysql -u root -e "CREATE DATABASE IF NOT EXISTS pensiones CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

## 3. Índice de archivos

| # | Archivo | Tabla | Depende de |
|---|---|---|---|
| 0 | `2014_10_12_000000_create_users_table.php` **(editar)** | `users` | — |
| 0 | `2026_10_06_200523_create_permission_tables.php` **(no tocar)** | Spatie | — |
| 1 | `2026_10_06_210000_add_extra_columns_to_permission_tables.php` | ALTER Spatie | Spatie |
| 2 | `2026_10_06_210100_create_tipos_vehiculo_table.php` | `tipos_vehiculo` | — |
| 3 | `2026_10_06_210200_create_zonas_table.php` | `zonas` | — |
| 4 | `2026_10_06_210300_create_metodos_pago_table.php` | `metodos_pago` | — |
| 5 | `2026_10_06_210400_create_tipos_incidencia_table.php` | `tipos_incidencia` | — |
| 6 | `2026_10_06_210500_create_dispositivos_table.php` | `dispositivos` | — |
| 7 | `2026_10_06_210600_create_clientes_table.php` | `clientes` | — |
| 8 | `2026_10_06_210700_create_vehiculos_table.php` | `vehiculos` | clientes, tipos_vehiculo |
| 9 | `2026_10_06_210800_create_espacios_table.php` | `espacios` | zonas, tipos_vehiculo |
| 10 | `2026_10_06_210900_create_tarifas_table.php` | `tarifas` | tipos_vehiculo |
| 11 | `2026_10_06_211000_create_puertas_table.php` | `puertas` | dispositivos |
| 12 | `2026_10_06_211100_create_contratos_pension_table.php` | `contratos_pension` | clientes, vehiculos, tarifas |
| 13 | `2026_10_06_211200_create_asignaciones_espacio_table.php` | `asignaciones_espacio` | contratos_pension, espacios |
| 14 | `2026_10_06_211300_create_pagos_table.php` | `pagos` | contratos_pension, clientes, metodos_pago, users |
| 15 | `2026_10_06_211400_create_movimientos_acceso_table.php` | `movimientos_acceso` | vehiculos, clientes, contratos, espacios, users, puertas |
| 16 | `2026_10_06_211500_create_incidencias_table.php` | `incidencias` | vehiculos, clientes, contratos, tipos_incidencia, users |
| 17 | `2026_10_06_211600_create_incidencia_archivos_table.php` | `incidencia_archivos` | incidencias |
| 18 | `2026_10_06_211700_create_reservaciones_table.php` | `reservaciones` | clientes, vehiculos, espacios, users |
| 19 | `2026_10_06_211800_create_vehiculo_documentos_table.php` | `vehiculo_documentos` | vehiculos |
| 20 | `2026_10_06_211900_create_bitacora_table.php` | `bitacora` | users |

> Los timestamps se escriben a mano en los nombres de archivo para forzar el orden por dependencias.

---

## 4. Comandos para crear cada migración

`php artisan make:migration` genera la fecha/hora actual en el nombre del archivo, por lo que **hay que renombrarlos** con los timestamps fijos de la tabla de la sección 3. Si no se hace, el orden de ejecución será arbitrario y las FK fallarán.

### 4.1 · Crear los archivos (se ejecutan en orden)

```powershell
# --- 1) ALTER de Spatie (tabla existente) ---
php artisan make:migration add_extra_columns_to_permission_tables --table=roles

# --- 2) Catalogos y tablas base (crear tabla nueva) ---
php artisan make:migration create_tipos_vehiculo_table       --create=tipos_vehiculo
php artisan make:migration create_zonas_table                --create=zonas
php artisan make:migration create_metodos_pago_table         --create=metodos_pago
php artisan make:migration create_tipos_incidencia_table     --create=tipos_incidencia
php artisan make:migration create_dispositivos_table         --create=dispositivos

# --- 3) Core: entidades principales ---
php artisan make:migration create_clientes_table             --create=clientes
php artisan make:migration create_vehiculos_table            --create=vehiculos
php artisan make:migration create_espacios_table             --create=espacios
php artisan make:migration create_tarifas_table              --create=tarifas
php artisan make:migration create_puertas_table              --create=puertas

# --- 4) Core: contratos, asignaciones y cobros ---
php artisan make:migration create_contratos_pension_table    --create=contratos_pension
php artisan make:migration create_asignaciones_espacio_table --create=asignaciones_espacio
php artisan make:migration create_pagos_table                --create=pagos
php artisan make:migration create_movimientos_acceso_table   --create=movimientos_acceso

# --- 5) Fase 2 ---
php artisan make:migration create_incidencias_table          --create=incidencias
php artisan make:migration create_incidencia_archivos_table  --create=incidencia_archivos
php artisan make:migration create_reservaciones_table        --create=reservaciones
php artisan make:migration create_vehiculo_documentos_table  --create=vehiculo_documentos
php artisan make:migration create_bitacora_table             --create=bitacora
```

> **No se crea ninguna migración** para `users` (se edita a mano, ver sección 5.0), ni para las tablas de Spatie (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`), ni para `usuario_roles` / `rol_permisos`.

Resultado: 20 archivos nuevos en `database/migrations/` con un timestamp tipo `2026_10_06_214532_...`.

### 4.2 · Renombrar con los timestamps fijos (orden por dependencias)

```powershell
Set-Location database\migrations

# 1) ALTER Spatie
Rename-Item *_add_extra_columns_to_permission_tables.php   "2026_10_06_210000_add_extra_columns_to_permission_tables.php"

# 2) Catalogos y tablas base
Rename-Item *_create_tipos_vehiculo_table.php              "2026_10_06_210100_create_tipos_vehiculo_table.php"
Rename-Item *_create_zonas_table.php                       "2026_10_06_210200_create_zonas_table.php"
Rename-Item *_create_metodos_pago_table.php                "2026_10_06_210300_create_metodos_pago_table.php"
Rename-Item *_create_tipos_incidencia_table.php            "2026_10_06_210400_create_tipos_incidencia_table.php"
Rename-Item *_create_dispositivos_table.php                "2026_10_06_210500_create_dispositivos_table.php"

# 3) Core: entidades principales
Rename-Item *_create_clientes_table.php                    "2026_10_06_210600_create_clientes_table.php"
Rename-Item *_create_vehiculos_table.php                   "2026_10_06_210700_create_vehiculos_table.php"
Rename-Item *_create_espacios_table.php                    "2026_10_06_210800_create_espacios_table.php"
Rename-Item *_create_tarifas_table.php                     "2026_10_06_210900_create_tarifas_table.php"
Rename-Item *_create_puertas_table.php                     "2026_10_06_211000_create_puertas_table.php"

# 4) Core: contratos, asignaciones y cobros
Rename-Item *_create_contratos_pension_table.php           "2026_10_06_211100_create_contratos_pension_table.php"
Rename-Item *_create_asignaciones_espacio_table.php        "2026_10_06_211200_create_asignaciones_espacio_table.php"
Rename-Item *_create_pagos_table.php                       "2026_10_06_211300_create_pagos_table.php"
Rename-Item *_create_movimientos_acceso_table.php          "2026_10_06_211400_create_movimientos_acceso_table.php"

# 5) Fase 2
Rename-Item *_create_incidencias_table.php                 "2026_10_06_211500_create_incidencias_table.php"
Rename-Item *_create_incidencia_archivos_table.php         "2026_10_06_211600_create_incidencia_archivos_table.php"
Rename-Item *_create_reservaciones_table.php               "2026_10_06_211700_create_reservaciones_table.php"
Rename-Item *_create_vehiculo_documentos_table.php         "2026_10_06_211800_create_vehiculo_documentos_table.php"
Rename-Item *_create_bitacora_table.php                    "2026_10_06_211900_create_bitacora_table.php"

Set-Location ..\..
```

### 4.3 · Verificar el orden resultante

```powershell
Get-ChildItem database\migrations -Filter *.php | Sort-Object Name | Select-Object -ExpandProperty Name
```

Debe aparecer el siguiente orden (los `2014_...` y `2019_...` de Laravel primero, luego `2026_10_06_200523` de Spatie, y recién después los nuevos):

```
2014_10_12_000000_create_users_table.php
...
2026_10_06_200523_create_permission_tables.php
2026_10_06_210000_add_extra_columns_to_permission_tables.php
2026_10_06_210100_create_tipos_vehiculo_table.php
2026_10_06_210200_create_zonas_table.php
2026_10_06_210300_create_metodos_pago_table.php
2026_10_06_210400_create_tipos_incidencia_table.php
2026_10_06_210500_create_dispositivos_table.php
2026_10_06_210600_create_clientes_table.php
2026_10_06_210700_create_vehiculos_table.php
2026_10_06_210800_create_espacios_table.php
2026_10_06_210900_create_tarifas_table.php
2026_10_06_211000_create_puertas_table.php
2026_10_06_211100_create_contratos_pension_table.php
2026_10_06_211200_create_asignaciones_espacio_table.php
2026_10_06_211300_create_pagos_table.php
2026_10_06_211400_create_movimientos_acceso_table.php
2026_10_06_211500_create_incidencias_table.php
2026_10_06_211600_create_incidencia_archivos_table.php
2026_10_06_211700_create_reservaciones_table.php
2026_10_06_211800_create_vehiculo_documentos_table.php
2026_10_06_211900_create_bitacora_table.php
```

### 4.4 · Rellenar cada archivo

Con los archivos creados y renombrados, se pega el contenido de la **sección 5** (Código de las migraciones) en cada uno.

---

## 5. Código de las migraciones

### 5.0 · Editar `database/migrations/2014_10_12_000000_create_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                     // Excel: nombre
            $table->string('email')->unique();                          // Excel: email UNIQUE
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');                                 // Excel: password_hash (bcrypt)
            $table->string('telefono', 30)->nullable();                 // nuevo
            $table->boolean('activo')->default(true);                   // nuevo
            $table->dateTime('ultimo_acceso')->nullable();              // nuevo
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

> La migración de Spatie `2026_10_06_200523_create_permission_tables.php` **no se modifica**.

---

### 5.1 · `2026_10_06_210000_add_extra_columns_to_permission_tables.php`

Agrega al esquema de Spatie las columnas del Excel que el paquete no trae.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // roles: id, name, guard_name, timestamps  -> + descripcion
        Schema::table('roles', function (Blueprint $table) {
            $table->string('descripcion', 255)->nullable()->after('name');
        });

        // permissions: id, name, guard_name, timestamps -> + modulo / accion
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('modulo', 50)->nullable()->after('name')->index();
            $table->string('accion', 30)->nullable()->after('modulo');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['modulo']);
            $table->dropColumn(['modulo', 'accion']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
```

> **No** se crean las tablas `usuario_roles` ni `rol_permisos`: las sustituyen `model_has_roles` y `role_has_permissions`.

---

### 5.2 · `2026_10_06_210100_create_tipos_vehiculo_table.php`

Catálogo · 7 campos · Core

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);                   // Automovil, Motocicleta, Camioneta, SUV, Camion, Remolque
            $table->string('descripcion', 255)->nullable();
            $table->decimal('tarifa_base', 12, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_vehiculo');
    }
};
```

---

### 5.3 · `2026_10_06_210200_create_zonas_table.php`

Catálogo · 6 campos · Core

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);                   // A - Pensiones, B - Visitantes, C - Motocicletas, D - Camiones
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
```

---

### 5.4 · `2026_10_06_210300_create_metodos_pago_table.php`

Catálogo · Core

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50);                   // Efectivo, Tarjeta, Transferencia, Deposito, Terminal
            $table->boolean('requiere_referencia')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();                          // no viene en el Excel; requerido por Eloquent
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
    }
};
```

---

### 5.5 · `2026_10_06_210400_create_tipos_incidencia_table.php`

Catálogo · Fase 2

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_incidencia', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);                   // Dano, Accidente, Intento de acceso, Objeto perdido, Vehiculo abandonado, Problema de pago, Otro
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();                          // no viene en el Excel; requerido por Eloquent
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_incidencia');
    }
};
```

---

### 5.6 · `2026_10_06_210500_create_dispositivos_table.php`

15 campos · Fase 2 (se crea antes que `puertas` por su FK)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->enum('tipo', [
                'CAMARA', 'LECTOR_RFID', 'LECTOR_QR', 'LECTOR_PLACAS',
                'BARRERA', 'SENSOR', 'IMPRESORA',
            ]);
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('numero_serie', 80)->nullable();
            $table->string('ip', 45)->nullable();           // IPv4/IPv6
            $table->string('mac', 20)->nullable();
            $table->string('ubicacion', 150)->nullable();
            $table->string('estado', 30)->default('ACTIVO'); // ACTIVO / INACTIVO / FALLA  (VARCHAR en el Excel)
            $table->json('configuracion')->nullable();       // JSON de configuracion
            $table->dateTime('ultimo_contacto')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos');
    }
};
```

---

### 5.7 · `2026_10_06_210600_create_clientes_table.php`

16 campos · Core

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
```

---

### 5.8 · `2026_10_06_210700_create_vehiculos_table.php`

15 campos · Core

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->string('placas', 20)->unique();          // ver Nota 1
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->smallInteger('anio')->nullable();
            $table->string('color', 50)->nullable();
            $table->foreignId('tipo_vehiculo_id')
                ->constrained('tipos_vehiculo')
                ->restrictOnDelete();
            $table->string('vin', 50)->nullable()->index();  // numero de serie
            $table->string('numero_motor', 100)->nullable();
            $table->string('foto_url', 500)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
```

---

### 5.9 · `2026_10_06_210800_create_espacios_table.php`

11 campos · Core

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('espacios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_id')
                ->constrained('zonas')
                ->restrictOnDelete();
            $table->string('codigo', 30);                    // codigo del cajon
            $table->foreignId('tipo_vehiculo_id')
                ->constrained('tipos_vehiculo')
                ->restrictOnDelete();
            $table->enum('estado', [
                'DISPONIBLE', 'OCUPADO', 'RESERVADO', 'MANTENIMIENTO', 'BLOQUEADO',
            ])->default('DISPONIBLE');
            $table->string('numero', 20)->nullable();
            $table->string('piso', 20)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['zona_id', 'codigo']);           // UNIQUE(zona_id, codigo)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espacios');
    }
};
```

---

### 5.10 · `2026_10_06_210900_create_tarifas_table.php`

13 campos · Core

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);                   // Ej. Mensualidad automovil
            $table->foreignId('tipo_vehiculo_id')
                ->constrained('tipos_vehiculo')
                ->restrictOnDelete();
            $table->enum('tipo_tarifa', [
                'HORA', 'DIA', 'NOCHE', 'MENSUAL', 'ANUAL', 'EVENTO',
            ]);
            $table->decimal('precio', 12, 2);                // auto $1500; moto $800; hora $25; dia $180
            $table->string('periodo', 30)->nullable();
            $table->time('hora_inicio')->nullable();         // tarifas nocturnas
            $table->time('hora_fin')->nullable();
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tipo_vehiculo_id', 'vigencia_desde', 'vigencia_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas');
    }
};
```

---

### 5.11 · `2026_10_06_211000_create_puertas_table.php`

8 campos · Fase 2 (se crea antes que `movimientos_acceso` por su FK)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puertas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);                    // Entrada principal, Salida principal, Empleados...
            $table->string('ubicacion', 150)->nullable();
            $table->string('tipo', 30)->default('ENTRADA');  // ENTRADA / SALIDA
            $table->foreignId('dispositivo_id')              // barrera/camara asociada
                ->nullable()
                ->constrained('dispositivos')
                ->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puertas');
    }
};
```

---

### 5.12 · `2026_10_06_211100_create_contratos_pension_table.php`

15 campos · Core — tabla principal del negocio

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos_pension', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->restrictOnDelete();
            $table->foreignId('tarifa_id')
                ->constrained('tarifas')
                ->restrictOnDelete();
            $table->string('numero_contrato', 30)->unique();  // folio
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->decimal('precio', 12, 2);                 // precio congelado; la tarifa puede cambiar despues
            $table->enum('periodicidad', [
                'MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL',
            ])->default('MENSUAL');
            $table->smallInteger('dia_cobro')->nullable();    // dia del mes de cobro
            $table->enum('estado', [
                'PENDIENTE', 'ACTIVA', 'VENCIDA', 'CANCELADA', 'SUSPENDIDA', 'FINALIZADA',
            ])->default('PENDIENTE');
            $table->decimal('deposito', 12, 2)->default(0);   // deposito en garantia
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_pension');
    }
};
```

---

### 5.13 · `2026_10_06_211200_create_asignaciones_espacio_table.php`

9 campos · Core — historial de cajón por contrato

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_espacio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_pension_id')
                ->constrained('contratos_pension')
                ->cascadeOnDelete();
            $table->foreignId('espacio_id')
                ->constrained('espacios')
                ->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();            // NULL = asignacion vigente
            $table->string('motivo', 255)->nullable();        // cambio de cajon, mantenimiento, etc.
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['espacio_id', 'fecha_fin']);       // buscar la asignacion vigente del cajon
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_espacio');
    }
};
```

---

### 5.14 · `2026_10_06_211300_create_pagos_table.php`

18 campos · Core

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_pension_id')
                ->constrained('contratos_pension')
                ->restrictOnDelete();
            $table->foreignId('cliente_id')                  // denormalizado para consulta rapida
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->string('numero_recibo', 30)->unique();
            $table->dateTime('fecha_pago');
            $table->date('periodo_inicio')->nullable();       // periodo que cubre el pago
            $table->date('periodo_fin')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('metodo_pago_id')
                ->constrained('metodos_pago')
                ->restrictOnDelete();
            $table->string('referencia', 100)->nullable();    // transferencia / terminal
            $table->enum('estado', [
                'PENDIENTE', 'PAGADO', 'CANCELADO', 'REEMBOLSADO',
            ])->default('PENDIENTE');
            $table->foreignId('usuario_id')                  // quien registro el cobro
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['periodo_inicio', 'periodo_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
```

---

### 5.15 · `2026_10_06_211400_create_movimientos_acceso_table.php`

15 campos · Core — entradas y salidas físicas

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_acceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->restrictOnDelete();
            $table->foreignId('cliente_id')                  // propietario al momento del acceso
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->foreignId('contrato_pension_id')         // NULL si es visitante
                ->nullable()
                ->constrained('contratos_pension')
                ->nullOnDelete();
            $table->foreignId('espacio_id')                  // cajon usado
                ->nullable()
                ->constrained('espacios')
                ->nullOnDelete();
            $table->enum('tipo', ['ENTRADA', 'SALIDA']);
            $table->dateTime('fecha_hora');
            $table->string('placas_detectadas', 20)->nullable();  // lectura LPR / manual
            $table->foreignId('usuario_id')                  // quien registro la operacion
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('puerta_id')                   // FK a Fase 2 (ya creada antes)
                ->nullable()
                ->constrained('puertas')
                ->nullOnDelete();
            $table->string('metodo_acceso', 30)->default('MANUAL'); // MANUAL / QR / RFID / LPR
            $table->string('foto_entrada', 500)->nullable();  // evidencia
            $table->string('foto_salida', 500)->nullable();   // evidencia
            $table->text('observaciones')->nullable();
            $table->timestamps();                            // Excel solo trae created_at

            $table->index(['vehiculo_id', 'fecha_hora']);
            $table->index(['fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_acceso');
    }
};
```

---

### 5.16 · `2026_10_06_211500_create_incidencias_table.php`

11 campos · Fase 2

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->restrictOnDelete();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->foreignId('contrato_pension_id')
                ->nullable()
                ->constrained('contratos_pension')
                ->nullOnDelete();
            $table->foreignId('tipo_incidencia_id')
                ->constrained('tipos_incidencia')
                ->restrictOnDelete();
            $table->dateTime('fecha_hora');
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('ABIERTA'); // ABIERTA / EN PROCESO / CERRADA (VARCHAR en el Excel)
            $table->foreignId('usuario_id')                   // quien la registro
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias');
    }
};
```

---

### 5.17 · `2026_10_06_211600_create_incidencia_archivos_table.php`

7 campos · Fase 2

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencia_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')
                ->constrained('incidencias')
                ->cascadeOnDelete();
            $table->string('tipo_archivo', 30)->default('FOTO'); // FOTO / VIDEO / DOCUMENTO
            $table->string('url', 500);                          // S3 / MinIO
            $table->string('nombre_archivo', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();                                // Excel solo trae created_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencia_archivos');
    }
};
```

---

### 5.18 · `2026_10_06_211700_create_reservaciones_table.php`

12 campos · Fase 2

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->restrictOnDelete();
            $table->foreignId('espacio_id')
                ->constrained('espacios')
                ->restrictOnDelete();
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->enum('estado', [
                'PENDIENTE', 'CONFIRMADA', 'CANCELADA', 'FINALIZADA',
            ])->default('PENDIENTE');
            $table->decimal('precio', 12, 2)->default(0);
            $table->foreignId('usuario_id')                   // quien la creo
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['espacio_id', 'fecha_inicio', 'fecha_fin']); // evitar doble reserva
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservaciones');
    }
};
```

---

### 5.19 · `2026_10_06_211800_create_vehiculo_documentos_table.php`

10 campos · Fase 2

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->cascadeOnDelete();
            $table->enum('tipo_documento', [
                'TARJETA_CIRCULACION', 'SEGURO', 'IDENTIFICACION', 'CONTRATO', 'OTRO',
            ]);
            $table->string('numero', 50)->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();   // util para alertas de vencimiento
            $table->string('archivo_url', 500)->nullable();
            $table->string('estado', 30)->default('VIGENTE'); // VIGENTE / VENCIDO (VARCHAR en el Excel)
            $table->timestamps();

            $table->index('fecha_vencimiento');              // alertas
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_documentos');
    }
};
```

---

### 5.20 · `2026_10_06_211900_create_bitacora_table.php`

10 campos · Fase 2 — auditoría

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')                  // se conserva el historial aunque el usuario baje
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('accion', 30);                    // CREAR / ACTUALIZAR / ELIMINAR / LOGIN
            $table->string('tabla', 60);                     // tabla afectada
            $table->unsignedBigInteger('registro_id')->nullable(); // ID del registro afectado
            $table->json('datos_anteriores')->nullable();     // Ej. precio_anterior: 1500
            $table->json('datos_nuevos')->nullable();         // Ej. precio_nuevo: 1700
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();                            // Excel solo trae created_at

            $table->index(['tabla', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
```

---

## 6. Ejecución y verificación

```powershell
# 1. Extension pdo_mysql habilitada (ver seccion 2)
php -m | Select-String pdo_mysql

# 2. Migrar todo desde cero
php artisan migrate:fresh

# 3. Deben salir 25 migraciones en verde (4 de Laravel + 1 de Spatie + 20 nuevas)
php artisan migrate:status

# 4. Semillas (roles de Spatie, permisos, catalogos, admin)
php artisan db:seed
```

Verificación rápida desde Tinker:

```php
php artisan tinker
>>> Schema::hasTable('contratos_pension')      // true
>>> Spatie\Permission\Models\Role::count()      // 5 (ADMINISTRADOR, SUPERVISOR, CAJERO, RECEPCION, SEGURIDAD)
>>> \App\Models\User::first()->hasRole('ADMINISTRADOR')
```

---

## 7. Notas y pendientes

1. **`vehiculos.placas` UNIQUE** — el propio Excel advierte: *"si la placa se reutiliza historicamente, manejar vigencia en vez de UNIQUE"*. Queda `unique()` en el MVP; si hay placas recicladas, se reemplaza por un índice simple + historial.
2. **`clientes.email` no es UNIQUE** en el Excel; se dejó como campo normal (varios contactos pueden compartir correo).
3. **Desviación de timestamps** — `metodos_pago`, `tipos_incidencia` y `permissions` no traen `created_at/updated_at` en el Excel, y `movimientos_acceso`, `bitacora`, `incidencia_archivos` solo `created_at`. Se agregó `timestamps()` completo para que Eloquent funcione sin flags especiales.
4. **`usuarios` → `users`** — se conservó la tabla y el campo `password` de Laravel; se agregaron `telefono`, `activo`, `ultimo_acceso`.
5. **Tablas Spatie** — `usuario_roles` y `rol_permisos` del Excel se sustituyen por `model_has_roles` y `role_has_permissions`; el modelo `User` debe incluir el trait `HasRoles`.
