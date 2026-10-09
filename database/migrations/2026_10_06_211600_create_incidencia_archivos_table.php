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
        Schema::create('incidencia_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')
                ->constrained('incidencias')
                ->cascadeOnDelete();
            $table->string('tipo_archivo', 30)->default('FOTO'); // FOTO / VIDEO / DOCUMENTO
            $table->string('url', 500);                          // S3 / MinIO
            $table->string('nombre_archivo', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();                                // Excel solo trae created_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidencia_archivos');
    }
};
