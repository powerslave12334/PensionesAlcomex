<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 5 (US-0502): datos de la SALIDA que cierra una ENTRADA.
     * Tabla ya poblada por el Sprint 0; aquí solo se agregan columnas.
     */
    public function up(): void
    {
        Schema::table('movimientos_acceso', function (Blueprint $table) {
            $table->foreignId('entrada_id')
                ->nullable()
                ->after('puerta_id')
                ->constrained('movimientos_acceso')
                ->nullOnDelete();

            $table->unsignedInteger('permanencia_minutos')
                ->nullable()
                ->after('entrada_id');

            $table->foreignId('tarifa_id')
                ->nullable()
                ->after('permanencia_minutos')
                ->constrained('tarifas')
                ->nullOnDelete();

            $table->decimal('cobro', 12, 2)->default(0)->after('tarifa_id');

            $table->string('estado_cobro', 15)
                ->default('NO_APLICA')
                ->after('cobro');

            $table->foreignId('metodo_pago_id')
                ->nullable()
                ->after('estado_cobro')
                ->constrained('metodos_pago')
                ->nullOnDelete();

            $table->index(['estado_cobro']);
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_acceso', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entrada_id');
            $table->dropConstrainedForeignId('tarifa_id');
            $table->dropConstrainedForeignId('metodo_pago_id');
            $table->dropColumn(['permanencia_minutos', 'cobro', 'estado_cobro']);
        });
    }
};
