<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WMS Avian')</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="{{ asset('adminlte/css/adminlte.min.css') }}">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">

    <style>
        :root {
            /* ── Avian Brand Palette ── */
            --avian-primary: #004230;
            --avian-secondary: #0d8564;
            --avian-green: #38c172;
            --avian-green-600: #43a047;
            --avian-green-800: #2E7D32;
            --avian-teal-800: #00695C;
            --avian-bluegrey: #455a64;
            --avian-orange: #f6993f;
            --avian-red: #e3342f;
            --avian-yellow: #ffed4a;
            --avian-cyan: #6cb2eb;
            --avian-indigo: #6574cd;
            --sidebar-bg: #1a2332;
            --body-bg: #f8fafc;
            --navbar-height: 3.5rem;
            --shadow-sm: 0 1px 4px rgba(0, 0, 0, .08);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, .12);
            --radius: 8px;
        }

        body,
        .wrapper {
            background: #f0f2f5 !important;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            background: #f0f2f5 !important;
            margin: 0 !important;
            padding: 0 !important;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            letter-spacing: -0.01em;
        }

        /* ─── OVERRIDE Bootstrap success → Avian Green (#38c172) ─── */
        .btn-success {
            background-color: var(--avian-green) !important;
            border-color: var(--avian-green) !important;
            color: #fff !important;
        }

        .btn-success:hover,
        .btn-success:focus,
        .btn-success:active {
            background-color: var(--avian-green-600) !important;
            border-color: var(--avian-green-600) !important;
        }

        .btn-outline-success {
            color: var(--avian-green) !important;
            border-color: var(--avian-green) !important;
        }

        .btn-outline-success:hover,
        .btn-outline-success.active {
            background-color: var(--avian-green) !important;
            border-color: var(--avian-green) !important;
            color: #fff !important;
        }

        .badge-success {
            background-color: var(--avian-green) !important;
        }

        .bg-success {
            background-color: var(--avian-green) !important;
        }

        .text-success {
            color: var(--avian-green) !important;
        }

        .border-success {
            border-color: var(--avian-green) !important;
        }

        .alert-success {
            background-color: #f0fdf4 !important;
            border-color: var(--avian-green) !important;
            color: var(--avian-green-800) !important;
        }

        /* Progress bar success */
        .progress-bar.bg-success {
            background-color: var(--avian-green) !important;
        }

        /* ─── OVERRIDE Bootstrap danger → Avian Red ─── */
        .btn-danger {
            background-color: var(--avian-red) !important;
            border-color: var(--avian-red) !important;
        }

        .btn-danger:hover {
            background-color: #c0392b !important;
            border-color: #c0392b !important;
        }

        .badge-danger {
            background-color: var(--avian-red) !important;
        }

        .text-danger {
            color: var(--avian-red) !important;
        }

        /* ─── OVERRIDE Bootstrap warning → Avian Orange ─── */
        .btn-warning {
            background-color: var(--avian-orange) !important;
            border-color: var(--avian-orange) !important;
            color: #fff !important;
        }

        .btn-warning:hover {
            background-color: #e08530 !important;
            border-color: #e08530 !important;
        }

        .badge-warning {
            background-color: var(--avian-orange) !important;
            color: #fff !important;
        }

        .text-warning {
            color: var(--avian-orange) !important;
        }

        /* ─── SWEETALERT2: hapus border hitam di tombol confirm/cancel ─── */
        .swal2-confirm,
        .swal2-deny,
        .swal2-cancel {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .swal2-confirm:focus,
        .swal2-deny:focus,
        .swal2-cancel:focus {
            box-shadow: none !important;
        }

        /* ─── TRANSITIONS (smooth like avian-hr) ─── */
        .main-sidebar,
        .main-sidebar .brand-link {
            transition: width 0.3s ease-in-out !important;
        }

        .main-header.navbar,
        .content-wrapper,
        .main-footer {
            transition: margin-left 0.3s ease-in-out, left 0.3s ease-in-out !important;
        }

        /* ─── NAVBAR (top bar) ─── */
        .main-header.navbar {
            background: var(--avian-secondary) !important;
            border-bottom: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .18);
            position: fixed !important;
            top: 0 !important;
            left: 250px !important;
            right: 0 !important;
            z-index: 1034;
            min-height: 3.5rem;
            padding: 0 8px;
            width: auto !important;
            margin: 0 !important;
        }

        .sidebar-collapse .main-header.navbar {
            left: 57px !important;
        }

        .main-header .navbar-nav .nav-link {
            color: rgba(255, 255, 255, .9) !important;
            padding: 0 10px;
            height: 3.5rem;
            display: flex;
            align-items: center;
            transition: background .15s;
        }

        .main-header .navbar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            border-radius: 6px;
        }

        /* Breadcrumb di navbar */
        .navbar-breadcrumb {
            display: flex;
            align-items: center;
            margin-left: 4px;
        }

        .navbar-breadcrumb .breadcrumb {
            background: transparent;
            margin: 0;
            padding: 0;
            font-size: 12.5px;
        }

        .navbar-breadcrumb .breadcrumb-item a {
            color: rgba(255, 255, 255, .7) !important;
            text-decoration: none;
        }

        .navbar-breadcrumb .breadcrumb-item a:hover {
            color: #fff !important;
        }

        .navbar-breadcrumb .breadcrumb-item.active {
            color: rgba(255, 255, 255, .95) !important;
            font-weight: 500;
        }

        .navbar-breadcrumb .breadcrumb-item+.breadcrumb-item::before {
            color: rgba(255, 255, 255, .35);
            content: "›";
            font-size: 14px;
        }

        /* Notification bell */
        .navbar-badge {
            position: absolute;
            top: 8px;
            right: 6px;
            font-size: 9px !important;
            padding: 1px 4px !important;
            border-radius: 10px;
        }

        /* Divider vertikal di navbar */
        .navbar-divider {
            width: 1px;
            height: 22px;
            background: rgba(255, 255, 255, .2);
            margin: 0 4px;
        }

        /* User avatar inisial */
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .2);
            border: 1.5px solid rgba(255, 255, 255, .4);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .3px;
            flex-shrink: 0;
            font-family: 'Poppins', sans-serif;
        }

        .user-avatar.lg {
            width: 46px;
            height: 46px;
            font-size: 16px;
            background: var(--avian-secondary);
            border: 2px solid rgba(255, 255, 255, .3);
        }

        /* User dropdown */
        .dropdown-user-menu {
            min-width: 250px !important;
            border: none !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .14) !important;
            border-radius: 10px !important;
            padding: 0 !important;
            overflow: hidden;
            margin-top: 8px !important;
        }

        .dropdown-user-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-bottom: 1px solid #f0f0f0;
        }

        .dropdown-user-header .user-info .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
            line-height: 1.3;
        }

        .dropdown-user-header .user-info .user-email {
            font-size: 11.5px;
            color: #6b7280;
            margin-top: 2px;
        }

        .badge-role {
            display: inline-block;
            background: var(--avian-secondary);
            color: #fff;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 500;
            margin-top: 4px;
        }

        .dropdown-user-menu .dropdown-item {
            font-size: 13px;
            color: #374151;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dropdown-user-menu .dropdown-item i {
            width: 16px;
            text-align: center;
            color: #9ca3af;
        }

        .dropdown-user-menu .dropdown-item:hover {
            background: #f9fafb;
        }

        .dropdown-user-menu .dropdown-item.text-danger i {
            color: #ef4444;
        }

        /* ─── BRAND LINK / LOGO SIDEBAR ─── */
        .brand-link,
        .navbar-avian {
            background: linear-gradient(135deg, #0d8564 0%, #004e39 100%) !important;
            border-bottom: 1px solid rgba(255, 255, 255, .08) !important;
            padding: 0 16px !important;
            height: 3.5rem;
            display: flex !important;
            align-items: center;
            justify-content: flex-start;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .2);
        }

        .brand-link img.brand-image,
        .brand-link .img-white {
            filter: brightness(0) invert(1);
            width: 28px;
            height: 28px;
            margin-right: 10px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .brand-text-wrapper {
            display: flex;
            flex-direction: column;
            line-height: 1;
            overflow: hidden;
        }

        .brand-link .brand-text {
            color: white !important;
            font-weight: 700 !important;
            font-size: 14.5px;
            letter-spacing: .2px;
            white-space: nowrap;
            font-family: 'Poppins', sans-serif;
        }

        .brand-version {
            font-size: 9px;
            color: rgba(255, 255, 255, .45);
            letter-spacing: .8px;
            font-weight: 400;
            margin-top: 3px;
            text-transform: uppercase;
        }

        /* ─── SIDEBAR ─── */
        .main-sidebar {
            background: var(--sidebar-bg) !important;
            border-right: none !important;
        }

        /* sidebar-dark-avian */
        .sidebar-dark-avian .nav-sidebar>.nav-item>.nav-link {
            background-color: transparent;
            color: rgba(255, 255, 255, 0.75);
            display: flex !important;
            align-items: center;
            border-radius: 6px;
            margin: 2px 8px;
            padding: 8px 12px;
            font-size: 13.5px;
            transition: background .15s, color .15s;
        }

        .sidebar-dark-avian .nav-sidebar>.nav-item>.nav-link .nav-icon {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            font-size: 14px;
            color: rgba(255, 255, 255, .45);
            transition: color .15s;
        }

        .sidebar-dark-avian .nav-sidebar>.nav-item>.nav-link:hover {
            background-color: rgba(255, 255, 255, 0.07) !important;
            color: #fff;
        }

        .sidebar-dark-avian .nav-sidebar>.nav-item>.nav-link:hover .nav-icon {
            color: rgba(255, 255, 255, .75);
        }

        .sidebar-dark-avian .nav-sidebar>.nav-item>.nav-link.active {
            background: linear-gradient(90deg, rgba(13, 133, 100, .35) 0%, rgba(13, 133, 100, .15) 100%) !important;
            color: #fff !important;
            border-left: 3px solid var(--avian-secondary);
            margin-left: 5px;
            padding-left: 9px;
        }

        .sidebar-dark-avian .nav-sidebar>.nav-item>.nav-link.active .nav-icon {
            color: var(--avian-accent);
        }

        /* Submenu level 1 */
        .sidebar-dark-avian .nav-treeview>.nav-item>.nav-link {
            color: rgba(255, 255, 255, 0.55);
            padding: 6px 12px 6px 1.8rem;
            display: flex !important;
            align-items: center;
            border-radius: 5px;
            margin: 1px 8px;
            font-size: 12.5px;
            transition: background .15s, color .15s;
        }

        .sidebar-dark-avian .nav-treeview>.nav-item>.nav-link:hover {
            background-color: rgba(255, 255, 255, 0.05) !important;
            color: rgba(255, 255, 255, .9);
        }

        .sidebar-dark-avian .nav-treeview>.nav-item>.nav-link.active {
            background-color: rgba(13, 133, 100, .2) !important;
            color: #fff !important;
        }

        /* Submenu level 2 */
        .sidebar-dark-avian .nav-treeview .nav-treeview>.nav-item>.nav-link {
            color: rgba(255, 255, 255, 0.45);
            padding-left: 2.8rem;
            font-size: 12px;
        }

        /* nav-header section labels */
        .nav-header {
            color: rgba(255, 255, 255, 0.28) !important;
            font-size: 9.5px !important;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            padding: 16px 20px 5px !important;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
        }

        /* Sidebar scrollbar - thin & subtle */
        .sidebar {
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, .08) transparent;
        }

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .08);
            border-radius: 4px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, .18);
        }

        /* ─── CONTENT ─── */
        .content-wrapper,
        .main-footer {
            background: #f0f2f5;
            overflow-x: hidden;
            margin-top: 56px !important;
            margin-left: 250px !important;
        }

        .sidebar-collapse .content-wrapper,
        .sidebar-collapse .main-footer {
            margin-left: 57px !important;
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

        .small-box {
            border-radius: 6px;
        }

        .small-box .inner h3 {
            font-size: 32px;
            font-weight: 700;
        }

        .main-footer {
            background: #fff;
        }

        .main-footer a {
            color: #0d8564;
            text-decoration: none;
        }

        .main-footer a:hover {
            color: #004230;
        }

        /* ─── SIDEBAR COLLAPSED STATE ─── */
        .sidebar-collapse .brand-link {
            justify-content: center;
            padding: 0 !important;
        }

        .sidebar-collapse .brand-link .brand-text-wrapper {
            display: none;
        }

        .sidebar-collapse .brand-link img.brand-image {
            margin-right: 0;
        }

        .sidebar-collapse .nav-sidebar .nav-link p,
        .sidebar-collapse .nav-link .right {
            display: none;
        }

        .sidebar-collapse .nav-sidebar .nav-link,
        .sidebar-collapse .nav-treeview .nav-link {
            justify-content: center;
            padding-left: 0.5rem !important;
        }

        .sidebar-collapse .nav-sidebar .nav-icon,
        .sidebar-collapse .nav-treeview .nav-icon {
            margin-right: 0;
        }

        /* ─── OVERRIDE BLUE → AVIAN GREEN ─── */
        /* Buttons */
        .btn-primary {
            background-color: #0d8564 !important;
            border-color: #0d8564 !important;
            color: #fff !important;
        }

        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background-color: #004230 !important;
            border-color: #004230 !important;
        }

        .btn-outline-primary {
            color: #0d8564 !important;
            border-color: #0d8564 !important;
        }

        .btn-outline-primary:hover {
            background-color: #0d8564 !important;
            color: #fff !important;
        }

        /* Avian brand button */
        .btn-avian-secondary {
            background-color: var(--avian-secondary) !important;
            border-color: var(--avian-secondary) !important;
            color: #fff !important;
        }

        .btn-avian-secondary:hover,
        .btn-avian-secondary:focus,
        .btn-avian-secondary:active {
            background-color: var(--avian-primary) !important;
            border-color: var(--avian-primary) !important;
            color: #fff !important;
        }

        /* Card primary outline top border */
        .card-primary.card-outline {
            border-top: 3px solid #0d8564 !important;
        }

        .card-primary>.card-header {
            background-color: #0d8564 !important;
        }

        /* Form focus states */
        .form-control:focus {
            border-color: #0d8564 !important;
            box-shadow: 0 0 0 0.2rem rgba(13, 133, 100, 0.25) !important;
        }

        /* Custom switch checked */
        .custom-control-input:checked~.custom-control-label::before {
            background-color: #0d8564 !important;
            border-color: #0d8564 !important;
        }

        /* DataTable pagination active */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #0d8564 !important;
            border-color: #0d8564 !important;
            color: #fff !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #004230 !important;
            border-color: #004230 !important;
            color: #fff !important;
        }

        /* Badge primary */
        .badge-primary {
            background-color: #0d8564 !important;
        }

        /* Alert / Link primary */
        a.text-primary,
        .text-primary {
            color: #0d8564 !important;
        }

        a {
            color: #0d8564;
        }

        a:hover {
            color: #004230;
        }

        /* ─── CARD GLOBAL IMPROVEMENTS ─── */
        .card {
            border: none !important;
            border-radius: 10px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06), 0 0 0 1px rgba(0, 0, 0, .04) !important;
        }

        .card-header {
            background: #fff !important;
            border-bottom: 1px solid #f1f3f5 !important;
            border-radius: 10px 10px 0 0 !important;
            padding: 14px 20px !important;
        }

        .card-header .card-title {
            font-size: 15px !important;
            font-weight: 600 !important;
            color: #111827 !important;
            margin: 0 !important;
        }

        .card-body {
            padding: 20px !important;
        }

        .card-primary.card-outline {
            border-top: 3px solid #0d8564 !important;
        }

        .card-primary>.card-header {
            background-color: #0d8564 !important;
        }

        .card-primary>.card-header .card-title {
            color: #fff !important;
        }

        /* ─── FOOTER ─── */
        .main-footer {
            background: #fff !important;
            border-top: 1px solid #e9ecef !important;
            padding: 12px 20px !important;
            font-size: 12.5px !important;
            color: #6b7280 !important;
        }

        .main-footer a {
            color: #0d8564;
            font-weight: 500;
        }

        .main-footer a:hover {
            color: #004230;
        }

        /* ─── PAGE CONTENT SPACING ─── */
        .content-wrapper>.content {
            padding-bottom: 24px;
        }

        /* ─── PAGE LOADING SPINNER ─── */
        #page-loader {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(255, 255, 255, .72);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease;
        }

        #page-loader.visible {
            opacity: 1;
            pointer-events: all;
        }

        .loader-ring {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: 4px solid #e5e7eb;
            border-top-color: var(--avian-secondary);
            animation: spin .75s linear infinite;
        }

        .loader-logo {
            width: 28px;
            height: 28px;
            position: absolute;
            border-radius: 50%;
            background: var(--avian-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loader-logo i {
            color: #fff;
            font-size: 13px;
        }

        .loader-text {
            font-size: 13px;
            font-weight: 500;
            color: var(--avian-secondary);
            letter-spacing: .3px;
        }

        .loader-dots::after {
            content: '';
            animation: dots 1.2s steps(4, end) infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes dots {
            0%   { content: ''; }
            25%  { content: '.'; }
            50%  { content: '..'; }
            75%  { content: '...'; }
            100% { content: ''; }
        }

        /* thin progress bar at top */
        #loader-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0%;
            background: linear-gradient(90deg, var(--avian-secondary), var(--avian-green));
            z-index: 100000;
            border-radius: 0 2px 2px 0;
            transition: width .3s ease;
            box-shadow: 0 0 8px rgba(13,133,100,.5);
        }

        /* ─── DATATABLE PROCESSING INDICATOR — branded ── */
        /* Muncul saat search / sort / ganti halaman (bukan saat initial load,
           karena page loader sudah menutupi seluruh halaman selama init AJAX) */
        .dataTables_wrapper { position: relative; }
        div.dataTables_processing {
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            width: auto !important;
            min-width: 150px;
            margin: 0 !important;
            padding: 7px 20px !important;
            transform: translate(-50%, -50%) !important;
            background: rgba(26, 35, 50, 0.91) !important;
            color: #fff !important;
            border: none !important;
            border-radius: 30px !important;
            font-size: 12px !important;
            font-weight: 600;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .32) !important;
            z-index: 100;
            text-align: center;
            letter-spacing: .2px;
        }

        /* ─── TABLE IMPROVEMENTS ─── */
        .table thead th {
            background: #f8f9fa;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 2px solid #e9ecef !important;
            border-top: none !important;
        }

        .table td {
            font-size: 13.5px;
            vertical-align: middle !important;
            border-color: #f3f4f6 !important;
        }

        .table-hover tbody tr:hover {
            background-color: #f8fff9 !important;
        }
    </style>
    @stack('styles')
</head>

<body class="hold-transition sidebar-mini layout-fixed">

    {{-- ── Page Loading Spinner ── --}}
    <div id="loader-bar"></div>
    <div id="page-loader">
        <div style="position:relative;display:flex;align-items:center;justify-content:center;">
            <div class="loader-ring"></div>
            <div class="loader-logo">
                <i class="fas fa-warehouse"></i>
            </div>
        </div>
        <div class="loader-text">Memuat<span class="loader-dots"></span></div>
    </div>

    <div class="wrapper">

        <!-- Navbar -->
        @php
            $navUser = auth()->user();
            $nameParts = explode(' ', trim($navUser->name ?? 'User'));
            $initials =
                strtoupper(substr($nameParts[0], 0, 1)) .
                (isset($nameParts[1]) ? strtoupper(substr($nameParts[1], 0, 1)) : '');
        @endphp
        <nav class="main-header navbar navbar-expand">

            {{-- Left: hamburger + breadcrumb --}}
            <ul class="navbar-nav align-items-center">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button" title="Toggle Sidebar">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>
                <li class="nav-item navbar-breadcrumb d-none d-sm-flex">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}"><i class="fas fa-home"></i></a>
                        </li>
                        @hasSection('breadcrumb')
                            @yield('breadcrumb')
                        @else
                            @if (Request::is('dashboard'))
                                {{-- no extra crumb on dashboard --}}
                            @else
                                <li class="breadcrumb-item active">@yield('page_title', '—')</li>
                            @endif
                        @endif
                    </ol>
                </li>
            </ul>

            {{-- Right: bell + divider + user --}}
            <ul class="navbar-nav ml-auto align-items-center">

                {{-- ── Notification Bell Dropdown ── --}}
                <li class="nav-item dropdown" id="notifDropdownItem">
                    <a class="nav-link position-relative" href="#"
                       data-toggle="dropdown" title="Notifikasi" id="notifBellBtn"
                       style="min-width:40px;justify-content:center;">
                        <i class="fas fa-bell" style="font-size:15px;"></i>
                        <span class="badge badge-danger navbar-badge" id="notifBadge"
                              style="display:none;font-size:9px;padding:2px 5px;border-radius:10px;
                                     top:6px;right:4px;">0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right p-0"
                         id="notifDropdown"
                         style="width:360px;max-height:480px;border:none;
                                box-shadow:0 10px 30px rgba(0,0,0,.15);border-radius:10px;overflow:hidden;">

                        {{-- Header --}}
                        <div class="d-flex justify-content-between align-items-center px-3 py-2"
                             style="background:#f8f9fa;border-bottom:1px solid #e9ecef;">
                            <span class="font-weight-bold" style="font-size:13px">
                                <i class="fas fa-bell mr-1 text-warning"></i>
                                Notifikasi
                                <span class="badge badge-danger ml-1" id="notifBadgeHeader"
                                      style="display:none;font-size:9px">0</span>
                            </span>
                            <button class="btn btn-xs btn-link text-muted p-0"
                                    id="btnMarkAllRead" style="font-size:11px;text-decoration:none;">
                                <i class="fas fa-check-double mr-1"></i>Tandai Semua Dibaca
                            </button>
                        </div>

                        {{-- Notification List --}}
                        <div id="notifList"
                             style="overflow-y:auto;max-height:380px;">
                            <div class="text-center py-4 text-muted" id="notifEmpty">
                                <i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>
                                <small>Belum ada notifikasi</small>
                            </div>
                            <div id="notifItems"></div>
                        </div>
                    </div>
                </li>

                {{-- Full-screen toggle --}}
                <li class="nav-item d-none d-md-flex">
                    <a class="nav-link" data-widget="fullscreen" href="#" title="Layar Penuh">
                        <i class="fas fa-expand-arrows-alt" style="font-size:14px;"></i>
                    </a>
                </li>

                {{-- Divider --}}
                <li class="nav-item d-flex align-items-center">
                    <div class="navbar-divider"></div>
                </li>

                {{-- User Dropdown --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" data-toggle="dropdown" href="#"
                        style="gap:8px; padding-right:12px;">
                        <div class="user-avatar">{{ $initials }}</div>
                        <div class="d-none d-md-block" style="line-height:1.25;">
                            <div style="font-size:12.5px;font-weight:600;color:#fff;">
                                {{ $navUser->name ?? 'Guest' }}
                            </div>
                            <div style="font-size:10px;color:rgba(255,255,255,.65);">
                                {{ $navUser->role->name ?? '-' }}
                            </div>
                        </div>
                        <i class="fas fa-chevron-down d-none d-md-inline"
                            style="font-size:9px;color:rgba(255,255,255,.55);"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right dropdown-user-menu">
                        <div class="dropdown-user-header">
                            <div class="user-avatar lg">{{ $initials }}</div>
                            <div class="user-info">
                                <div class="user-name">{{ $navUser->name ?? 'Guest' }}</div>
                                <div class="user-email">{{ $navUser->email ?? '' }}</div>
                                <span class="badge-role">{{ $navUser->role->name ?? '-' }}</span>
                            </div>
                        </div>
                        <div class="dropdown-divider m-0"></div>
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-user-cog"></i> Pengaturan Profil
                        </a>
                        <div class="dropdown-divider m-0"></div>
                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt"></i> Keluar
                            </button>
                        </form>
                    </div>
                </li>
            </ul>
        </nav>

        <!-- Sidebar -->
        <aside class="main-sidebar elevation-4 sidebar-dark-avian">
            <a href="{{ route('dashboard') }}" class="brand-link navbar-avian">
                <img src="{{ asset('images/avian-logo-icon.png') }}" alt="Avian Brands Logo"
                    class="brand-image img-white">
                <div class="brand-text-wrapper">
                    <span class="brand-text">{{ config('app.name', 'WMS') }}</span>
                    <span class="brand-version">Warehouse Management</span>
                </div>
            </a>

            <div class="sidebar">
                <nav class="mt-3">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                        data-accordion="true">

                        {{-- DASHBOARD --}}
                        <li class="nav-item">
                            <a href="{{ route('dashboard') }}"
                                class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>

                        {{-- ═══ 1. DATA MASTER ═══ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">DATA MASTER
                        </li>

                        {{-- Lokasi Gudang --}}
                        <li class="nav-item {{ request()->is('location*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('location*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-map-marked-alt"></i>
                                <p>Lokasi Gudang <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('location.warehouses.index') }}"
                                        class="nav-link {{ request()->routeIs('location.warehouses*') ? 'active' : '' }}">
                                        <i class="fas fa-warehouse nav-icon" style="font-size:12px;"></i>
                                        <p>Warehouse</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('location.racks.index') }}"
                                        class="nav-link {{ request()->routeIs('location.racks*') ? 'active' : '' }}">
                                        <i class="fas fa-th-large nav-icon" style="font-size:12px;"></i>
                                        <p>Rak (Rack)</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('location.cells.index') }}"
                                        class="nav-link {{ request()->routeIs('location.cells.index') || request()->routeIs('location.cells.create') || request()->routeIs('location.cells.edit') || request()->routeIs('location.cells.stock') || request()->routeIs('location.cells.qr-label') ? 'active' : '' }}">
                                        <i class="fas fa-border-all nav-icon" style="font-size:12px;"></i>
                                        <p>Sel (Cell / Slot)</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('location.cells.scan') }}"
                                        class="nav-link {{ request()->routeIs('location.cells.scan') ? 'active' : '' }}">
                                        <i class="fas fa-qrcode nav-icon" style="font-size:12px;color:#0ab87a;"></i>
                                        <p>Scan QR Cell <span class="badge badge-success badge-sm ml-1"
                                                style="font-size:9px;">Tablet</span></p>
                                    </a>
                                </li>
                                @if(auth()->user()->hasRole('admin'))
                                <li class="nav-item">
                                    <a href="{{ route('location.mspart.import') }}"
                                        class="nav-link {{ request()->routeIs('location.mspart*') ? 'active' : '' }}">
                                        <i class="fas fa-file-import nav-icon" style="font-size:12px;color:#f6993f;"></i>
                                        <p>Import MSpart</p>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>

                        {{-- Master Sparepart --}}
                        <li class="nav-item {{ request()->is('master*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('master*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-database"></i>
                                <p>Master Sparepart <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('master.items.index') }}"
                                        class="nav-link {{ request()->routeIs('master.items*') ? 'active' : '' }}">
                                        <i class="fas fa-cogs nav-icon" style="font-size:12px;"></i>
                                        <p>Data Sparepart</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('master.categories.index') }}"
                                        class="nav-link {{ request()->routeIs('master.categories*') ? 'active' : '' }}">
                                        <i class="fas fa-tags nav-icon" style="font-size:12px;"></i>
                                        <p>Kategori</p>
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
                                    <a href="{{ route('master.affinities.index') }}"
                                        class="nav-link {{ request()->routeIs('master.affinities*') ? 'active' : '' }}">
                                        <i class="fas fa-project-diagram nav-icon" style="font-size:12px;"></i>
                                        <p>Co-Occurrence</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        {{-- ═══ 2. PENERIMAAN ═══ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">PENERIMAAN
                        </li>

                        <li class="nav-item {{ request()->is('inbound*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('inbound*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-truck-loading"></i>
                                <p>Penerimaan Barang <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">

                                {{-- Surat Jalan --}}
                                <li class="nav-item">
                                    <a href="{{ route('inbound.orders.index') }}"
                                        class="nav-link {{ request()->routeIs('inbound.orders.index') ? 'active' : '' }}">
                                        <i class="fas fa-file-import nav-icon" style="font-size:12px;"></i>
                                        <p>Semua Surat Jalan (DO)
                                            <span class="badge badge-danger ml-1 sidebar-badge"
                                                  id="badge-inbound-draft"
                                                  style="display:none;font-size:9px">0</span>
                                        </p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('inbound.orders.create') }}"
                                        class="nav-link {{ request()->routeIs('inbound.orders.create') ? 'active' : '' }}">
                                        <i class="fas fa-plus-circle nav-icon"
                                            style="font-size:12px;color:#10b981;"></i>
                                        <p>Tambah DO Manual</p>
                                    </a>
                                </li>

                            </ul>
                        </li>

                        {{-- ═══ 3. OPTIMASI GA ═══ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">OPTIMASI GA
                        </li>

                        <li class="nav-item {{ request()->is('putaway*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('putaway*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-brain"></i>
                                <p>Penempatan Barang <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('putaway.index') }}"
                                        class="nav-link {{ request()->routeIs('putaway.index') ? 'active' : '' }}">
                                        <i class="fas fa-dolly-flatbed nav-icon" style="font-size:12px;"></i>
                                        <p>Antrian Put-Away
                                            <span class="badge badge-warning ml-1 sidebar-badge"
                                                  id="badge-putaway-pending"
                                                  style="display:none;font-size:9px">0</span>
                                        </p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('putaway.queue') }}"
                                        class="nav-link {{ request()->routeIs('putaway.queue') ? 'active' : '' }}">
                                        <i class="fas fa-stream nav-icon" style="font-size:12px;"></i>
                                        <p>Put-Away Queue</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        {{-- ═══ 4. OUTBOUND ═══ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">OUTBOUND</li>

                        <li class="nav-item {{ request()->is('outbound*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('outbound*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-sign-out-alt" style="color:#6b7280;"></i>
                                <p>Pengambilan Barang <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('outbound.index') }}"
                                        class="nav-link {{ request()->routeIs('outbound.index') ? 'active' : '' }}">
                                        <i class="fas fa-history nav-icon" style="font-size:12px;"></i>
                                        <p>Riwayat Outbound</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('outbound.create') }}"
                                        class="nav-link {{ request()->routeIs('outbound.create') ? 'active' : '' }}">
                                        <i class="fas fa-plus-circle nav-icon" style="font-size:12px;color:#10b981;"></i>
                                        <p>Outbound Baru</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        {{-- ═══ 5. INVENTORI & STOK ═══ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">INVENTORI</li>

                        <li class="nav-item {{ request()->is('stock*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('stock*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-boxes"></i>
                                <p>Manajemen Stok <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('stock.index') }}"
                                        class="nav-link {{ request()->routeIs('stock.index') ? 'active' : '' }}">
                                        <i class="fas fa-cubes nav-icon" style="font-size:12px;"></i>
                                        <p>Stok Saat Ini</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('stock.movements') }}"
                                        class="nav-link {{ request()->routeIs('stock.movements') ? 'active' : '' }}">
                                        <i class="fas fa-dolly nav-icon" style="font-size:12px;"></i>
                                        <p>Mutasi Stok</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('stock.low-stock') }}"
                                        class="nav-link {{ request()->routeIs('stock.low-stock') ? 'active' : '' }}">
                                        <i class="fas fa-exclamation-triangle nav-icon"
                                            style="font-size:12px;color:#f97316;"></i>
                                        <p>Stok Kritis</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('stock.near-expiry') }}"
                                        class="nav-link {{ request()->routeIs('stock.near-expiry') ? 'active' : '' }}">
                                        <i class="fas fa-calendar-times nav-icon"
                                            style="font-size:12px;color:#ef4444;"></i>
                                        <p>Mendekati Kadaluarsa</p>
                                    </a>
                                </li>
                            </ul>
                        </li>


                        {{-- ═══════════════════════════════
                             5. VISUALISASI 3D
                        ═══════════════════════════════ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">VISUALISASI
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('warehouse3d.index') }}"
                                class="nav-link {{ request()->routeIs('warehouse3d.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-cube"></i>
                                <p>Denah Gudang 3D</p>
                            </a>
                        </li>

                        {{-- ═══════════════════════════════
                             6. LAPORAN & ANALITIK
                        ═══════════════════════════════ --}}
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">LAPORAN</li>

                        <li class="nav-item {{ request()->is('reports*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('reports*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-chart-bar"></i>
                                <p>Laporan <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('reports.inventory') }}"
                                        class="nav-link {{ request()->routeIs('reports.inventory') ? 'active' : '' }}">
                                        <i class="fas fa-boxes nav-icon" style="font-size:12px;"></i>
                                        <p>Laporan Stok</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('reports.inbound') }}"
                                        class="nav-link {{ request()->routeIs('reports.inbound') ? 'active' : '' }}">
                                        <i class="fas fa-file-import nav-icon" style="font-size:12px;"></i>
                                        <p>Laporan Penerimaan</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('reports.putaway') }}"
                                        class="nav-link {{ request()->routeIs('reports.putaway') ? 'active' : '' }}">
                                        <i class="fas fa-dolly-flatbed nav-icon" style="font-size:12px;"></i>
                                        <p>Laporan Put-Away</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('reports.movements') }}"
                                        class="nav-link {{ request()->routeIs('reports.movements') ? 'active' : '' }}">
                                        <i class="fas fa-exchange-alt nav-icon" style="font-size:12px;"></i>
                                        <p>Laporan Mutasi Stok</p>
                                    </a>
                                </li>
                                @if(!auth()->user()->hasRole('operator'))
                                <li class="nav-item">
                                    <a href="{{ route('reports.ga-effectiveness') }}"
                                        class="nav-link {{ request()->routeIs('reports.ga-effectiveness') ? 'active' : '' }}">
                                        <i class="fas fa-brain nav-icon" style="font-size:12px;color:#6366f1;"></i>
                                        <p>Kinerja Genetic Algorithm</p>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>

                        {{-- ═══════════════════════════════
                             8. PENGATURAN SISTEM
                        ═══════════════════════════════ --}}
                        @if(!auth()->user()->hasRole('operator'))
                        <li class="nav-header" style="color:#6b7280;font-size:10px;letter-spacing:1px;">SISTEM</li>

                        @if (auth()->user()?->isAdmin())
                            {{-- Manajemen Pengguna --}}
                            <li class="nav-item {{ request()->is('users*') ? 'menu-open' : '' }}">
                                <a href="#" class="nav-link {{ request()->is('users*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-users"></i>
                                    <p>Manajemen User <i class="right fas fa-angle-left"></i></p>
                                </a>
                                <ul class="nav nav-treeview">
                                    <li class="nav-item">
                                        <a href="{{ route('users.index') }}"
                                            class="nav-link {{ request()->routeIs('users.index') ? 'active' : '' }}">
                                            <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                            <p>Daftar Pengguna</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="{{ route('users.create') }}"
                                            class="nav-link {{ request()->routeIs('users.create') ? 'active' : '' }}">
                                            <i class="fas fa-user-plus nav-icon"
                                                style="font-size:11px;color:#10b981;"></i>
                                            <p>Tambah Pengguna</p>
                                        </a>
                                    </li>
                                </ul>
                            </li>

                            {{-- Role & Permission --}}
                            <li class="nav-item {{ request()->is('roles*') ? 'menu-open' : '' }}">
                                <a href="#" class="nav-link {{ request()->is('roles*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-user-shield"></i>
                                    <p>Role &amp; Hak Akses <i class="right fas fa-angle-left"></i></p>
                                </a>
                                <ul class="nav nav-treeview">
                                    <li class="nav-item">
                                        <a href="{{ route('roles.index') }}"
                                            class="nav-link {{ request()->routeIs('roles.index') ? 'active' : '' }}">
                                            <i class="fas fa-list nav-icon" style="font-size:11px;"></i>
                                            <p>Daftar Role</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="{{ route('roles.create') }}"
                                            class="nav-link {{ request()->routeIs('roles.create') ? 'active' : '' }}">
                                            <i class="fas fa-plus nav-icon" style="font-size:11px;color:#10b981;"></i>
                                            <p>Tambah Role</p>
                                        </a>
                                    </li>
                                </ul>
                            </li>

                        @endif
                        @endif
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
            <strong>Copyright &copy; 2026 <a href="https://avianbrands.com" target="_blank">Avian Brands</a>.</strong>
            All rights reserved.
            <div class="float-right d-none d-sm-inline-block" style="color:#9ca3af;">
                WMS Avian &nbsp;&middot;&nbsp; <b style="color:#6b7280;">v1.0.0</b>
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

    <!-- DataTable global defaults (bahasa Indonesia + processing branded) -->
    <script>
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            processing:   '<i class="fas fa-circle-notch fa-spin mr-1" style="color:#0d8564"></i> Memuat…',
            search:       'Cari:',
            lengthMenu:   'Tampil _MENU_ data',
            info:         'Data _START_–_END_ dari _TOTAL_',
            infoEmpty:    'Tidak ada data',
            infoFiltered: '(dari _MAX_ total)',
            zeroRecords:  '<div class="text-center text-muted py-3 small"><i class="fas fa-search fa-lg d-block mb-1"></i>Data tidak ditemukan</div>',
            emptyTable:   '<div class="text-center text-muted py-3 small"><i class="fas fa-inbox fa-lg d-block mb-1"></i>Belum ada data</div>',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' },
        },
    });
    </script>

    @if (session('success'))
        <script>
            $(document).ready(function() {
                Swal.fire({
                    title: 'Berhasil!',
                    text: '{{ addslashes(session('success')) }}',
                    icon: 'success',
                    timer: 3500,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: true,
                    showClass: {
                        popup: 'swal2-show',
                    },
                    hideClass: {
                        popup: 'swal2-hide',
                    },
                });
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            $(document).ready(function() {
                Swal.fire({
                    title: 'Gagal!',
                    text: '{{ addslashes(session('error')) }}',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'OK',
                });
            });
        </script>
    @endif

    {{-- ── Page Loader JS ─────────────────────────────────────────── --}}
    <script>
    (function () {
        const loader = document.getElementById('page-loader');
        const bar    = document.getElementById('loader-bar');
        let barTimer = null;
        let hidden   = false;   // guard: jangan hide dua kali

        function showLoader() {
            hidden = false;
            bar.style.width = '0%';
            bar.style.transition = 'none';
            requestAnimationFrame(() => {
                bar.style.transition = 'width 2.5s cubic-bezier(.1,.6,.3,1)';
                bar.style.width = '72%';
            });
            loader.classList.add('visible');
        }

        function hideLoader() {
            if (hidden) return;
            hidden = true;
            bar.style.transition = 'width .2s ease';
            bar.style.width = '100%';
            loader.classList.remove('visible');
            clearTimeout(barTimer);
            barTimer = setTimeout(() => {
                bar.style.transition = 'opacity .3s';
                bar.style.opacity   = '0';
                setTimeout(() => {
                    bar.style.width   = '0%';
                    bar.style.opacity = '1';
                    bar.style.transition = '';
                }, 300);
            }, 250);
        }

        // Expose agar bisa dipanggil dari luar IIFE (koordinasi DataTable)
        window.__hideLoader = hideLoader;

        // ── Deteksi apakah halaman ini punya DataTable ──────────────
        // #datatable adalah id standar yang dipakai di semua view
        const hasDt = !!document.getElementById('datatable');

        // window.load: hide hanya jika TIDAK ada DataTable
        // Jika ada DataTable, biarkan init.dt yang hide setelah data pertama loaded
        window.addEventListener('load', function () {
            if (!hasDt) hideLoader();
        });

        // Cached page (readyState sudah complete saat script jalan)
        if (document.readyState === 'complete' && !hasDt) hideLoader();

        // Safety fallback: paksa hide setelah 8 detik (jika init.dt tidak pernah fire)
        if (hasDt) setTimeout(hideLoader, 8000);

        // ── DataTable: hide page loader setelah data pertama selesai dimuat ──
        // init.dt fires setelah draw pertama (termasuk AJAX server-side selesai).
        // Ini yang menggantikan window.load untuk halaman ber-DataTable,
        // sehingga user hanya melihat SATU loading (page loader), bukan dua.
        if (hasDt) {
            $(document).one('init.dt', function () {
                hideLoader();
            });
        }

        // Tampilkan saat klik link navigasi (non-AJAX, non-anchor)
        document.addEventListener('click', function (e) {
            const a = e.target.closest('a');
            if (!a) return;
            const href = a.getAttribute('href');
            if (!href || href === '#' || href.startsWith('#') ||
                href.startsWith('javascript') ||
                a.getAttribute('target') === '_blank' ||
                e.ctrlKey || e.metaKey || e.shiftKey) return;
            if (a.hasAttribute('download')) return;
            showLoader();
        });

        // Tampilkan saat submit form non-AJAX
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (form.dataset.ajax || form.dataset.noLoader) return;
            showLoader();
        });
    })();
    </script>

    {{-- ── Notification System JS ─────────────────────────────────── --}}
    <script>
    (function () {
        const notifUrl    = "{{ route('notifications.index') }}";
        const markReadUrl = "{{ url('notifications') }}";
        const markAllUrl  = "{{ route('notifications.read-all') }}";
        const csrfToken   = $('meta[name="csrf-token"]').attr('content');

        // Tracking ID notifikasi per sesi browser
        let knownIds    = null;
        let isFirstPoll = true;

        // Antrian toast supaya tidak tumpuk sekaligus
        let toastQueue   = [];
        let toastRunning = false;

        const iconColorMap = {
            primary:   '#0d8564',
            info:      '#17a2b8',
            warning:   '#f6993f',
            success:   '#38c172',
            danger:    '#e3342f',
            secondary: '#6c757d',
        };

        const toastMeta = {
            new_inbound:   { icon: 'fas fa-truck',        color: '#0d8564', label: 'Inbound Baru Masuk' },
            qty_confirmed: { icon: 'fas fa-check-circle', color: '#17a2b8', label: 'Qty Dikonfirmasi'    },
            ga_accepted:   { icon: 'fas fa-robot',        color: '#8b5cf6', label: 'GA Diterima'         },
            putaway_done:  { icon: 'fas fa-boxes',        color: '#f59e0b', label: 'Put-Away Selesai'    },
        };

        // ── Render item di dropdown bell ────────────────────────────────
        function renderNotif(n) {
            const color  = iconColorMap[n.color] || '#6c757d';
            const bgRead = n.is_read ? '#fff' : '#f0fff8';
            const bold   = n.is_read ? '' : 'font-weight:600';
            return `
            <div class="notif-item d-flex align-items-start px-3 py-2"
                 data-id="${n.id}" data-url="${n.url}"
                 style="cursor:pointer;border-bottom:1px solid #f3f4f6;background:${bgRead};transition:background .15s">
                <div class="mr-2 mt-1 flex-shrink-0"
                     style="width:32px;height:32px;border-radius:50%;background:${color}22;
                            display:flex;align-items:center;justify-content:center">
                    <i class="${n.icon}" style="color:${color};font-size:13px"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="text-dark" style="font-size:12.5px;${bold};line-height:1.4">
                        ${n.title}
                    </div>
                    <div class="text-muted" style="font-size:11.5px;margin-top:2px;line-height:1.35">
                        ${n.message}
                    </div>
                    <div class="text-muted" style="font-size:10px;margin-top:3px">
                        <i class="far fa-clock mr-1"></i>${n.created_at}
                    </div>
                </div>
                ${!n.is_read ? '<span class="ml-2 mt-1" style="width:7px;height:7px;border-radius:50%;background:#e3342f;flex-shrink:0;display:block"></span>' : ''}
            </div>`;
        }

        // ── Tampilkan satu toast via SweetAlert2 ────────────────────────
        function showNextToast() {
            if (toastRunning || toastQueue.length === 0) return;
            toastRunning = true;

            const n    = toastQueue.shift();
            const meta = toastMeta[n.type] || {
                icon:  n.icon  || 'fas fa-bell',
                color: iconColorMap[n.color] || '#6c757d',
                label: 'Notifikasi Baru',
            };

            // Buat HTML konten toast (tanpa template literal bersarang)
            const wrap = document.createElement('div');
            wrap.style.cssText = 'display:flex;align-items:flex-start;gap:10px;text-align:left;cursor:pointer;';

            const iconBox = document.createElement('div');
            iconBox.style.cssText = 'width:38px;height:38px;border-radius:50%;background:' + meta.color + '18;'
                + 'display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;';
            iconBox.innerHTML = '<i class="' + meta.icon + '" style="color:' + meta.color + ';font-size:16px;"></i>';

            const textBox = document.createElement('div');
            textBox.style.cssText = 'flex:1;min-width:0;';

            const labelEl = document.createElement('div');
            labelEl.style.cssText = 'font-size:11px;font-weight:700;color:' + meta.color
                + ';text-transform:uppercase;letter-spacing:.6px;margin-bottom:3px;';
            labelEl.textContent = meta.label;

            const titleEl = document.createElement('div');
            titleEl.style.cssText = 'font-size:13.5px;font-weight:600;color:#1f2937;line-height:1.3;margin-bottom:3px;';
            titleEl.textContent = n.title;

            const msgEl = document.createElement('div');
            msgEl.style.cssText = 'font-size:12px;color:#6b7280;line-height:1.4;';
            msgEl.innerHTML = n.message; // message sudah dipercaya dari server sendiri

            textBox.appendChild(labelEl);
            textBox.appendChild(titleEl);
            textBox.appendChild(msgEl);
            wrap.appendChild(iconBox);
            wrap.appendChild(textBox);

            Swal.fire({
                toast:             false,   // bukan toast kecil — pakai popup full supaya pasti terlihat
                position:          'top-end',
                html:              wrap.outerHTML,
                showConfirmButton: true,
                confirmButtonText: '<i class="fas fa-arrow-right mr-1"></i>Buka',
                confirmButtonColor: meta.color,
                showCloseButton:   true,
                timer:             12000,
                timerProgressBar:  true,
                width:             '380px',
                padding:           '1.2em',
                customClass:       { popup: 'wms-notif-popup' },
                didOpen: function (popup) {
                    // Klik area konten → langsung navigasi
                    popup.querySelector('.swal2-html-container').style.cursor = 'pointer';
                    popup.querySelector('.swal2-html-container').addEventListener('click', function () {
                        Swal.close();
                        $.post(markReadUrl + '/' + n.id + '/read', { _token: csrfToken });
                        if (n.url && n.url !== '#') window.location.href = n.url;
                    });
                },
                didClose: function () {
                    toastRunning = false;
                    // Jika masih ada antrian, tampilkan berikutnya setelah jeda singkat
                    if (toastQueue.length > 0) setTimeout(showNextToast, 400);
                }
            }).then(function (result) {
                if (result.isConfirmed) {
                    $.post(markReadUrl + '/' + n.id + '/read', { _token: csrfToken });
                    if (n.url && n.url !== '#') window.location.href = n.url;
                }
            });
        }

        // ── Polling utama ────────────────────────────────────────────────
        function loadNotifications() {
            $.getJSON(notifUrl, function (data) {
                const count = data.unread_count;

                // Badge di bell icon
                if (count > 0) {
                    $('#notifBadge').text(count > 99 ? '99+' : count).show();
                    $('#notifBadgeHeader').text(count).show();
                } else {
                    $('#notifBadge').hide();
                    $('#notifBadgeHeader').hide();
                }

                updateSidebarBadges(data.sidebar_counts || {});

                const items = data.notifications || [];

                if (isFirstPoll) {
                    // Polling pertama: rekam semua ID yang sudah ada — jangan tampilkan toast
                    knownIds    = new Set(items.map(function(n) { return n.id; }));
                    isFirstPoll = false;
                } else {
                    // Polling berikutnya: deteksi ID baru (tidak peduli is_read)
                    items.forEach(function(n) {
                        if (!knownIds.has(n.id)) {
                            knownIds.add(n.id);
                            toastQueue.push(n);
                        }
                    });
                    if (toastQueue.length > 0) showNextToast();
                }

                // Render dropdown
                if (items.length === 0) {
                    $('#notifEmpty').show();
                    $('#notifItems').empty();
                    return;
                }
                $('#notifEmpty').hide();
                $('#notifItems').html(items.map(renderNotif).join(''));
            });
        }

        // Klik item dropdown → mark read + navigate
        $(document).on('click', '.notif-item', function () {
            const id  = $(this).data('id');
            const url = $(this).data('url');
            $.post(markReadUrl + '/' + id + '/read', { _token: csrfToken });
            if (url && url !== '#') window.location.href = url;
        });

        // Mark all read
        $('#btnMarkAllRead').on('click', function (e) {
            e.stopPropagation();
            $.post(markAllUrl, { _token: csrfToken }, function () {
                loadNotifications();
                Swal.fire({ icon: 'success', toast: true, position: 'top-end',
                    showConfirmButton: false, timer: 1500, title: 'Semua notifikasi dibaca.' });
            });
        });

        // Load saat bell diklik
        $('#notifBellBtn').on('click', function () { loadNotifications(); });

        // Polling tiap 20 detik
        loadNotifications();
        setInterval(loadNotifications, 20000);

        // ── Sidebar badge helper ─────────────────────────────────────────
        function updateSidebarBadges(counts) {
            function setBadge(id, count) {
                var el = $('#' + id);
                if (count > 0) { el.text(count).show(); } else { el.hide(); }
            }
            setBadge('badge-inbound-draft',   counts.inbound_draft   || 0);
            setBadge('badge-putaway-pending', counts.putaway_pending || 0);
        }
    })();
    </script>

    <style>
    .wms-notif-popup {
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        border-radius: 12px !important;
        box-shadow: 0 8px 32px rgba(0,0,0,.18) !important;
    }
    .wms-notif-popup .swal2-html-container { margin: 0 !important; padding: 0 !important; }
    .wms-notif-popup .swal2-actions { margin-top: 14px !important; }
    </style>

    @stack('scripts')

    {{-- ══════════════════════════════════════════════════════════════════
         GLOBAL CELL QR SCANNER
         Operator bisa scan QR cell dari halaman mana pun (gun scanner
         atau kamera). Popup muncul tanpa navigasi meninggalkan halaman.
    ══════════════════════════════════════════════════════════════════════ --}}

    {{-- Modal cell detail --}}
    <div class="modal fade" id="globalCellModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-scrollable" style="max-width:460px;" role="document">
            <div class="modal-content" style="border-radius:12px;overflow:hidden;">
                <div class="modal-header py-2" style="background:#1a2332;color:#fff;">
                    <h6 class="modal-title font-weight-bold mb-0" id="globalCellModalTitle">
                        <i class="fas fa-map-marker-alt mr-1"></i> —
                    </h6>
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;">&times;</button>
                </div>
                <div class="modal-body py-3 px-3" id="globalCellModalBody" style="min-height:80px;">
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Memuat…
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <a id="globalCellModalLink" href="#" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-external-link-alt mr-1"></i> Buka Halaman Penuh
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        // ── Scanner gun buffer ─────────────────────────────────────────────
        // Scanner guns send keystrokes very fast (~10ms apart) followed by Enter.
        // We accumulate characters and process on Enter.
        let buf = '', timer = null;
        const TIMEOUT_MS = 200; // reset buffer if gap > 200ms (human typing is slower)

        document.addEventListener('keydown', function (e) {
            // Skip when typing in any input / textarea / select
            const tag = document.activeElement && document.activeElement.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

            // Skip modifier-only and function keys
            if (e.key.length > 1 && e.key !== 'Enter') return;
            if (e.ctrlKey || e.altKey || e.metaKey) return;

            clearTimeout(timer);

            if (e.key === 'Enter') {
                const code = buf.trim();
                buf = '';
                if (code.length > 1) fetchCellAndShow(code);
            } else {
                buf += e.key;
                timer = setTimeout(function () { buf = ''; }, TIMEOUT_MS);
            }
        });

        // ── Camera scan (html5-qrcode) — expose global hook ───────────────
        // Pages that use the camera scanner can call this function directly.
        window.globalCellScan = function (raw) { fetchCellAndShow(raw); };

        // ── Fetch cell detail via JSON ─────────────────────────────────────
        function fetchCellAndShow(raw) {
            // Extract code from URL format: http://domain/c/CODE
            let code = raw;
            if (raw.indexOf('/c/') !== -1) {
                code = raw.split('/c/').pop().split(/[/?# ]/)[0];
            }
            if (!code) return;

            // Show modal with loading state immediately
            $('#globalCellModalTitle').html('<i class="fas fa-map-marker-alt mr-1"></i> ' + escHtml(code));
            $('#globalCellModalBody').html('<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat…</div>');
            $('#globalCellModalLink').attr('href', '/c/' + encodeURIComponent(code));
            $('#globalCellModal').modal('show');

            fetch('/c/' + encodeURIComponent(code), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    // Not a cell — silently dismiss
                    $('#globalCellModal').modal('hide');
                    return;
                }
                renderCellModal(data.cell, data.stocks);
            })
            .catch(function () { $('#globalCellModal').modal('hide'); });
        }

        // ── Render modal content ───────────────────────────────────────────
        function renderCellModal(cell, stocks) {
            $('#globalCellModalTitle').html(
                '<i class="fas fa-map-marker-alt mr-1"></i> ' + escHtml(cell.code) +
                (cell.label && cell.label !== cell.code ? ' <small style="font-weight:400;opacity:.75">· ' + escHtml(cell.label) + '</small>' : '')
            );
            $('#globalCellModalLink').attr('href', '/c/' + encodeURIComponent(cell.code));

            const capPct = cell.capacity_max > 0
                ? Math.min(100, Math.round(cell.capacity_used / cell.capacity_max * 100))
                : 0;
            const barColor = capPct >= 90 ? '#dc3545' : (capPct >= 70 ? '#ffc107' : '#28a745');

            const statusMap = {
                available: ['#d4edda','#155724','Tersedia'],
                full:      ['#f8d7da','#721c24','Penuh'],
                partial:   ['#fff3cd','#856404','Sebagian'],
            };
            const [sBg, sFg, sLabel] = statusMap[cell.status] || ['#e2e3e5','#383d41', cell.status];

            let locationHtml = '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">';
            locationHtml += '<span class="badge" style="background:#e9ecef;color:#495057;font-size:11px;">' + escHtml(cell.warehouse) + '</span>';
            locationHtml += '<span class="badge" style="background:#e9ecef;color:#495057;font-size:11px;">Rak ' + escHtml(cell.rack) + '</span>';
            if (cell.level) locationHtml += '<span class="badge" style="background:#e9ecef;color:#495057;font-size:11px;">Level ' + escHtml(String(cell.level)) + '</span>';
            locationHtml += '<span class="badge" style="background:' + sBg + ';color:' + sFg + ';font-size:11px;">' + sLabel + '</span>';
            locationHtml += '</div>';

            let capHtml = '';
            if (cell.capacity_max > 0) {
                capHtml = '<div style="margin-bottom:12px;">'
                    + '<div style="height:8px;background:#e9ecef;border-radius:4px;overflow:hidden;">'
                    + '<div style="height:100%;width:' + capPct + '%;background:' + barColor + ';border-radius:4px;"></div></div>'
                    + '<div style="display:flex;justify-content:space-between;font-size:11px;color:#888;margin-top:3px;">'
                    + '<span>Terisi: <strong>' + cell.capacity_used + '</strong></span>'
                    + '<span>' + capPct + '%</span>'
                    + '<span>Maks: <strong>' + cell.capacity_max + '</strong></span></div></div>';
            }

            let stocksHtml = '<div style="font-size:11px;font-weight:700;color:#6c757d;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">'
                + '<i class="fas fa-boxes mr-1"></i> Isi Cell (' + stocks.length + ' item)</div>';

            if (stocks.length === 0) {
                stocksHtml += '<p class="text-muted text-center py-2 mb-0" style="font-size:13px;">Cell kosong.</p>';
            } else {
                stocks.forEach(function (s, i) {
                    stocksHtml += '<div style="display:flex;align-items:flex-start;padding:10px 0;'
                        + (i < stocks.length - 1 ? 'border-bottom:1px solid #f0f0f0;' : '') + '">'
                        + '<div style="flex:1;min-width:0;">'
                        + '<div style="font-weight:700;font-size:13px;">' + escHtml(s.item_name) + '</div>'
                        + '<div style="font-size:11px;color:#6c757d;">' + escHtml(s.item_sku)
                        + (s.item_merk ? ' · ' + escHtml(s.item_merk) : '') + '</div>'
                        + (s.category ? '<span style="display:inline-block;margin-top:3px;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:600;background:' + escHtml(s.category_color) + ';color:#fff;">' + escHtml(s.category) + '</span>' : '')
                        + '</div>'
                        + '<div style="text-align:right;margin-left:12px;flex-shrink:0;">'
                        + '<div style="font-size:22px;font-weight:800;color:#28a745;line-height:1;">' + s.quantity + '</div>'
                        + '<div style="font-size:10px;color:#6c757d;">' + escHtml(s.unit) + '</div>'
                        + '<div style="font-size:10px;color:#adb5bd;">' + escHtml(s.inbound_date) + '</div>'
                        + '</div></div>';
                });
            }

            $('#globalCellModalBody').html(locationHtml + capHtml + stocksHtml);
        }

        function escHtml(str) {
            if (!str && str !== 0) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
    })();
    </script>
</body>

</html>
