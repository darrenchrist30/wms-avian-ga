<!DOCTYPE html>
<html lang="en">
`

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'WMS Avian')</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="{{ asset('adminlte/css/adminlte.min.css') }}">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">

    <style>
        /* Local Fonts */
        @font-face {
            font-family: 'Roboto';
            src: url("{{ asset('fonts/Roboto/Roboto-Light.ttf') }}") format('truetype');
            font-weight: 300;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Roboto';
            src: url("{{ asset('fonts/Roboto/Roboto-Regular.ttf') }}") format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Roboto';
            src: url("{{ asset('fonts/Roboto/Roboto-Medium.ttf') }}") format('truetype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Roboto';
            src: url("{{ asset('fonts/Roboto/Roboto-Bold.ttf') }}") format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Poppins';
            src: url("{{ asset('fonts/Poppins/Poppins-Light.ttf') }}") format('truetype');
            font-weight: 300;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Poppins';
            src: url("{{ asset('fonts/Poppins/Poppins-Regular.ttf') }}") format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Poppins';
            src: url("{{ asset('fonts/Poppins/Poppins-Medium.ttf') }}") format('truetype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Poppins';
            src: url("{{ asset('fonts/Poppins/Poppins-SemiBold.ttf') }}") format('truetype');
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Poppins';
            src: url("{{ asset('fonts/Poppins/Poppins-Bold.ttf') }}") format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --avian-primary: #004230;
            --avian-secondary: #0d8564;
        }

        body {
            font-family: 'Roboto', sans-serif !important;
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        /* Navbar hijau sesuai Avian HRMS */
        .main-header.navbar {
            background: #0d8564 !important;
            border-bottom: none;
            right: 0 !important;
            margin-top: 0 !important;
            top: 0 !important;
            min-height: 3.5rem;
            padding-top: 0;
            padding-bottom: 0;
            width: auto !important;
        }

        .main-header .navbar-nav .nav-link {
            color: white !important;
        }

        .main-header .navbar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Sidebar abu-abu gelap sesuai Avian HRMS */
        .main-sidebar {
            background: #343a40 !important;
        }

        .sidebar-dark-primary .nav-sidebar>.nav-item>.nav-link {
            background-color: transparent;
            color: rgba(255, 255, 255, 0.8);
        }

        .sidebar-dark-primary .nav-sidebar>.nav-item>.nav-link:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #fff;
            margin-left: 0 !important;
        }

        .sidebar-dark-primary .nav-sidebar>.nav-item>.nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        /* Level 1 submenu */
        .sidebar-dark-primary .nav-treeview>.nav-item>.nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding-left: 2rem;
        }

        /* Level 2 submenu (nested treeview) */
        .sidebar-dark-primary .nav-treeview .nav-treeview>.nav-item>.nav-link {
            color: rgba(255, 255, 255, 0.6);
            padding-left: 3.2rem;
            font-size: 0.82rem;
        }

        .sidebar-dark-primary .nav-treeview>.nav-item>.nav-link:hover {
            background-color: rgba(255, 255, 255, 0.03);
            color: #fff;
            margin-left: 0 !important;
        }

        .sidebar-dark-primary .nav-treeview .nav-treeview>.nav-item>.nav-link:hover {
            background-color: rgba(255, 255, 255, 0.03);
            color: #fff;
        }

        .sidebar-dark-primary .nav-treeview>.nav-item>.nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .sidebar-dark-primary .nav-treeview .nav-treeview>.nav-item>.nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        /* Brand link hijau */
        .brand-link {
            background: #0d8564 !important;
            border-bottom: none;
            padding: 0 15px;
            height: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .brand-link i {
            margin-right: 10px;
            font-size: 24px;
        }

        .brand-link .brand-text {
            color: white !important;
            font-weight: 500 !important;
            font-size: 18px;
            display: inline-block;
            margin: 0;
        }

        .content-wrapper {
            background: #ecf0f5;
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #004230 0%, #0d8564 100%);
            color: white;
            border-radius: 8px;
            padding: 35px 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .welcome-card h5 {
            font-size: 15px;
            font-weight: 400;
            margin-bottom: 10px;
            opacity: 0.95;
        }

        .welcome-card h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-card .weather {
            font-size: 15px;
            opacity: 0.9;
        }

        .info-card {
            background: white;
            border-radius: 6px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
            margin-bottom: 20px;
            border-left: 3px solid var(--avian-secondary);
        }

        .info-card h5 {
            color: #2c3e50;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .small-box {
            border-radius: 6px;
        }

        .small-box .inner h3 {
            font-size: 32px;
            font-weight: 700;
        }

        .main-footer a {
            color: #0d8564;
            text-decoration: none;
        }

        .main-footer a:hover {
            color: #004230;
        }

        /* Hide scrollbar */
        .sidebar {
            overflow-y: auto;
            scrollbar-width: none;
            /* Firefox */
            -ms-overflow-style: none;
            /* IE and Edge */
        }

        .sidebar::-webkit-scrollbar {
            display: none;
            /* Chrome, Safari, Opera */
        }

        /* Hide horizontal scrollbar di content wrapper */
        .content-wrapper {
            overflow-x: hidden;
        }

        /* Center icons when sidebar collapsed */
        .sidebar-collapse .nav-sidebar .nav-link {
            text-align: center;
        }

        .sidebar-collapse .nav-sidebar .nav-link p {
            display: none;
        }

        .sidebar-collapse .nav-sidebar .nav-icon {
            margin-right: 0;
        }

        /* Center submenu icons when sidebar collapsed */
        .sidebar-collapse .nav-treeview .nav-link {
            text-align: center;
            padding-left: 0.5rem !important;
        }

        .sidebar-collapse .nav-treeview .nav-icon {
            margin-right: 0;
        }

        /* Hide angle icon when collapsed */
        .sidebar-collapse .nav-link .right {
            display: none;
        }

        /* Prevent hover state from changing position */
        .sidebar-collapse .nav-sidebar .nav-link:hover {
            margin-left: 0 !important;
            padding-left: 0.5rem !important;
        }

        .sidebar-collapse .nav-treeview .nav-link:hover {
            margin-left: 0 !important;
            padding-left: 0.5rem !important;
        }

        .sidebar-collapse .brand-link {
            justify-content: center;
        }

        .sidebar-collapse .brand-link .brand-text {
            display: none;
        }

        .sidebar-collapse .brand-link i {
            margin-right: 0;
        }
    </style>
    @stack('styles')
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i
                            class="fas fa-bars"></i></a>
                </li>
            </ul>

            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fas fa-home"></i>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#"
                        style="text-transform: uppercase;">
                        <i class="fas fa-user-circle"></i>
                        {{ auth()->user()->name ?? 'Guest' }}
                        <small style="font-size:10px;opacity:.75;">
                            ({{ auth()->user()->role->name ?? '-' }})
                        </small>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <div class="dropdown-item-text" style="font-size:12px;color:#6b7280;">
                            {{ auth()->user()->email ?? '' }}
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-user mr-2"></i> Profile
                        </a>
                        <div class="dropdown-divider"></div>
                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </button>
                        </form>
                    </div>
                </li>
            </ul>
        </nav>

        <!-- Sidebar -->
        <aside class="main-sidebar elevation-4 sidebar-dark-avian">
            <a href="/" class="brand-link navbar-avian">
                <img src="{{ asset('images/avian-logo-icon.png') }}" alt="Avian Brands Logo"
                    class="brand-image img-white">
                <span class="brand-text font-weight-light">{{ config('app.name', 'Laravel') }}</span>
            </a>

            <div class="sidebar">
                <nav class="mt-3">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                        data-accordion="true">

                        {{-- ═══════════════════════════════
                             DASHBOARD
                        ═══════════════════════════════ --}}
                        <li class="nav-item">
                            <a href="{{ route('dashboard') }}"
                                class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>

                        {{-- ═══════════════════════════════
                             1. MASTER DATA
                             Semua data referensi/induk
                        ═══════════════════════════════ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">DATA MASTER</li>

                        {{-- 1a. Lokasi Gudang --}}
                        <li class="nav-item {{ request()->is('location*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('location*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-map-marked-alt"></i>
                                <p>Lokasi Gudang <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">

                                {{-- Zona --}}
                                <li class="nav-item {{ request()->is('location/zones*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('location/zones*') ? 'active' : '' }}">
                                        <i class="fas fa-vector-square nav-icon" style="font-size:12px;"></i>
                                        <p>Zona Penyimpanan <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="{{ route('location.zones.index') }}"
                                                class="nav-link {{ request()->routeIs('location.zones.index') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Daftar Zona</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('location.zones.map') ? 'active' : '' }}">
                                                <i class="fas fa-map nav-icon" style="font-size:11px;"></i>
                                                <p>Peta Denah Zona</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('location.zones.capacity') ? 'active' : '' }}">
                                                <i class="fas fa-chart-bar nav-icon" style="font-size:11px;"></i>
                                                <p>Kapasitas per Zona</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Rak --}}
                                <li class="nav-item {{ request()->is('location/racks*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('location/racks*') ? 'active' : '' }}">
                                        <i class="fas fa-th-large nav-icon" style="font-size:12px;"></i>
                                        <p>Rak (Rack) <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('location.racks.index') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Daftar Semua Rak</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('location.racks.by-zone') ? 'active' : '' }}">
                                                <i class="fas fa-filter nav-icon" style="font-size:11px;"></i>
                                                <p>Rak per Zona</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('location.racks.positions') ? 'active' : '' }}">
                                                <i class="fas fa-drafting-compass nav-icon"
                                                    style="font-size:11px;"></i>
                                                <p>Posisi Koordinat Rak</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Cell / Slot --}}
                                <li class="nav-item {{ request()->is('location/cells*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('location/cells*') ? 'active' : '' }}">
                                        <i class="fas fa-border-all nav-icon" style="font-size:12px;"></i>
                                        <p>Sel (Cell / Slot) <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('location.cells.index') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Daftar Semua Cell</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('location.cells.available') ? 'active' : '' }}">
                                                <i class="fas fa-check-circle nav-icon"
                                                    style="font-size:11px;color:#10b981;"></i>
                                                <p>Cell Tersedia</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('location.cells.full') ? 'active' : '' }}">
                                                <i class="fas fa-times-circle nav-icon"
                                                    style="font-size:11px;color:#ef4444;"></i>
                                                <p>Cell Penuh</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('location.cells.quarantine') ? 'active' : '' }}">
                                                <i class="fas fa-exclamation-triangle nav-icon"
                                                    style="font-size:11px;color:#f59e0b;"></i>
                                                <p>Cell Karantina / Hold</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('location.cells.grid') ? 'active' : '' }}">
                                                <i class="fas fa-table nav-icon" style="font-size:11px;"></i>
                                                <p>Grid Cell per Rak</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                            </ul>
                        </li>

                        {{-- 1b. Master Sparepart --}}
                        <li class="nav-item {{ request()->is('master*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('master*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-database"></i>
                                <p>Master Sparepart <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">

                                {{-- Sparepart --}}
                                <li class="nav-item {{ request()->is('master/items*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('master/items*') ? 'active' : '' }}">
                                        <i class="fas fa-cogs nav-icon" style="font-size:12px;"></i>
                                        <p>Sparepart <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="{{ route('master.items.index') }}"
                                                class="nav-link {{ request()->routeIs('master.items.index') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Daftar Semua</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('master.items.fast-moving') ? 'active' : '' }}">
                                                <i class="fas fa-bolt nav-icon"
                                                    style="font-size:11px;color:#f59e0b;"></i>
                                                <p>Fast Moving</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('master.items.slow-moving') ? 'active' : '' }}">
                                                <i class="fas fa-walking nav-icon"
                                                    style="font-size:11px;color:#6366f1;"></i>
                                                <p>Slow Moving</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('master.items.non-moving') ? 'active' : '' }}">
                                                <i class="fas fa-ban nav-icon"
                                                    style="font-size:11px;color:#ef4444;"></i>
                                                <p>Non Moving</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('master.items.low-stock') ? 'active' : '' }}">
                                                <i class="fas fa-exclamation-circle nav-icon"
                                                    style="font-size:11px;color:#f97316;"></i>
                                                <p>Stok di Bawah Minimum</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                <li class="nav-item">
                                    <a href="{{ route('master.categories.index') }}"
                                        class="nav-link {{ request()->routeIs('master.categories*') ? 'active' : '' }}">
                                        <i class="fas fa-tags nav-icon" style="font-size:12px;"></i>
                                        <p>Kategori Sparepart</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('master.units.index') }}"
                                        class="nav-link {{ request()->routeIs('master.units*') ? 'active' : '' }}">
                                        <i class="fas fa-ruler-combined nav-icon" style="font-size:12px;"></i>
                                        <p>Satuan (Unit)</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('master.suppliers.index') }}"
                                        class="nav-link {{ request()->routeIs('master.suppliers*') ? 'active' : '' }}">
                                        <i class="fas fa-industry nav-icon" style="font-size:12px;"></i>
                                        <p>Supplier / Vendor</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        {{-- ═══════════════════════════════
                             2. PENERIMAAN BARANG (INBOUND)
                        ═══════════════════════════════ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">PENERIMAAN
                        </li>

                        <li class="nav-item {{ request()->is('inbound*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('inbound*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-truck-loading"></i>
                                <p>Penerimaan Barang <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">

                                {{-- Surat Jalan --}}
                                <li class="nav-item {{ request()->is('inbound/orders*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('inbound/orders*') ? 'active' : '' }}">
                                        <i class="fas fa-file-import nav-icon" style="font-size:12px;"></i>
                                        <p>Surat Jalan (DO) <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inbound.orders.index') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Semua DO</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inbound.orders.pending') ? 'active' : '' }}">
                                                <i class="fas fa-clock nav-icon"
                                                    style="font-size:11px;color:#f59e0b;"></i>
                                                <p>DO Menunggu Proses</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inbound.orders.done') ? 'active' : '' }}">
                                                <i class="fas fa-check-double nav-icon"
                                                    style="font-size:11px;color:#10b981;"></i>
                                                <p>DO Selesai</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Proses Terima --}}
                                <li class="nav-item {{ request()->is('inbound/receive*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('inbound/receive*') ? 'active' : '' }}">
                                        <i class="fas fa-clipboard-check nav-icon" style="font-size:12px;"></i>
                                        <p>Proses Terima Barang <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inbound.receive.scan') ? 'active' : '' }}">
                                                <i class="fas fa-qrcode nav-icon" style="font-size:11px;"></i>
                                                <p>Scan / Input Barang</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inbound.receive.verify') ? 'active' : '' }}">
                                                <i class="fas fa-search nav-icon" style="font-size:11px;"></i>
                                                <p>Verifikasi Kesesuaian</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inbound.receive.history') ? 'active' : '' }}">
                                                <i class="fas fa-history nav-icon" style="font-size:11px;"></i>
                                                <p>Riwayat Penerimaan</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Put Away --}}
                                <li class="nav-item {{ request()->is('inbound/putaway*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('inbound/putaway*') ? 'active' : '' }}">
                                        <i class="fas fa-map-pin nav-icon" style="font-size:12px;"></i>
                                        <p>Put Away ke Lokasi <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inbound.putaway.pending') ? 'active' : '' }}">
                                                <i class="fas fa-tasks nav-icon"
                                                    style="font-size:11px;color:#f59e0b;"></i>
                                                <p>Antrian Put Away</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inbound.putaway.execute') ? 'active' : '' }}">
                                                <i class="fas fa-arrow-right nav-icon" style="font-size:11px;"></i>
                                                <p>Eksekusi Penempatan</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inbound.putaway.history') ? 'active' : '' }}">
                                                <i class="fas fa-history nav-icon" style="font-size:11px;"></i>
                                                <p>Riwayat Put Away</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                            </ul>
                        </li>

                        {{-- ═══════════════════════════════
                             3. OPTIMASI GA (FITUR UTAMA)
                        ═══════════════════════════════ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">OPTIMASI GA
                        </li>

                        <li class="nav-item {{ request()->is('ga*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('ga*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-brain"></i>
                                <p>Optimasi Penempatan <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">

                                {{-- Rekomendasi --}}
                                <li class="nav-item {{ request()->is('ga/recommend*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('ga/recommend*') ? 'active' : '' }}">
                                        <i class="fas fa-magic nav-icon" style="font-size:12px;"></i>
                                        <p>Rekomendasi <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('ga.recommend.latest') ? 'active' : '' }}">
                                                <i class="fas fa-star nav-icon"
                                                    style="font-size:11px;color:#f59e0b;"></i>
                                                <p>Rekomendasi Terbaru</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('ga.recommend.detail') ? 'active' : '' }}">
                                                <i class="fas fa-search-plus nav-icon" style="font-size:11px;"></i>
                                                <p>Detail per Sparepart</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('ga.recommend.approve') ? 'active' : '' }}">
                                                <i class="fas fa-thumbs-up nav-icon"
                                                    style="font-size:11px;color:#10b981;"></i>
                                                <p>Setujui Rekomendasi</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Jalankan GA --}}
                                <li class="nav-item {{ request()->is('ga/run*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('ga/run*') ? 'active' : '' }}">
                                        <i class="fas fa-play-circle nav-icon" style="font-size:12px;"></i>
                                        <p>Jalankan GA <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('ga.run.new') ? 'active' : '' }}">
                                                <i class="fas fa-rocket nav-icon"
                                                    style="font-size:11px;color:#6366f1;"></i>
                                                <p>Optimasi DO Baru</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('ga.run.reoptimize') ? 'active' : '' }}">
                                                <i class="fas fa-redo nav-icon" style="font-size:11px;"></i>
                                                <p>Ulang Optimasi</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('ga.run.status') ? 'active' : '' }}">
                                                <i class="fas fa-spinner nav-icon" style="font-size:11px;"></i>
                                                <p>Status Proses GA</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Analisis Hasil --}}
                                <li class="nav-item {{ request()->is('ga/analysis*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('ga/analysis*') ? 'active' : '' }}">
                                        <i class="fas fa-chart-line nav-icon" style="font-size:12px;"></i>
                                        <p>Analisis Hasil GA <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('ga.analysis.convergence') ? 'active' : '' }}">
                                                <i class="fas fa-chart-area nav-icon" style="font-size:11px;"></i>
                                                <p>Grafik Konvergensi</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('ga.analysis.fitness') ? 'active' : '' }}">
                                                <i class="fas fa-tachometer-alt nav-icon" style="font-size:11px;"></i>
                                                <p>Nilai Fitness</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('ga.analysis.compare') ? 'active' : '' }}">
                                                <i class="fas fa-balance-scale nav-icon" style="font-size:11px;"></i>
                                                <p>Perbandingan Sebelum/Sesudah</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                <li class="nav-item">
                                    <a href="#"
                                        class="nav-link {{ request()->routeIs('ga.parameters*') ? 'active' : '' }}">
                                        <i class="fas fa-sliders-h nav-icon" style="font-size:12px;"></i>
                                        <p>Parameter GA</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#"
                                        class="nav-link {{ request()->routeIs('ga.history*') ? 'active' : '' }}">
                                        <i class="fas fa-history nav-icon" style="font-size:12px;"></i>
                                        <p>Riwayat Optimasi</p>
                                    </a>
                                </li>

                            </ul>
                        </li>

                        {{-- ═══════════════════════════════
                             4. INVENTORI & STOK
                        ═══════════════════════════════ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">INVENTORI</li>

                        <li class="nav-item {{ request()->is('inventory*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('inventory*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-boxes"></i>
                                <p>Manajemen Stok <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">

                                {{-- Stok Saat Ini --}}
                                <li class="nav-item {{ request()->is('inventory/stocks*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('inventory/stocks*') ? 'active' : '' }}">
                                        <i class="fas fa-cubes nav-icon" style="font-size:12px;"></i>
                                        <p>Stok Saat Ini <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.stocks.all') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Semua Stok</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.stocks.by-zone') ? 'active' : '' }}">
                                                <i class="fas fa-layer-group nav-icon" style="font-size:11px;"></i>
                                                <p>Stok per Zona</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.stocks.by-category') ? 'active' : '' }}">
                                                <i class="fas fa-tags nav-icon" style="font-size:11px;"></i>
                                                <p>Stok per Kategori</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.stocks.location') ? 'active' : '' }}">
                                                <i class="fas fa-map-marker-alt nav-icon" style="font-size:11px;"></i>
                                                <p>Posisi Stok per Cell</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Mutasi Stok --}}
                                <li class="nav-item {{ request()->is('inventory/movements*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('inventory/movements*') ? 'active' : '' }}">
                                        <i class="fas fa-dolly nav-icon" style="font-size:12px;"></i>
                                        <p>Mutasi Stok <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.movements.all') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Semua Mutasi</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.movements.inbound') ? 'active' : '' }}">
                                                <i class="fas fa-arrow-down nav-icon"
                                                    style="font-size:11px;color:#10b981;"></i>
                                                <p>Barang Masuk</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.movements.outbound') ? 'active' : '' }}">
                                                <i class="fas fa-arrow-up nav-icon"
                                                    style="font-size:11px;color:#ef4444;"></i>
                                                <p>Barang Keluar</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.movements.transfer') ? 'active' : '' }}">
                                                <i class="fas fa-exchange-alt nav-icon"
                                                    style="font-size:11px;color:#6366f1;"></i>
                                                <p>Transfer Antar Lokasi</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Stock Opname --}}
                                <li class="nav-item {{ request()->is('inventory/opname*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('inventory/opname*') ? 'active' : '' }}">
                                        <i class="fas fa-clipboard-list nav-icon" style="font-size:12px;"></i>
                                        <p>Stock Opname <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.opname.create') ? 'active' : '' }}">
                                                <i class="fas fa-plus-circle nav-icon"
                                                    style="font-size:11px;color:#10b981;"></i>
                                                <p>Buat Opname Baru</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.opname.active') ? 'active' : '' }}">
                                                <i class="fas fa-pen nav-icon"
                                                    style="font-size:11px;color:#f59e0b;"></i>
                                                <p>Opname Berjalan</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.opname.history') ? 'active' : '' }}">
                                                <i class="fas fa-history nav-icon" style="font-size:11px;"></i>
                                                <p>Riwayat Opname</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.opname.adjustment') ? 'active' : '' }}">
                                                <i class="fas fa-balance-scale nav-icon" style="font-size:11px;"></i>
                                                <p>Penyesuaian Stok</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Deadstock --}}
                                <li class="nav-item {{ request()->is('inventory/deadstock*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('inventory/deadstock*') ? 'active' : '' }}">
                                        <i class="fas fa-hourglass-end nav-icon" style="font-size:12px;"></i>
                                        <p>Deadstock <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.deadstock.list') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Daftar Deadstock</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.deadstock.threshold') ? 'active' : '' }}">
                                                <i class="fas fa-sliders-h nav-icon" style="font-size:11px;"></i>
                                                <p>Atur Threshold</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('inventory.deadstock.action') ? 'active' : '' }}">
                                                <i class="fas fa-tools nav-icon" style="font-size:11px;"></i>
                                                <p>Tindakan Deadstock</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                <li class="nav-item">
                                    <a href="#"
                                        class="nav-link {{ request()->routeIs('inventory.expiry*') ? 'active' : '' }}">
                                        <i class="fas fa-calendar-times nav-icon" style="font-size:12px;"></i>
                                        <p>Mendekati Kadaluarsa</p>
                                    </a>
                                </li>

                            </ul>
                        </li>

                        {{-- ═══════════════════════════════
                             5. PERMINTAAN SPAREPART (OUTBOUND)
                        ═══════════════════════════════ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">PENGELUARAN
                        </li>

                        <li class="nav-item {{ request()->is('outbound*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('outbound*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-hand-holding-box"></i>
                                <p>Permintaan Sparepart <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">

                                {{-- Permintaan --}}
                                <li class="nav-item {{ request()->is('outbound/requests*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('outbound/requests*') ? 'active' : '' }}">
                                        <i class="fas fa-file-medical nav-icon" style="font-size:12px;"></i>
                                        <p>Permintaan <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('outbound.requests.create') ? 'active' : '' }}">
                                                <i class="fas fa-plus-circle nav-icon"
                                                    style="font-size:11px;color:#10b981;"></i>
                                                <p>Buat Permintaan Baru</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('outbound.requests.pending') ? 'active' : '' }}">
                                                <i class="fas fa-clock nav-icon"
                                                    style="font-size:11px;color:#f59e0b;"></i>
                                                <p>Menunggu Persetujuan</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('outbound.requests.approved') ? 'active' : '' }}">
                                                <i class="fas fa-check nav-icon"
                                                    style="font-size:11px;color:#10b981;"></i>
                                                <p>Disetujui / Siap Picking</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('outbound.requests.all') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Semua Permintaan</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Picking --}}
                                <li class="nav-item {{ request()->is('outbound/picking*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('outbound/picking*') ? 'active' : '' }}">
                                        <i class="fas fa-hand-pointer nav-icon" style="font-size:12px;"></i>
                                        <p>Proses Picking <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('outbound.picking.active') ? 'active' : '' }}">
                                                <i class="fas fa-play nav-icon"
                                                    style="font-size:11px;color:#6366f1;"></i>
                                                <p>Picking Aktif</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('outbound.picking.confirm') ? 'active' : '' }}">
                                                <i class="fas fa-qrcode nav-icon" style="font-size:11px;"></i>
                                                <p>Konfirmasi Pengambilan</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('outbound.picking.done') ? 'active' : '' }}">
                                                <i class="fas fa-check-circle nav-icon"
                                                    style="font-size:11px;color:#10b981;"></i>
                                                <p>Picking Selesai</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                <li class="nav-item">
                                    <a href="#"
                                        class="nav-link {{ request()->routeIs('outbound.history*') ? 'active' : '' }}">
                                        <i class="fas fa-sign-out-alt nav-icon" style="font-size:12px;"></i>
                                        <p>Riwayat Pengeluaran</p>
                                    </a>
                                </li>

                            </ul>
                        </li>

                        {{-- ═══════════════════════════════
                             6. VISUALISASI 3D
                        ═══════════════════════════════ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">VISUALISASI
                        </li>

                        <li class="nav-item {{ request()->is('warehouse/3d*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('warehouse/3d*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-cube"></i>
                                <p>Denah Gudang 3D <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="#"
                                        class="nav-link {{ request()->routeIs('warehouse.3d.full') ? 'active' : '' }}">
                                        <i class="fas fa-expand nav-icon" style="font-size:12px;"></i>
                                        <p>View Keseluruhan Gudang</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#"
                                        class="nav-link {{ request()->routeIs('warehouse.3d.zone') ? 'active' : '' }}">
                                        <i class="fas fa-vector-square nav-icon" style="font-size:12px;"></i>
                                        <p>View per Zona</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#"
                                        class="nav-link {{ request()->routeIs('warehouse.3d.placement') ? 'active' : '' }}">
                                        <i class="fas fa-map-marker-alt nav-icon" style="font-size:12px;"></i>
                                        <p>Penempatan Sparepart</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#"
                                        class="nav-link {{ request()->routeIs('warehouse.3d.heatmap') ? 'active' : '' }}">
                                        <i class="fas fa-fire nav-icon" style="font-size:12px;color:#ef4444;"></i>
                                        <p>Heatmap Utilisasi</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        {{-- ═══════════════════════════════
                             7. LAPORAN & ANALITIK
                        ═══════════════════════════════ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">LAPORAN</li>

                        <li class="nav-item {{ request()->is('reports*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('reports*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-chart-bar"></i>
                                <p>Laporan <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">

                                {{-- Laporan Stok --}}
                                <li class="nav-item {{ request()->is('reports/stock*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('reports/stock*') ? 'active' : '' }}">
                                        <i class="fas fa-file-alt nav-icon" style="font-size:12px;"></i>
                                        <p>Laporan Stok <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.stock.current') ? 'active' : '' }}">
                                                <i class="fas fa-cubes nav-icon" style="font-size:11px;"></i>
                                                <p>Posisi Stok Terkini</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.stock.minimum') ? 'active' : '' }}">
                                                <i class="fas fa-exclamation nav-icon"
                                                    style="font-size:11px;color:#f97316;"></i>
                                                <p>Stok di Bawah Minimum</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.stock.valuation') ? 'active' : '' }}">
                                                <i class="fas fa-coins nav-icon" style="font-size:11px;"></i>
                                                <p>Valuasi Stok</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Laporan Penerimaan --}}
                                <li class="nav-item {{ request()->is('reports/inbound*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('reports/inbound*') ? 'active' : '' }}">
                                        <i class="fas fa-file-import nav-icon" style="font-size:12px;"></i>
                                        <p>Laporan Penerimaan <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.inbound.period') ? 'active' : '' }}">
                                                <i class="fas fa-calendar nav-icon" style="font-size:11px;"></i>
                                                <p>Per Periode</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.inbound.supplier') ? 'active' : '' }}">
                                                <i class="fas fa-industry nav-icon" style="font-size:11px;"></i>
                                                <p>Per Supplier</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.inbound.category') ? 'active' : '' }}">
                                                <i class="fas fa-tags nav-icon" style="font-size:11px;"></i>
                                                <p>Per Kategori</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Laporan Pengeluaran --}}
                                <li class="nav-item {{ request()->is('reports/outbound*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('reports/outbound*') ? 'active' : '' }}">
                                        <i class="fas fa-file-export nav-icon" style="font-size:12px;"></i>
                                        <p>Laporan Pengeluaran <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.outbound.period') ? 'active' : '' }}">
                                                <i class="fas fa-calendar nav-icon" style="font-size:11px;"></i>
                                                <p>Per Periode</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.outbound.department') ? 'active' : '' }}">
                                                <i class="fas fa-building nav-icon" style="font-size:11px;"></i>
                                                <p>Per Divisi / Departemen</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.outbound.category') ? 'active' : '' }}">
                                                <i class="fas fa-tags nav-icon" style="font-size:11px;"></i>
                                                <p>Per Kategori Sparepart</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Laporan Kinerja GA (PENTING untuk skripsi) --}}
                                <li class="nav-item {{ request()->is('reports/ga*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('reports/ga*') ? 'active' : '' }}">
                                        <i class="fas fa-brain nav-icon" style="font-size:12px;"></i>
                                        <p>Laporan Kinerja GA <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.ga.fitness') ? 'active' : '' }}">
                                                <i class="fas fa-chart-line nav-icon"
                                                    style="font-size:11px;color:#6366f1;"></i>
                                                <p>Grafik Nilai Fitness</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.ga.comparison') ? 'active' : '' }}">
                                                <i class="fas fa-balance-scale nav-icon" style="font-size:11px;"></i>
                                                <p>Sebelum vs Sesudah GA</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.ga.convergence') ? 'active' : '' }}">
                                                <i class="fas fa-chart-area nav-icon" style="font-size:11px;"></i>
                                                <p>Konvergensi Algoritma</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.ga.export') ? 'active' : '' }}">
                                                <i class="fas fa-file-download nav-icon" style="font-size:11px;"></i>
                                                <p>Export Hasil (PDF/Excel)</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Utilisasi --}}
                                <li class="nav-item {{ request()->is('reports/utilization*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('reports/utilization*') ? 'active' : '' }}">
                                        <i class="fas fa-chart-pie nav-icon" style="font-size:12px;"></i>
                                        <p>Utilisasi Kapasitas <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.utilization.zone') ? 'active' : '' }}">
                                                <i class="fas fa-vector-square nav-icon" style="font-size:11px;"></i>
                                                <p>Per Zona</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.utilization.rack') ? 'active' : '' }}">
                                                <i class="fas fa-th-large nav-icon" style="font-size:11px;"></i>
                                                <p>Per Rak</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('reports.utilization.trend') ? 'active' : '' }}">
                                                <i class="fas fa-chart-line nav-icon" style="font-size:11px;"></i>
                                                <p>Tren Waktu</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                <li class="nav-item">
                                    <a href="#"
                                        class="nav-link {{ request()->routeIs('reports.deadstock*') ? 'active' : '' }}">
                                        <i class="fas fa-file-times nav-icon" style="font-size:12px;"></i>
                                        <p>Laporan Deadstock</p>
                                    </a>
                                </li>

                            </ul>
                        </li>

                        {{-- ═══════════════════════════════
                             8. PENGATURAN SISTEM
                        ═══════════════════════════════ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">SISTEM</li>

                        <li class="nav-item {{ request()->is('settings*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('settings*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-cog"></i>
                                <p>Pengaturan <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">

                                {{-- User Management --}}
                                <li class="nav-item {{ request()->is('settings/users*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('settings/users*') ? 'active' : '' }}">
                                        <i class="fas fa-users nav-icon" style="font-size:12px;"></i>
                                        <p>Manajemen User <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('settings.users.index') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Daftar User</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('settings.users.create') ? 'active' : '' }}">
                                                <i class="fas fa-user-plus nav-icon"
                                                    style="font-size:11px;color:#10b981;"></i>
                                                <p>Tambah User Baru</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('settings.users.inactive') ? 'active' : '' }}">
                                                <i class="fas fa-user-slash nav-icon"
                                                    style="font-size:11px;color:#ef4444;"></i>
                                                <p>User Nonaktif</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Role & Permission --}}
                                <li class="nav-item {{ request()->is('settings/roles*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('settings/roles*') ? 'active' : '' }}">
                                        <i class="fas fa-user-shield nav-icon" style="font-size:12px;"></i>
                                        <p>Role &amp; Hak Akses <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('settings.roles.index') ? 'active' : '' }}">
                                                <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                                <p>Daftar Role</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('settings.permissions.index') ? 'active' : '' }}">
                                                <i class="fas fa-key nav-icon" style="font-size:11px;"></i>
                                                <p>Kelola Permission</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('settings.roles.assign') ? 'active' : '' }}">
                                                <i class="fas fa-user-tag nav-icon" style="font-size:11px;"></i>
                                                <p>Assign Role ke User</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                                {{-- Log Aktivitas --}}
                                <li class="nav-item {{ request()->is('settings/log*') ? 'menu-open' : '' }}">
                                    <a href="#"
                                        class="nav-link {{ request()->is('settings/log*') ? 'active' : '' }}">
                                        <i class="fas fa-scroll nav-icon" style="font-size:12px;"></i>
                                        <p>Log Aktivitas <i class="right fas fa-angle-left"></i></p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left:.8rem;">
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('settings.log.system') ? 'active' : '' }}">
                                                <i class="fas fa-server nav-icon" style="font-size:11px;"></i>
                                                <p>Log Sistem</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('settings.log.login') ? 'active' : '' }}">
                                                <i class="fas fa-sign-in-alt nav-icon" style="font-size:11px;"></i>
                                                <p>Log Login User</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#"
                                                class="nav-link {{ request()->routeIs('settings.log.export') ? 'active' : '' }}">
                                                <i class="fas fa-file-download nav-icon" style="font-size:11px;"></i>
                                                <p>Export Log</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>

                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <section class="content">
                <div class="container-fluid pt-3">
                    @yield('content')
                </div>
            </section>
        </div>

        <!-- Footer -->
        <footer class="main-footer">
            <strong>Copyright &copy; 2025 <a href="https://avianbrands.com" target="_blank">Avian Brands</a>.</strong>
            All rights reserved.
            <div class="float-right d-none d-sm-inline-block">
                <b>Version</b> 1.0.0
            </div>
        </footer>
    </div>

    <!-- jQuery -->
    <script src="{{ asset('adminlte/plugins/jquery/jquery.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    <script src="{{ asset('adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('adminlte/js/adminlte.min.js') }}"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @stack('scripts')
</body>

</html>
