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
        Schema::create('asignaciones_espacio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_pension_id')
                ->constrained('contratos_pension')
                ->cascadeOnDelete();
            $table->foreignId('espacio_id')
                ->constrained('espacios')
                ->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();            // NULL = asignacion vigente
            $table->string('motivo', 255)->nullable();        // cambio de cajon, mantenimiento, etc.
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['espacio_id', 'fecha_fin']);       // buscar la asignacion vigente del cajon
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones_espacio');
    }
};
