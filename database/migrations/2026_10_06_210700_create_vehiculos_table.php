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
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('placas', 20)->unique();
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->smallInteger('anio')->nullable();
            $table->string('color', 50)->nullable();
            $table->foreignId('tipo_vehiculo_id')->constrained('tipos_vehiculo')->restrictOnDelete();
            $table->string('vin', 50)->nullable()->index();
            $table->string('numero_motor', 100)->nullable();
            $table->string('foto_url', 500)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
