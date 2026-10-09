<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ZonaSeeder::class,
            TipoVehiculoSeeder::class,
            TarifaSeeder::class,
            MetodoPagoSeeder::class,   // ← nuevo (Sprint 4)
            DispositivoSeeder::class,  // ← nuevo (Sprint 5)
            PuertaSeeder::class,       // ← nuevo (Sprint 5) — requiere dispositivos
            RolPermisoSeeder::class,
        ]);
    }
}
