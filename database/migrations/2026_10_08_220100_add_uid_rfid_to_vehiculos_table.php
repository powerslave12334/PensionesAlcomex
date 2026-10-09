<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * US-0504: UID de la tarjeta RFID pegada al parabrisas.
     * En una integración real lo escribe el lector; aquí lo carga el alta
     * del vehículo y lo usa el formulario mock de RFID.
     */
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->string('uid_rfid', 40)
                ->nullable()
                ->unique()
                ->after('placas');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropUnique(['uid_rfid']);
            $table->dropColumn('uid_rfid');
        });
    }
};
