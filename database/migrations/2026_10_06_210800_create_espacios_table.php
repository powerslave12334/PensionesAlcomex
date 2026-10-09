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
        Schema::create('espacios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_id')
                ->constrained('zonas')
                ->restrictOnDelete();
            $table->string('codigo', 30);                    // codigo del cajon
            $table->foreignId('tipo_vehiculo_id')
                ->constrained('tipos_vehiculo')
                ->restrictOnDelete();
            $table->enum('estado', [
                'DISPONIBLE', 'OCUPADO', 'RESERVADO', 'MANTENIMIENTO', 'BLOQUEADO',
            ])->default('DISPONIBLE');
            $table->string('numero', 20)->nullable();
            $table->string('piso', 20)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['zona_id', 'codigo']);           // UNIQUE(zona_id, codigo)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('espacios');
    }
};
