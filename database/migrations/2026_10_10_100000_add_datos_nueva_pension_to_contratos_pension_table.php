<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajuste del alta de pensiones: datos de la empresa que paga la unidad,
     * fecha y hora de entrada (automática, editable) y la documentación
     * entregada por el cliente (una sola de las tres opciones).
     */
    public function up(): void
    {
        Schema::table('contratos_pension', function (Blueprint $table) {
            $table->string('nombre_empresa', 150)->nullable()->after('cliente_id');
            $table->string('contacto_empresa', 100)->nullable()->after('nombre_empresa');
            $table->string('direccion_empresa', 255)->nullable()->after('contacto_empresa');
            $table->dateTime('fecha_entrada')->nullable()->after('fecha_inicio');
            $table->enum('documentacion', [
                'INE', 'TARJETA_CIRCULACION', 'LICENCIA_CONDUCIR',
            ])->nullable()->after('deposito');
        });
    }

    public function down(): void
    {
        Schema::table('contratos_pension', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_empresa', 'contacto_empresa', 'direccion_empresa',
                'fecha_entrada', 'documentacion',
            ]);
        });
    }
};
