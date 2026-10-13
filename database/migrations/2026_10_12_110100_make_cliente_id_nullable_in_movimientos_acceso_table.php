<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Con la columna cliente_id fuera de vehiculos, las entradas de
     * visitantes (sin contrato) ya no tienen cliente que copiar: el campo
     * pasa a ser opcional en movimientos_acceso.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE movimientos_acceso MODIFY cliente_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE movimientos_acceso MODIFY cliente_id BIGINT UNSIGNED NOT NULL');
    }
};
