<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
<div id="app" class="d-flex" style="min-height: 100vh;">

    {{-- Sidebar --}}
    <nav class="bg-dark text-white p-3" style="width: 250px;">
        <a href="{{ route('home') }}" class="text-white text-decoration-none fs-5 fw-bold d-block mb-3">
            {{ config('app.name') }}
        </a>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('home') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('home') }}">Dashboard</a>
            </li>

            @can('clientes.ver')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('clientes.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('clientes.index') }}">Clientes</a>
            </li>
            @endcan

            @can('vehiculos.ver')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('vehiculos.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('vehiculos.index') }}">Vehículos</a>
            </li>
            @endcan

            {{-- Los demás módulos se habilitan en sus sprints --}}
            @canany(['espacios.ver', 'zonas.ver', 'tipos_vehiculo.ver'])
            <li class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center
                          {{ request()->routeIs('espacios.*', 'zonas.*', 'tipos-vehiculo.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   data-bs-toggle="collapse" href="#menuEspacios" role="button"
                   aria-expanded="{{ request()->routeIs('espacios.*', 'zonas.*', 'tipos-vehiculo.*') ? 'true' : 'false' }}"
                   aria-controls="menuEspacios">
                    Espacios
                    <span class="small">&#9662;</span>
                </a>
                <div class="collapse {{ request()->routeIs('espacios.*', 'zonas.*', 'tipos-vehiculo.*') ? 'show' : '' }}"
                     id="menuEspacios">
                    <ul class="nav flex-column ps-3">
                        @can('espacios.ver')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('espacios.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                               href="{{ route('espacios.index') }}">Cajones</a>
                        </li>
                        @endcan
                        @can('zonas.ver')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('zonas.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                               href="{{ route('zonas.index') }}">Zonas</a>
                        </li>
                        @endcan
                        @can('tipos_vehiculo.ver')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('tipos-vehiculo.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                               href="{{ route('tipos-vehiculo.index') }}">Tipos de vehículo</a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcanany
            @can('tarifas.ver')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('tarifas.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('tarifas.index') }}">Tarifas</a>
            </li>
            @endcan
            @can('contratos.ver')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('contratos.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                   href="{{ route('contratos.index') }}">Pensiones</a>
            </li>
            @endcan
            @can('pagos.ver')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pagos.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                       href="{{ route('pagos.index') }}">Pagos</a>
                </li>
            @endcan
            @can('accesos.ver')
                <!--<li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('accesos.*') ? 'text-white fw-bold' : 'text-white-50' }}"
                       href="{{ route('accesos.index') }}">Accesos</a>
                </li>-->
            @endcan
            <!--<li class="nav-item"><span class="nav-link disabled text-white-50">Incidencias</span></li>-->
            <li class="nav-item"><span class="nav-link disabled text-white-50">Reportes</span></li>
        </ul>
    </nav>

    {{-- Contenido --}}
    <div class="flex-grow-1 d-flex flex-column">
        <nav class="navbar navbar-expand navbar-light bg-white shadow-sm px-4">
            <span class="navbar-text me-auto fw-semibold">@yield('title')</span>
            @auth
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        {{ Auth::user()->name }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button class="dropdown-item" type="submit">Cerrar sesión</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endauth
        </nav>

        <main class="p-4 flex-grow-1">
            @include('partials.alerts')
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
