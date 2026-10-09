<?php

namespace Database\Seeders;

use App\Models\Tarifa;
use App\Models\TipoVehiculo;
use Illuminate\Database\Seeder;

class TarifaSeeder extends Seeder
{
    /** @var array<int, array{nombre:string, tipo:string, periodo:string, precio:float, extra?:array}> */
    private array $tarifas = [
        ['nombre' => 'Mensualidad automovil',  'tipo' => 'Automovil',   'periodo' => 'por mes',      'precio' => 1500.00],
        ['nombre' => 'Mensualidad motocicleta','tipo' => 'Motocicleta', 'periodo' => 'por mes',      'precio' => 800.00],
        ['nombre' => 'Mensualidad SUV',        'tipo' => 'SUV',         'periodo' => 'por mes',      'precio' => 1800.00],
        ['nombre' => 'Mensualidad camioneta',  'tipo' => 'Camioneta',   'periodo' => 'por mes',      'precio' => 1800.00],
        ['nombre' => 'Mensualidad camion',     'tipo' => 'Camion',      'periodo' => 'por mes',      'precio' => 2500.00],
        ['nombre' => 'Hora automovil',         'tipo' => 'Automovil',   'periodo' => 'por hora',     'precio' => 25.00,
         'extra' => ['tipo_tarifa' => 'HORA']],
        ['nombre' => 'Dia automovil',          'tipo' => 'Automovil',   'periodo' => 'por dia',      'precio' => 180.00,
         'extra' => ['tipo_tarifa' => 'DIA']],
        ['nombre' => 'Noche automovil',        'tipo' => 'Automovil',   'periodo' => '22:00 a 06:00','precio' => 120.00,
         'extra' => ['tipo_tarifa' => 'NOCHE', 'hora_inicio' => '22:00', 'hora_fin' => '06:00']],
        ['nombre' => 'Anual automovil',        'tipo' => 'Automovil',   'periodo' => 'por anio',     'precio' => 15000.00,
         'extra' => ['tipo_tarifa' => 'ANUAL']],
        ['nombre' => 'Evento corta estancia',  'tipo' => 'Automovil',   'periodo' => 'por evento',   'precio' => 50.00,
         'extra' => ['tipo_tarifa' => 'EVENTO']],
    ];

    public function run(): void
    {
        foreach ($this->tarifas as $fila) {
            $tipo = TipoVehiculo::where('nombre', $fila['tipo'])->first();

            if (! $tipo) {
                $this->command?->warn("  [omitida] Falta el tipo de vehiculo: {$fila['tipo']}");
                continue;
            }

            Tarifa::updateOrInsert(
                ['nombre' => $fila['nombre'], 'tipo_vehiculo_id' => $tipo->id],
                array_merge([
                    'tipo_tarifa'    => 'MENSUAL',
                    'precio'         => $fila['precio'],
                    'periodo'        => $fila['periodo'],
                    'vigencia_desde' => null,
                    'vigencia_hasta' => null,
                    'activo'         => true,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ], $fila['extra'] ?? [])
            );
        }

        $this->command?->info('  Tarifas: ' . Tarifa::count() . ' registradas.');
    }
}
