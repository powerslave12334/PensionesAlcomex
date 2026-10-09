<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ContratoPensionController;
use App\Http\Controllers\EspacioController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\TarifaController;
use App\Http\Controllers\TipoVehiculoController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\ZonaController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'home' : 'login');
});

Auth::routes(['register' => false]);

Route::middleware(['auth'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Sprint 1
    Route::resource('clientes', ClienteController::class)->except(['show']);
    Route::resource('vehiculos', VehiculoController::class)->except(['show']);

    // Sprint 2 — Módulo 4
    Route::resource('zonas', ZonaController::class)->except(['show']);
    Route::resource('tipos-vehiculo', TipoVehiculoController::class)
        ->parameters(['tipos-vehiculo' => 'tipo'])
        ->except(['show']);
    Route::resource('espacios', EspacioController::class)->except(['show']);
    Route::patch('espacios/{espacio}/estado', [EspacioController::class, 'cambiarEstado'])
        ->name('espacios.estado');

    // Sprint 3 — Módulos 6 y 7
    Route::resource('tarifas', TarifaController::class)->except(['show']);

    Route::resource('contratos', ContratoPensionController::class); // sí incluye show
    Route::post('contratos/{contrato}/estado', [ContratoPensionController::class, 'cambiarEstado'])
        ->name('contratos.estado');
    Route::post('contratos/{contrato}/renovar', [ContratoPensionController::class, 'renovar'])
        ->name('contratos.renovar');
    Route::post('contratos/{contrato}/espacio', [ContratoPensionController::class, 'asignarEspacio'])
        ->name('contratos.espacio');
    Route::delete('contratos/{contrato}/espacio', [ContratoPensionController::class, 'liberarEspacio'])
        ->name('contratos.espacio.destroy');

    // Sprint 4 — Módulo 8
    // ¡Ojo! `pagos/corte` va ANTES del resource: si no, la ruta pagos/{pago}
    // se come «corte» como si fuera un folio.
    Route::get('pagos/corte', [PagoController::class, 'corte'])->name('pagos.corte');
    Route::resource('pagos', PagoController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('pagos/{pago}/estado', [PagoController::class, 'estado'])->name('pagos.estado');

    // Sprint 5 — Módulos 5 y 9 (accesos)
    // ¡Misma lección que pagos/corte! Las rutas estáticas van ANTES del
    // resource: si no, accesos/{acceso} se comería «qr», «rfid», etc.
    Route::get('accesos/qr', [AccesoController::class, 'qr'])->name('accesos.qr');
    Route::get('accesos/rfid', [AccesoController::class, 'rfid'])->name('accesos.rfid');
    Route::post('accesos/rfid', [AccesoController::class, 'leerRfid'])->name('accesos.rfid.leer');
    Route::get('accesos/lpr', [AccesoController::class, 'lpr'])->name('accesos.lpr');
    Route::post('accesos/lpr', [AccesoController::class, 'leerLpr'])->name('accesos.lpr.leer');
    Route::post('accesos/barrera', [AccesoController::class, 'barrera'])->name('accesos.barrera');
    Route::get('accesos/dispositivos', [AccesoController::class, 'dispositivos'])->name('accesos.dispositivos');
    Route::get('accesos/{acceso}/salida', [AccesoController::class, 'salida'])->name('accesos.salida');
    Route::post('accesos/{acceso}/salida', [AccesoController::class, 'cerrarSalida'])->name('accesos.salida.store');
    Route::resource('accesos', AccesoController::class)->only(['index', 'create', 'store', 'show']);
});
