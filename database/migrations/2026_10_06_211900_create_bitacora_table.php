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
       Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')                  // se conserva el historial aunque el usuario baje
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('accion', 30);                    // CREAR / ACTUALIZAR / ELIMINAR / LOGIN
            $table->string('tabla', 60);                     // tabla afectada
            $table->unsignedBigInteger('registro_id')->nullable(); // ID del registro afectado
            $table->json('datos_anteriores')->nullable();     // Ej. precio_anterior: 1500
            $table->json('datos_nuevos')->nullable();         // Ej. precio_nuevo: 1700
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();                            // Excel solo trae created_at

            $table->index(['tabla', 'registro_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
