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
        Schema::create('reservaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->restrictOnDelete();
            $table->foreignId('espacio_id')
                ->constrained('espacios')
                ->restrictOnDelete();
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->enum('estado', [
                'PENDIENTE', 'CONFIRMADA', 'CANCELADA', 'FINALIZADA',
            ])->default('PENDIENTE');
            $table->decimal('precio', 12, 2)->default(0);
            $table->foreignId('usuario_id')                   // quien la creo
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['espacio_id', 'fecha_inicio', 'fecha_fin']); // evitar doble reserva
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservaciones');
    }
};
