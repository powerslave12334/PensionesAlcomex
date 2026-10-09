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
         // roles: id, name, guard_name, timestamps  -> + descripcion
        Schema::table('roles', function (Blueprint $table) {
            $table->string('descripcion', 255)->nullable()->after('name');
        });

        // permissions: id, name, guard_name, timestamps -> + modulo / accion
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('modulo', 50)->nullable()->after('name')->index();
            $table->string('accion', 30)->nullable()->after('modulo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['modulo']);
            $table->dropColumn(['modulo', 'accion']);
        });
    }
};
