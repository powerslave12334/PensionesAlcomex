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
         Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->enum('tipo', [
                'CAMARA', 'LECTOR_RFID', 'LECTOR_QR', 'LECTOR_PLACAS',
                'BARRERA', 'SENSOR', 'IMPRESORA',
            ]);
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('numero_serie', 80)->nullable();
            $table->string('ip', 45)->nullable();           // IPv4/IPv6
            $table->string('mac', 20)->nullable();
            $table->string('ubicacion', 150)->nullable();
            $table->string('estado', 30)->default('ACTIVO'); // ACTIVO / INACTIVO / FALLA  (VARCHAR en el Excel)
            $table->json('configuracion')->nullable();       // JSON de configuracion
            $table->dateTime('ultimo_contacto')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispositivos');
    }
};
