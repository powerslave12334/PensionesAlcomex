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
       Schema::create('tarifas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);                   // Ej. Mensualidad automovil
            $table->foreignId('tipo_vehiculo_id')
                ->constrained('tipos_vehiculo')
                ->restrictOnDelete();
            $table->enum('tipo_tarifa', [
                'HORA', 'DIA', 'NOCHE', 'MENSUAL', 'ANUAL', 'EVENTO',
            ]);
            $table->decimal('precio', 12, 2);                // auto $1500; moto $800; hora $25; dia $180
            $table->string('periodo', 30)->nullable();
            $table->time('hora_inicio')->nullable();         // tarifas nocturnas
            $table->time('hora_fin')->nullable();
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tipo_vehiculo_id', 'vigencia_desde', 'vigencia_hasta']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarifas');
    }
};
