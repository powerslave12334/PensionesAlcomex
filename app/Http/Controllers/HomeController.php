<?php

namespace App\Http\Controllers;

use App\Models\ContratoPension;
use App\Models\Espacio;
use App\Models\MovimientoAcceso;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** Panel principal del administrador: estadísticas, gráficos y listados. */
    public function index()
    {
        // US-0307: contratos por vencer (solo quien puede ver contratos).
        $porVencer = Gate::allows('contratos.ver')
            ? ContratoPension::porVencer()->with('cliente')->get()
            : collect();

        // US-0501: vehículos con entrada abierta.
        $adentro = Gate::allows('accesos.ver')
            ? MovimientoAcceso::abiertas()->count()
            : null;

        $stats     = $this->estadisticas();
        $cobrosMes = $this->cobrosPorMes();
        $estados   = $this->contratosPorEstado();
        $recientes = $this->cobrosRecientes();
        $criticos  = $this->contratosCriticos();

        return view('home', compact(
            'porVencer', 'adentro',
            'stats', 'cobrosMes', 'estados',
            'recientes', 'criticos'
        ));
    }

    // ─── Tarjetas de estadísticas ─────────────────────────────────────

    private function estadisticas(): array
    {
        return [
            'total_contratos' => ContratoPension::count(),
            'activos'         => ContratoPension::where('estado', 'ACTIVA')->count(),
            'pendientes'      => ContratoPension::where('estado', 'PENDIENTE')->count(),
            'total_cobros'    => Pago::count(),
            'recaudado'       => Pago::sum('total'),
            'cajones_ocupados'=> Espacio::where('estado', 'OCUPADO')->count(),
        ];
    }

    // ─── Datos para gráficos ──────────────────────────────────────────

    private function cobrosPorMes(): array
    {
        return DB::table('pagos')
            ->selectRaw('DATE_FORMAT(fecha_pago, "%Y-%m") as mes')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('fecha_pago')
            ->where('fecha_pago', '>=', now()->subMonths(6))
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->toArray();
    }

    private function contratosPorEstado(): array
    {
        return ContratoPension::select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();
    }

    // ─── Listados ─────────────────────────────────────────────────────

    private function cobrosRecientes(): array
    {
        return Pago::with(['contrato.cliente', 'metodoPago'])
            ->latest('fecha_pago')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'id'       => $p->id,
                'folio'    => $p->numero_recibo,
                'contrato' => $p->contrato?->numero_contrato ?? '—',
                'cliente'  => $p->contrato?->cliente?->nombre_completo ?? '—',
                'fecha'    => $p->fecha_pago->format('d/m/Y'),
                'total'    => number_format((float) $p->total, 2, '.', ''),
                'metodo'   => $p->metodoPago?->nombre ?? '—',
            ])
            ->toArray();
    }

    private function contratosCriticos(): array
    {
        return ContratoPension::whereIn('estado', ['PENDIENTE', 'VENCIDA', 'SUSPENDIDA'])
            ->with(['cliente', 'vehiculo', 'pagos'])
            ->latest('fecha_inicio')
            ->take(8)
            ->get()
            ->map(fn ($c) => [
                'folio'           => $c->numero_contrato,
                'id'              => $c->id,
                'cliente'         => $c->cliente?->nombre_completo ?? '—',
                'vehiculo'        => $c->vehiculo?->placas ?? '—',
                'estado'          => $c->estado,
                'cobros_pagados'  => $c->pagos->where('estado', 'PAGADO')->count(),
                'fecha_inicio'    => $c->fecha_inicio->format('d/m/Y'),
            ])
            ->toArray();
    }
}