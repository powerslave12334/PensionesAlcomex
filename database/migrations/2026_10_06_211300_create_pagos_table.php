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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_pension_id')
                ->constrained('contratos_pension')
                ->restrictOnDelete();
            $table->foreignId('cliente_id')                  // denormalizado para consulta rapida
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->string('numero_recibo', 30)->unique();
            $table->dateTime('fecha_pago');
            $table->date('periodo_inicio')->nullable();       // periodo que cubre el pago
            $table->date('periodo_fin')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('metodo_pago_id')
                ->constrained('metodos_pago')
                ->restrictOnDelete();
            $table->string('referencia', 100)->nullable();    // transferencia / terminal
            $table->enum('estado', [
                'PENDIENTE', 'PAGADO', 'CANCELADO', 'REEMBOLSADO',
            ])->default('PENDIENTE');
            $table->foreignId('usuario_id')                  // quien registro el cobro
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['periodo_inicio', 'periodo_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
