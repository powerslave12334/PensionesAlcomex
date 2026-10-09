<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DispositivoSeeder extends Seeder
{
    /**
     * Catálogo de hardware (hoja "dispositivos" del Excel).
     * Idempotente: updateOrInsert por nombre.
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
            // Ojo: DB::table no convierte arrays a JSON (eso lo hace Eloquent).
            $registro = $dispositivo;
            $registro['configuracion'] = $dispositivo['configuracion']
                ? json_encode($dispositivo['configuracion'], JSON_UNESCAPED_UNICODE)
                : null;

            DB::table('dispositivos')->updateOrInsert(
                ['nombre' => $dispositivo['nombre']],
                $registro + [
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
