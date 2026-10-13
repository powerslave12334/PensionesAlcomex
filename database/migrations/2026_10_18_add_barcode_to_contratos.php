<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('barcode_data', 50)->default('PAGO:0')->after('fecha_fin');
            $table->enum('barcode_tipo', ['CODE128', 'CODE39', 'QR'])->default('CODE128')->after('barcode_data');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('barcode_data');
            $table->dropColumn('barcode_tipo');
        });
    }
};