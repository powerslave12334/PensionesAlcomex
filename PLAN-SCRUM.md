# Plan Scrum — Sistema de Pensión Vehicular

Plan de trabajo con metodología **Scrum** para la construcción de los 12 módulos del sistema.

- **Documento relacionado:** `MIGRACIONES.md` (esquema de BD y migraciones)
- **Stack:** Laravel 10 · Livewire 4 · MySQL · Spatie laravel-permission
- **Duración estimada:** 10 sprints (Sprint 0 de 1 semana + 9 sprints de 2 semanas) ≈ 19 semanas
- **Velocidad asumida:** 40–45 story points / sprint (equipo de 2 devs)

---

## 1. Equipo y roles

| Rol Scrum | Responsabilidad |
|---|---|
| **Product Owner (PO)** | Dueño del Product Backlog. Prioriza, define criterios de aceptación, acepta el incremento en la Sprint Review. |
| **Scrum Master (SM)** | Facilita las ceremonias, retira impedimentos, cuida el cumplimiento de Scrum. |
| **Dev Backend (2)** | Modelos, migraciones, lógica de negocio, APIs internas, seeders. |
| **Dev Frontend / Diseño (1)** | Vistas Blade/Livewire, layout, componentes, UX. |
| **QA (1, parcial)** | Casos de prueba, pruebas manuales, regresión, criterios de aceptación. |

---

## 2. Artefactos

| Artefacto | Contenido | Responsable |
|---|---|---|
| **Product Backlog** | Todas las historias de usuario de los 12 módulos (§6), priorizadas con MoSCoW y estimadas en puntos de historia. | PO |
| **Sprint Backlog** | Historias comprometidas en el sprint + tareas técnicas desglosadas. | Equipo |
| **Incremento** | Cada sprint entrega software funcional en `develop`, migrado y con seeders. | Equipo |
| **Definition of Done** | Lista única de terminado aplicada a cada historia (§5). | Equipo |

---

## 3. Ceremonias

| Ceremonia | Frecuencia | Duración | Agenda |
|---|---|---|---|
| **Sprint Planning** | Inicio del sprint | 2 h | Revisar objetivo, seleccionar historias, desglosar tareas, comprometer puntos. |
| **Daily Scrum** | Diaria (L–V) | 15 min | ¿Qué hice? ¿Qué hago? ¿Hay impedimentos? |
| **Refinamiento del backlog** | 1 vez por sprint | 1 h | Aclarar dudas, re-estimar, añadir criterios de aceptación. |
| **Sprint Review** | Fin del sprint | 1 h | Demo del incremento al PO/stakeholders, aceptar o rechazar historias. |
| **Retrospectiva** | Fin del sprint | 45 min | Qué salió bien, qué mejorar, 1–2 acciones para el próximo sprint. |

---

## 4. Estimación y priorización

- **Estimación:** puntos de historia en escala de Fibonacci (1, 2, 3, 5, 8, 13, 21). Una historia > 13 pts se parte antes de entrar al sprint.
- **Prioridad MoSCoW:** `M` Must (MVP) · `S` Should · `C` Could · `W` Won't (esta versión).
- **Mapeo con la BD:** el Excel marca 13 tablas como *Core/MVP* y 11 como *Fase 2*; ese criterio alimenta la prioridad.

---

## 5. Definition of Done (DoD)

Una historia está **terminada** cuando:

1. El código está en `develop` y sin errores de sintaxis/estilos (`vendor/bin/pint --test`).
2. Migración/modelo/relaciones creadas y `php artisan migrate:fresh --seed` funciona desde cero.
3. Criterios de aceptación verificados por QA.
4. Autorización aplicada (rol/permiso de Spatie) si la historia toca datos sensibles.
5. Validaciones de servidor + mensajes de error amigables en español.
6. Estado vacío, de carga y de error implementados en la vista.
7. Pruebas automatizadas del flujo principal en verde (`php artisan test`).
8. Aceptada por el PO en la Sprint Review.

---

## 6. Roadmap de sprints

| Sprint | Duración | Módulos | Objetivo | Prioridad |
|---|---|---|---|---|
| **Sprint 0** | 1 sem | — | Fundamentos: BD, auth, layout, Spatie, catálogos base | M |
| **Sprint 1** | 2 sem | 2, 3 | Clientes y Vehículos | M |
| **Sprint 2** | 2 sem | 4 | Espacios / Cajones + catálogos de zona y tipo | M |
| **Sprint 3** | 2 sem | 6, 7 | Tarifas y Contratos de pensión | M |
| **Sprint 4** | 2 sem | 8 | Pagos / Caja y recibos | M |
| **Sprint 5** | 2 sem | 5, 9 | Entradas y salidas / Accesos | M |
| **Sprint 6** | 2 sem | 10 | Incidencias y documentos del vehículo | S |
| **Sprint 7** | 2 sem | 12 | Usuarios, permisos y bitácora | M |
| **Sprint 8** | 2 sem | 1, 11 | Dashboard y Reportes | S |
| **Sprint 9** | 2 sem | — | QA integral, seguridad, rendimiento, despliegue | M |

---

## 7. Detalle por sprint

### Sprint 0 — Fundamentos (1 semana)

**Objetivo:** tener el esqueleto de la aplicación listo para construir módulos.

| ID | Historia / tarea | Pts |
|---|---|---|
| T-001 | Habilitar `pdo_mysql`, crear BD `pensiones`, correr las 25 migraciones de `MIGRACIONES.md` | 2 |
| T-002 | Modelos Eloquent de las 19 tablas + relaciones de la hoja *Relaciones* | 8 |
| T-003 | `User` con trait `HasRoles` de Spatie | 1 |
| T-004 | Seeders: 5 roles (ADMINISTRADOR, SUPERVISOR, CAJERO, RECEPCION, SEGURIDAD), permisos `modulo.accion`, catálogos (tipos_vehiculo, zonas, metodos_pago, tipos_incidencia), usuario admin | 5 |
| T-005 | Auth con laravel/ui (login/logout, middleware) | 5 |
| T-006 | Layout base: sidebar con los 12 módulos, topbar, plantillas Blade/Livewire | 8 |
| T-007 | Middleware `permission:` registrado y probado | 2 |
| T-008 | Estructura de carpetas y convenciones (Services, Livewire, Actions) | 2 |
| | **Total** | **33** |

---

### Sprint 1 — Clientes y Vehículos (Módulos 2 y 3)

**Objetivo:** dar de alta y consultar clientes con sus vehículos.

| ID | Historia de usuario | Pts | Prioridad |
|---|---|---|---|
| US-0101 | Como **administrador**, quiero registrar un cliente con nombre, apellidos, teléfono, correo, dirección y RFC, para tener su ficha completa. | 8 | M |
| US-0102 | Como **administrador**, quiero editar y desactivar (`activo`) un cliente, para mantener el histórico sin borrar datos. | 3 | M |
| US-0103 | Como **administrador**, quiero buscar y filtrar clientes por nombre, correo o teléfono, para encontrarlos rápido. | 5 | M |
| US-0104 | Como **administrador**, quiero registrar un vehículo con placas, marca, modelo, año, color, tipo, VIN, foto y propietario, para identificarlo en el estacionamiento. | 8 | M |
| US-0105 | Como **administrador**, quiero ver el detalle del cliente con sus vehículos asociados, para tener la vista 360°. | 5 | M |
| US-0106 | Como **administrador**, quiero ver el historial de estancias del cliente, para revisar su comportamiento. | 3 | S |
| US-0107 | Como **sistema**, quiero validaciones de unicidad (email, VIN) y formato (placas, RFC), para evitar datos duplicados. | 3 | M |
| | **Total** | **35** | |

**Criterios de aceptación clave (US-0104):** las placas son únicas; el tipo de vehículo es obligatorio y proviene del catálogo; la foto se sube a `storage` y se guarda en `foto_url`; al guardar se registra en la bitácora.

**BD:** `clientes`, `vehiculos`, `tipos_vehiculo` (seed).

---

### Sprint 2 — Espacios / Cajones (Módulo 4)

**Objetivo:** conocer qué cajones existen, en qué zona están y quién los ocupa.

| ID | Historia de usuario | Pts | Prioridad |
|---|---|---|---|
| US-0201 | Como **administrador**, quiero administrar zonas (Pensiones, Visitantes, Motos, Camiones), para organizar el predio. | 5 | M |
| US-0202 | Como **administrador**, quiero administrar los tipos de vehículo con su tarifa base, para clasificar cajones y cobros. | 3 | M |
| US-0203 | Como **administrador**, quiero dar de alta cajones con código único por zona, piso y tipo compatible, para no duplicar espacios. | 8 | M |
| US-0204 | Como **recepcionista**, quiero cambiar el estado de un cajón (libre, ocupado, reservado, mantenimiento), para reflejar la realidad física. | 5 | M |
| US-0205 | Como **supervisor**, quiero ver la lista/mapa de cajones por zona con su estado, para saber la ocupación de un vistazo. | 8 | M |
| US-0206 | Como **recepcionista**, quiero ver el vehículo asignado actualmente a cada cajón, para resolver dudas en recepción. | 5 | M |
| | **Total** | **34** | |

**BD:** `zonas`, `espacios`, `asignaciones_espacio` (lectura del vehículo vigente).

---

### Sprint 3 — Tarifas y Pensiones (Módulos 6 y 7)

**Objetivo:** poder crear un contrato de pensión con su cajón y su precio congelado.

| ID | Historia de usuario | Pts | Prioridad |
|---|---|---|---|
| US-0301 | Como **administrador**, quiero crear tarifas por tipo de vehículo y periodo (hora, día, noche, mensual, anual, evento) con vigencia desde/hasta, para controlar los precios. | 8 | M |
| US-0302 | Como **administrador**, quiero que el sistema solo muestre la tarifa vigente al crear un contrato, para evitar errores de precio. | 3 | M |
| US-0303 | Como **recepcionista**, quiero generar un contrato con folio único, cliente, vehículo, fechas, periodicidad y precio congelado, para formalizar la pensión. | 13 | M |
| US-0304 | Como **recepcionista**, quiero asignar un cajón al contrato (con historial de asignaciones), para saber dónde está el vehículo. | 8 | M |
| US-0305 | Como **administrador**, quiero gestionar estados del contrato (pendiente, activa, vencida, cancelada, suspendida, finalizada) y renovarlo, para llevar el ciclo de vida. | 8 | M |
| US-0306 | Como **administrador**, quiero listar y filtrar contratos y ver su detalle con pagos y asignaciones, para tener el expediente completo. | 5 | M |
| US-0307 | Como **sistema**, quiero alertar contratos por vencer (15 días antes), para impulsar renovaciones. | 3 | S |
| | **Total** | **48** → comprometer **40** | |

**BD:** `tarifas`, `contratos_pension`, `asignaciones_espacio`.

---

### Sprint 4 — Pagos / Caja (Módulo 8)

**Objetivo:** cobrar una mensualidad y emitir el recibo.

| ID | Historia de usuario | Pts | Prioridad |
|---|---|---|---|
| US-0401 | Como **cajero**, quiero registrar un cobro con recibo foliado único, periodo que cubre, subtotal, descuento, impuesto y total, para dejar constancia. | 13 | M |
| US-0402 | Como **cajero**, quiero elegir el método de pago (efectivo, tarjeta, transferencia, depósito) y que me pida referencia cuando corresponda, para conciliar. | 5 | M |
| US-0403 | Como **cajero**, quiero imprimir o descargar el recibo en PDF, para entregarlo al cliente. | 5 | M |
| US-0404 | Como **administrador**, quiero ver el historial de pagos por contrato y por cliente, para verificar saldos. | 5 | M |
| US-0405 | Como **cajero**, quiero hacer el **corte de caja** del día (totales por método), para cuadrar el turno. | 8 | S |
| US-0406 | Como **administrador**, quiero cancelar o reembolsar un pago con motivo, para corregir errores sin perder el registro. | 5 | S |
| US-0407 | Como **sistema**, quiero bloquear el botón de cobro si el permiso `pagos.crear` no está asignado, para respetar roles. | 2 | M |
| | **Total** | **43** → comprometer **40** | |

**BD:** `pagos`, `metodos_pago`.

---

### Sprint 5 — Entradas y Salidas / Accesos (Módulos 5 y 9)

**Objetivo:** registrar entradas y salidas y calcular la permanencia.

| ID | Historia de usuario | Pts | Prioridad |
|---|---|---|---|
| US-0501 | Como **seguridad/recepción**, quiero registrar la **entrada** de un vehículo con fecha/hora, cajón, usuario y evidencia, para dejar el movimiento. | 8 | M |
| US-0502 | Como **seguridad/recepción**, quiero registrar la **salida**, calcular el tiempo de permanencia y el cobro correspondiente, para facturar el tiempo. | 13 | M |
| US-0503 | Como **cliente**, quiero un **código QR** por contrato para entrar y salir, para agilizar el acceso. | 8 | S |
| US-0504 | Como **seguridad**, quiero leer **tarjeta RFID** asociada al vehículo, para registrar el acceso sin intervención manual. | 8 | S |
| US-0505 | Como **sistema**, quiero la lectura automática de placas (**LPR/ANPR**) con las fotos de entrada y salida, para validar el vehículo. | 13 | C |
| US-0506 | Como **sistema**, quiero controlar las **barreras** (abrir/cerrar) desde el registro de acceso, para integrar hardware. | 8 | C |
| US-0507 | Como **supervisor**, quiero el listado de entradas/salidas con filtros por fecha y vehículo, para auditar el flujo. | 5 | M |
| | **Total** | **63** → comprometer **40** (US-0505/0506 con mock en esta versión) | |

**BD:** `movimientos_acceso`, `puertas`, `dispositivos` (mocks si no hay hardware).

---

### Sprint 6 — Incidencias (Módulo 10)

**Objetivo:** registrar y dar seguimiento a incidentes con evidencia.

| ID | Historia de usuario | Pts | Prioridad |
|---|---|---|---|
| US-0601 | Como **seguridad**, quiero registrar una incidencia (daño, accidente, objeto olvidado, vehículo abandonado, problema de pago) vinculada al vehículo y cliente. | 8 | S |
| US-0602 | Como **seguridad**, quiero subir fotos/videos/documentos a la incidencia, para guardar la evidencia. | 5 | S |
| US-0603 | Como **supervisor**, quiero mover la incidencia entre estados (abierta → en proceso → cerrada), para dar seguimiento. | 5 | S |
| US-0604 | Como **administrador**, quiero administrar los tipos de incidencia, para personalizar los motivos. | 3 | S |
| US-0605 | Como **administrador**, quiero el expediente de documentos del vehículo (tarjeta de circulación, seguro) con fecha de vencimiento y alertas, para prevenir vencimientos. | 8 | S |
| | **Total** | **29** | |

**BD:** `incidencias`, `tipos_incidencia`, `incidencia_archivos`, `vehiculo_documentos`.

---

### Sprint 7 — Usuarios, permisos y bitácora (Módulo 12)

**Objetivo:** controlar quién puede hacer qué y dejar constancia de las acciones.

| ID | Historia de usuario | Pts | Prioridad |
|---|---|---|---|
| US-0701 | Como **administrador**, quiero crear, editar y desactivar usuarios del sistema, para gestionar el acceso. | 5 | M |
| US-0702 | Como **administrador**, quiero una **matriz de permisos** módulo × acción (ver, crear, editar, eliminar, cancelar), para asignar permisos en bloque. | 13 | M |
| US-0703 | Como **administrador**, quiero asignar y quitar roles (Administrador, Recepcionista, Cajero, Supervisor, Seguridad) a los usuarios. | 5 | M |
| US-0704 | Como **sistema**, quiero ocultar/deshabilitar los botones y rutas sin permiso, para que cada rol solo vea lo suyo. | 8 | M |
| US-0705 | Como **administrador**, quiero una **bitácora** con usuario, acción, tabla, registro, datos anteriores/nuevos, IP y fecha, para auditoría. | 8 | M |
| US-0706 | Como **administrador**, quiero consultar y filtrar la bitácora, para investigar cambios. | 5 | S |
| | **Total** | **44** → comprometer **40** | |

**BD:** `roles`, `permissions`, pivotes de Spatie, `bitacora`.

---

### Sprint 8 — Dashboard y Reportes (Módulos 1 y 11)

**Objetivo:** visibilidad del negocio en tiempo real.

| ID | Historia de usuario | Pts | Prioridad |
|---|---|---|---|
| US-0801 | Como **administrador**, quiero un dashboard con: vehículos actualmente dentro, espacios ocupados/libres, ingresos del día y del mes. | 8 | S |
| US-0802 | Como **administrador**, quiero la gráfica de vehículos por tipo y las últimas entradas y salidas en el dashboard. | 5 | S |
| US-0803 | Como **administrador**, quiero el reporte de **ingresos** por período y método de pago. | 8 | S |
| US-0804 | Como **administrador**, quiero el reporte de **ocupación** por zona y el histórico de cajones. | 5 | S |
| US-0805 | Como **administrador**, quiero los reportes de **pensiones activas/vencidas** y **pagos pendientes**. | 8 | S |
| US-0806 | Como **administrador**, quiero exportar los reportes a **Excel/PDF**. | 5 | C |
| | **Total** | **39** | |

---

### Sprint 9 — QA, seguridad y despliegue

**Objetivo:** dejar el incremento listo para producción.

| ID | Tarea | Pts |
|---|---|---|
| T-0901 | Suite de pruebas de los flujos críticos (contrato → pago → acceso) | 8 |
| T-0902 | Revisión de seguridad: políticas, protección CSRF/SQL/XSS, subida de archivos, rate limiting en login | 8 |
| T-0903 | Optimización: índices, consultas N+1, caché de KPIs del dashboard | 5 |
| T-0904 | Datos de demostración (seeder realista) | 3 |
| T-0905 | Despliegue (`.env` de producción, migraciones, permisos de storage, backup) | 5 |
| T-0906 | Documentación de usuario por módulo | 3 |
| | **Total** | **32** |

---

## 8. Backlog resumido por módulo

| # | Módulo | Sprint | Prioridad | Tablas BD principales | Rol principal |
|---|---|---|---|---|---|
| 1 | Dashboard | 8 | S | (consultas) | Administrador |
| 2 | Vehículos | 1 | M | `vehiculos`, `tipos_vehiculo` | Administrador |
| 3 | Clientes / Propietarios | 1 | M | `clientes` | Administrador |
| 4 | Espacios / Cajones | 2 | M | `zonas`, `espacios`, `asignaciones_espacio` | Recepción |
| 5 | Entradas y salidas | 5 | M | `movimientos_acceso` | Seguridad |
| 6 | Tarifas | 3 | M | `tarifas` | Administrador |
| 7 | Pensiones / Abonos | 3 | M | `contratos_pension`, `asignaciones_espacio` | Recepción |
| 8 | Pagos / Caja | 4 | M | `pagos`, `metodos_pago` | Cajero |
| 9 | Accesos | 5 | M/S/C | `movimientos_acceso`, `puertas`, `dispositivos` | Seguridad |
| 10 | Incidencias | 6 | S | `incidencias`, `incidencia_archivos`, `vehiculo_documentos` | Seguridad |
| 11 | Reportes | 8 | S | (consultas) | Administrador |
| 12 | Usuarios y permisos | 7 | M | Spatie + `bitacora` | Administrador |

---

## 9. Riesgos y mitigación

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Integración de hardware (barreras, LPR, RFID) no disponible | Alto | En el Sprint 5 se trabaja contra **mocks**/interfaces; la integración real queda como incremento posterior. |
| Alcance inflado en tarifas/contratos (los más complejos) | Alto | Partir historias > 13 pts; el PO corta a *Must* antes de empezar el sprint. |
| Historias de Dashboard/Reportes con datos incompletos | Medio | Posponer al Sprint 8 a propósito: requieren que todos los módulos previos generen datos. |
| Permisos agregados tarde rompen funcionalidad ya construida | Medio | Los permisos se **semejan desde el Sprint 0** y se aplica la guardia desde cada módulo; el Sprint 7 solo construye la UI. |
| Rendimiento en `movimientos_acceso` (tabla de mucho volumen) | Medio | Índices ya creados en la migración + paginación en listados. |
| Cambios de alcance del PO | Medio | Refinamiento semanal; lo nuevo entra al backlog y desplaza prioridades, no se inyecta en el sprint en curso. |

---

## 10. Criterios de aceptación del proyecto (éxito)

1. Los 12 módulos operativos y navegables desde el sidebar.
2. Un cliente nuevo puede: registrarse → crear contrato → asignar cajón → pagar → entrar y salir el vehículo.
3. Cada rol solo ve y ejecuta lo que sus permisos permiten (prueba con los 5 roles).
4. `php artisan migrate:fresh --seed` levanta una instancia funcional en < 2 minutos.
5. Dashboard con datos reales de los últimos 30 días.
6. Bitácora registrando todas las acciones de escritura.
