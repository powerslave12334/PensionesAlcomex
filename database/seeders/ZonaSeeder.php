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
