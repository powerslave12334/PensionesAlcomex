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
        Schema::create('movimientos_acceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->restrictOnDelete();
            $table->foreignId('cliente_id')                  // propietario al momento del acceso
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->foreignId('contrato_pension_id')         // NULL si es visitante
                ->nullable()
                ->constrained('contratos_pension')
                ->nullOnDelete();
            $table->foreignId('espacio_id')                  // cajon usado
                ->nullable()
                ->constrained('espacios')
                ->nullOnDelete();
            $table->enum('tipo', ['ENTRADA', 'SALIDA']);
            $table->dateTime('fecha_hora');
            $table->string('placas_detectadas', 20)->nullable();  // lectura LPR / manual
            $table->foreignId('usuario_id')                  // quien registro la operacion
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('puerta_id')                   // FK a Fase 2 (ya creada antes)
                ->nullable()
                ->constrained('puertas')
                ->nullOnDelete();
            $table->string('metodo_acceso', 30)->default('MANUAL'); // MANUAL / QR / RFID / LPR
            $table->string('foto_entrada', 500)->nullable();  // evidencia
            $table->string('foto_salida', 500)->nullable();   // evidencia
            $table->text('observaciones')->nullable();
            $table->timestamps();                            // Excel solo trae created_at

            $table->index(['vehiculo_id', 'fecha_hora']);
            $table->index(['fecha_hora']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_acceso');
    }
};
