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
