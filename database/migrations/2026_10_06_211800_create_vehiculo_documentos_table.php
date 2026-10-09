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
        Schema::create('vehiculo_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')
                ->constrained('vehiculos')
                ->cascadeOnDelete();
            $table->enum('tipo_documento', [
                'TARJETA_CIRCULACION', 'SEGURO', 'IDENTIFICACION', 'CONTRATO', 'OTRO',
            ]);
            $table->string('numero', 50)->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();   // util para alertas de vencimiento
            $table->string('archivo_url', 500)->nullable();
            $table->string('estado', 30)->default('VIGENTE'); // VIGENTE / VENCIDO (VARCHAR en el Excel)
            $table->timestamps();

            $table->index('fecha_vencimiento');              // alertas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculo_documentos');
    }
};
