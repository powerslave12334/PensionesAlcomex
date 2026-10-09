<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->restrictOnDelete();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->foreignId('contrato_pension_id')
                ->nullable()
                ->constrained('contratos_pension')
                ->nullOnDelete();
            $table->foreignId('tipo_incidencia_id')
                ->constrained('tipos_incidencia')
                ->restrictOnDelete();
            $table->dateTime('fecha_hora');
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('ABIERTA'); // ABIERTA / EN PROCESO / CERRADA (VARCHAR en el Excel)
            $table->foreignId('usuario_id')                   // quien la registro
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidencias');
    }
};
