<?php

namespace App\Http\Controllers;

use App\Models\ContratoPension;
use App\Models\MovimientoAcceso;
use Illuminate\Support\Facades\Gate;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // Solo quien puede ver contratos recibe la alerta (US-0307).
        $porVencer = Gate::allows('contratos.ver')
            ? ContratoPension::porVencer()->with('cliente')->get()
            : collect();

        // US-0501: vehículos con entrada abierta (Sprint 5).
        $adentro = Gate::allows('accesos.ver')
            ? MovimientoAcceso::abiertas()->count()
            : null;

        return view('home', compact('porVencer', 'adentro'));
    }
}
