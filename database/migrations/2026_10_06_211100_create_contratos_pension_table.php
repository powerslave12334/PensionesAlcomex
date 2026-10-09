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
        Schema::create('contratos_pension', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->restrictOnDelete();
            $table->foreignId('tarifa_id')
                ->constrained('tarifas')
                ->restrictOnDelete();
            $table->string('numero_contrato', 30)->unique();  // folio
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->decimal('precio', 12, 2);                 // precio congelado; la tarifa puede cambiar despues
            $table->enum('periodicidad', [
                'MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL',
            ])->default('MENSUAL');
            $table->smallInteger('dia_cobro')->nullable();    // dia del mes de cobro
            $table->enum('estado', [
                'PENDIENTE', 'ACTIVA', 'VENCIDA', 'CANCELADA', 'SUSPENDIDA', 'FINALIZADA',
            ])->default('PENDIENTE');
            $table->decimal('deposito', 12, 2)->default(0);   // deposito en garantia
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contratos_pension');
    }
};
