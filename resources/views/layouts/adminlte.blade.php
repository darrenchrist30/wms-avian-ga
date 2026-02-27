<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'WMS Avian GA')</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="{{ asset('adminlte/css/adminlte.min.css') }}">

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
        }

        /* Navbar hijau sesuai Avian HRMS */
        .main-header.navbar {
            background: #0d8564 !important;
            border-bottom: none;
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

        .sidebar-dark-primary .nav-treeview>.nav-item>.nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding-left: 2rem;
        }

        .sidebar-dark-primary .nav-treeview>.nav-item>.nav-link:hover {
            background-color: rgba(255, 255, 255, 0.03);
            color: #fff;
            margin-left: 0 !important;
        }

        .sidebar-dark-primary .nav-treeview>.nav-item>.nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        /* Brand link hijau */
        .brand-link {
            background: #0d8564 !important;
            border-bottom: none;
            padding: 15px;
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
                        <i class="fas fa-user-circle"></i> ADMIN USER
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-user mr-2"></i> Profile
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
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
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                        <!-- Dashboard -->
                        <li class="nav-item">
                            <a href="{{ url('/') }}" class="nav-link">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>

                        <!-- Terminal -->
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-desktop"></i>
                                <p>Terminal</p>
                            </a>
                        </li>

                        <!-- Master -->
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-database"></i>
                                <p>
                                    Master
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-wrench nav-icon" style="font-size: 12px;"></i>
                                        <p>Maintenance</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-box nav-icon" style="font-size: 12px;"></i>
                                        <p>
                                            Item Management
                                            <i class="right fas fa-angle-left"></i>
                                        </p>
                                    </a>
                                    <ul class="nav nav-treeview" style="padding-left: 1rem;">
                                        <li class="nav-item">
                                            <a href="#" class="nav-link">
                                                <i class="fas fa-cube nav-icon" style="font-size: 11px;"></i>
                                                <p>Products</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#" class="nav-link">
                                                <i class="fas fa-tags nav-icon" style="font-size: 11px;"></i>
                                                <p>Categories</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#" class="nav-link">
                                                <i class="fas fa-barcode nav-icon" style="font-size: 11px;"></i>
                                                <p>SKU Management</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#" class="nav-link">
                                                <i class="fas fa-ruler nav-icon" style="font-size: 11px;"></i>
                                                <p>Units</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-truck nav-icon" style="font-size: 12px;"></i>
                                        <p>Service</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-cog nav-icon" style="font-size: 12px;"></i>
                                        <p>Master Configuration</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- Transaction -->
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-exchange-alt"></i>
                                <p>Transaction</p>
                            </a>
                        </li>

                        <!-- Inventory -->
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-warehouse"></i>
                                <p>
                                    Inventory
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-boxes nav-icon" style="font-size: 12px;"></i>
                                        <p>Stock Management</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-dolly nav-icon" style="font-size: 12px;"></i>
                                        <p>Stock Movement</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-clipboard-check nav-icon" style="font-size: 12px;"></i>
                                        <p>Stock Opname</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- Receiving -->
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-sign-in-alt"></i>
                                <p>Receiving</p>
                            </a>
                        </li>

                        <!-- Picking & Packing -->
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-hand-holding-box"></i>
                                <p>
                                    Picking & Packing
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-hand-pointer nav-icon" style="font-size: 12px;"></i>
                                        <p>Pick Orders</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-box-open nav-icon" style="font-size: 12px;"></i>
                                        <p>Pack Orders</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- Shipping -->
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-shipping-fast"></i>
                                <p>Shipping</p>
                            </a>
                        </li>

                        <!-- Reports -->
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-chart-bar"></i>
                                <p>
                                    Reports
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-file-alt nav-icon" style="font-size: 12px;"></i>
                                        <p>Inventory Report</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-file-invoice nav-icon" style="font-size: 12px;"></i>
                                        <p>Transaction Report</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="fas fa-file-chart-line nav-icon" style="font-size: 12px;"></i>
                                        <p>Performance Report</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- Settings -->
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-cog"></i>
                                <p>Settings</p>
                            </a>
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
    @stack('scripts')
</body>

</html>
