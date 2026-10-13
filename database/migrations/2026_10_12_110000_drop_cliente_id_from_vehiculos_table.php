<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aún no se "casan" vehículos y clientes: se quita el vínculo de la
     * tabla vehiculos (el contrato sigue ligando cliente + vehículo).
     */
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->foreignId('cliente_id')
                ->nullable()
                ->after('id')
                ->constrained('clientes')
                ->restrictOnDelete();
        });
    }
};
