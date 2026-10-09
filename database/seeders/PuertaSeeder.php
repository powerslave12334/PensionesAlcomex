<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PuertaSeeder extends Seeder
{
    /**
     * Las 2 casetas del recinto. Cada puerta apunta a su barrera
     * (FK puertas.dispositivo_id → dispositivos.id).
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
