<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MetodoPagoSeeder extends Seeder
{
    /**
     * Catálogo de la hoja "metodos_pago" (US-0402).
     * `requiere_referencia` gobierna si el formulario exige referencia.
     */
    public function run(): void
    {
        $metodos = [
            ['nombre' => 'Efectivo',      'requiere_referencia' => false],
            ['nombre' => 'Tarjeta',       'requiere_referencia' => true],
            ['nombre' => 'Transferencia', 'requiere_referencia' => true],
            ['nombre' => 'Depósito',      'requiere_referencia' => true],
            ['nombre' => 'Terminal',      'requiere_referencia' => false],
        ];

        foreach ($metodos as $metodo) {
            DB::table('metodos_pago')->updateOrInsert(
                ['nombre' => $metodo['nombre']],
                $metodo + ['activo' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
