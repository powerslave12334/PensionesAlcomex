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
       Schema::create('puertas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);                    // Entrada principal, Salida principal, Empleados...
            $table->string('ubicacion', 150)->nullable();
            $table->string('tipo', 30)->default('ENTRADA');  // ENTRADA / SALIDA
            $table->foreignId('dispositivo_id')              // barrera/camara asociada
                ->nullable()
                ->constrained('dispositivos')
                ->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puertas');
    }
};
