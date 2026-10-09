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

        // Reparto de permisos por rol
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
            // Sprint 4 — consulta el histórico, no cobra
            'pagos.ver',
            // Sprint 5 — abre y cierra la caseta (US-0501, US-0502)
            'accesos.ver', 'accesos.registrar', 'accesos.cobrar',
        ]);

        Role::findByName('SUPERVISOR')->syncPermissions([
            'clientes.ver', 'vehiculos.ver',
            'zonas.ver', 'tipos_vehiculo.ver', 'espacios.ver',
            'tarifas.ver', 'contratos.ver',
            // Sprint 4 — vigila la caja
            'pagos.ver', 'pagos.corte',
            // Sprint 5 — solo audita el flujo (US-0507)
            'accesos.ver',
        ]);

        Role::findByName('CAJERO')->syncPermissions([
            'espacios.ver', 'contratos.ver',
            // Sprint 4 — cobra y hace corte (US-0401, US-0405)
            'pagos.ver', 'pagos.crear', 'pagos.corte',
            // Sprint 5 — liquida el cobro de la salida de visitantes (US-0502)
            'accesos.ver', 'accesos.cobrar',
        ]);

        Role::findByName('SEGURIDAD')->syncPermissions([
            'espacios.ver', 'contratos.ver',   // verificar titular del cajón
            // Sprint 5 — opera la caseta completa (US-0501, US-0502)
            'accesos.ver', 'accesos.registrar', 'accesos.cobrar',
        ]);

        // Usuario admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@pension.mx'],
            ['name' => 'Administrador', 'password' => bcrypt('password'), 'activo' => true]
        );
        $admin->syncRoles(['ADMINISTRADOR']);
    }
}
