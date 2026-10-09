<p align="center"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></p>

# Sistema de Pensión Vehicular — Alcomex

Aplicación web para la administración de un estacionamiento con pensiones: clientes, vehículos, cajones, tarifas, contratos, cobros y control de entradas/salidas.

**Metodología:** Scrum (10 sprints) · **Documentación de dominio:** [`PLAN-SCRUM.md`](PLAN-SCRUM.md), [`MIGRACIONES.md`](MIGRACIONES.md)

---

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.1 · Laravel 10 |
| Frontend | Blade · Bootstrap 5 · Sass · Vite |
| Base de datos | MySQL (PDO) |
| Auth | laravel/ui (login/logout/reset) |
| Autorización | spatie/laravel-permission (roles y permisos `modulo.accion`) |
| Extras | laravel/sanctum · SweetAlert2 · qrcode-generator |

---

## Módulos

| # | Módulo | Ruta | Estado |
|---|---|---|---|
| 1 | Dashboard | `/home` | 🟡 Alertas de contratos por vencer y vehículos dentro; KPIs/gráficas en Sprint 8 |
| 2 | Clientes / Propietarios | `/clientes` | ✅ |
| 3 | Vehículos | `/vehiculos` | ✅ |
| 4 | Espacios / Cajones | `/espacios`, `/zonas`, `/tipos-vehiculo` | ✅ |
| 5 | Entradas y salidas | `/accesos` | ✅ |
| 6 | Tarifas | `/tarifas` | ✅ |
| 7 | Pensiones / Contratos | `/contratos` | ✅ |
| 8 | Pagos / Caja | `/pagos`, `/pagos/corte` | ✅ |
| 9 | Accesos (QR, RFID, LPR, barreras) | `/accesos/qr`, `/accesos/rfid`, `/accesos/lpr` | ✅ (hardware con mock) |
| 10 | Incidencias | — | ⏳ Sprint 6 |
| 11 | Reportes | — | ⏳ Sprint 8 |
| 12 | Usuarios y permisos | — | ⏳ Sprint 7 |

---

## Requisitos

- PHP >= 8.1 con extensiones `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- Composer
- Node.js >= 18 y npm
- MySQL 8 (o MariaDB equivalente)

---

## Instalación

```bash
# 1. Clonar
git clone https://github.com/powerslave12334/PensionesAlcomex.git
cd PensionesAlcomex

# 2. Dependencias
composer install
npm install

# 3. Configuración
cp .env.example .env
php artisan key:generate

# 4. Base de datos: crear el esquema
#    EDITA .env con tus credenciales (DB_DATABASE, DB_USERNAME, DB_PASSWORD)
#    y crea la base de datos, por ejemplo:
#    CREATE DATABASE pensiones CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

php artisan migrate --seed

# 5. Assets
npm run build        # o `npm run dev` en desarrollo

# 6. Ejecutar
php artisan serve    # http://127.0.0.1:8000
```

> `php artisan migrate --seed` deja la instancia funcional: catálogos, roles, permisos y usuario administrador.

---

## Acceso inicial

| Campo | Valor |
|---|---|
| Email | `admin@pension.mx` |
| Password | `password` |

> ⚠️ Cambia estas credenciales antes de cualquier despliegue fuera de desarrollo.

### Roles sembrados

`ADMINISTRADOR` · `SUPERVISOR` · `CAJERO` · `RECEPCION` · `SEGURIDAD`

Los permisos siguen el patrón `modulo.accion` (ej. `pagos.crear`, `contratos.renovar`) y se asignan por rol desde `database/seeders/RolPermisoSeeder.php`.

---

## Estructura del proyecto

```
app/
├── Http/Controllers/    # Controladores por módulo (Cliente, Vehiculo, Espacio, Tarifa, Contrato, Pago, Acceso…)
└── Models/              # 14 modelos Eloquent + relaciones del dominio
database/
├── migrations/          # Esquema completo (27 migraciones)
└── seeders/             # Catálogos, roles/permisos, dispositivos, puertas, tarifas
resources/views/         # Blade por módulo + layouts y partials
routes/web.php           # Rutas agrupadas por sprint/módulo
```

---

## Documentación

| Documento | Contenido |
|---|---|
| [`PLAN-SCRUM.md`](PLAN-SCRUM.md) | Backlog, sprints, roles del equipo, criterios de aceptación |
| [`MIGRACIONES.md`](MIGRACIONES.md) | Esquema de base de datos |
| [`GUIA-LOGIN-MODULO1.md`](GUIA-LOGIN-MODULO1.md) | Guía del módulo de acceso |
| [`GUIA-SPRINT2-ESPACIOS.md`](GUIA-SPRINT2-ESPACIOS.md) | Espacios / cajones |
| [`GUIA-SPRINT3-TARIFAS-Y-PENSIONES.md`](GUIA-SPRINT3-TARIFAS-Y-PENSIONES.md) | Tarifas y contratos |
| [`GUIA-SPRINT4-PAGOS-Y-CAJA.md`](GUIA-SPRINT4-PAGOS-Y-CAJA.md) | Pagos y corte de caja |
| [`GUIA-SPRINT5-ENTRADAS-SALIDAS-ACCESOS.md`](GUIA-SPRINT5-ENTRADAS-SALIDAS-ACCESOS.md) | Entradas, salidas y accesos |

---

## Pruebas y calidad de código

```bash
php artisan test              # suite PHPUnit
vendor/bin/pint               # formateo (Laravel Pint)
```

---

## Variables relevantes de `.env`

| Variable | Descripción |
|---|---|
| `DB_*` | Conexión a MySQL |
| `APP_URL` | URL base usada por los enlaces generados |
| `FILESYSTEM_DISK` | Disco para fotos de vehículos y evidencias |

---

## Licencia

El framework Laravel es software de código abierto con licencia [MIT](https://opensource.org/licenses/MIT). El código de este proyecto es propietario del equipo del proyecto.
