<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Protected Routes (auth + active user)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active.user'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    /*
    |------------------------------------------------------------------
    | Master Data
    |------------------------------------------------------------------
    */
    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('categories', \App\Http\Controllers\Master\ItemCategoryController::class);
        Route::resource('units', \App\Http\Controllers\Master\UnitController::class);
        Route::resource('suppliers', \App\Http\Controllers\Master\SupplierController::class);
        Route::resource('items', \App\Http\Controllers\Master\ItemController::class);
        Route::get('items/{item}/barcode', [\App\Http\Controllers\Master\ItemController::class, 'barcode'])
            ->name('items.barcode');
    });

    /*
    |------------------------------------------------------------------
    | Location Management (Warehouse → Zone → Rack → Cell)
    |------------------------------------------------------------------
    */
    Route::prefix('location')->name('location.')->group(function () {
        Route::resource('warehouses', \App\Http\Controllers\Location\WarehouseController::class);
        Route::resource('zones', \App\Http\Controllers\Location\ZoneController::class);
        Route::resource('racks', \App\Http\Controllers\Location\RackController::class);
        Route::resource('cells', \App\Http\Controllers\Location\CellController::class);
        Route::get('cells/{cell}/stock', [\App\Http\Controllers\Location\CellController::class, 'stockDetail'])
            ->name('cells.stock');
    });

    /*
    |------------------------------------------------------------------
    | Inbound (Penerimaan Barang dari ERP)
    |------------------------------------------------------------------
    */
    Route::prefix('inbound')->name('inbound.')->group(function () {
        Route::resource('orders', \App\Http\Controllers\Inbound\InboundOrderController::class);
        Route::post('orders/{order}/sync-erp', [\App\Http\Controllers\Inbound\InboundOrderController::class, 'syncFromErp'])
            ->name('orders.sync-erp');
        Route::post('orders/{order}/process-ga', [\App\Http\Controllers\Inbound\InboundOrderController::class, 'processGA'])
            ->name('orders.process-ga');
    });

    /*
    |------------------------------------------------------------------
    | Put-Away (Penempatan Barang + Konfirmasi Barcode)
    |------------------------------------------------------------------
    */
    Route::prefix('putaway')->name('putaway.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PutAway\PutAwayController::class, 'index'])->name('index');
        Route::get('{order}', [\App\Http\Controllers\PutAway\PutAwayController::class, 'show'])->name('show');
        Route::post('{recommendation}/confirm', [\App\Http\Controllers\PutAway\PutAwayController::class, 'confirm'])->name('confirm');
        Route::post('{recommendation}/override', [\App\Http\Controllers\PutAway\PutAwayController::class, 'override'])
            ->name('override')->middleware('role:admin,supervisor');
        Route::post('scan-barcode', [\App\Http\Controllers\PutAway\PutAwayController::class, 'scanBarcode'])->name('scan-barcode');
    });

    /*
    |------------------------------------------------------------------
    | Stock Management
    |------------------------------------------------------------------
    */
    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Stock\StockController::class, 'index'])->name('index');
        Route::get('search', [\App\Http\Controllers\Stock\StockController::class, 'search'])->name('search');
        Route::get('movements', [\App\Http\Controllers\Stock\StockController::class, 'movements'])->name('movements');
        Route::get('low-stock', [\App\Http\Controllers\Stock\StockController::class, 'lowStock'])->name('low-stock');
        Route::get('near-expiry', [\App\Http\Controllers\Stock\StockController::class, 'nearExpiry'])->name('near-expiry');
        Route::get('{item}', [\App\Http\Controllers\Stock\StockController::class, 'show'])->name('show');
        Route::post('transfer', [\App\Http\Controllers\Stock\StockController::class, 'transfer'])
            ->name('transfer')->middleware('role:admin,supervisor');
    });

    /*
    |------------------------------------------------------------------
    | 3D Warehouse Visualization (Three.js)
    |------------------------------------------------------------------
    */
    Route::prefix('warehouse-3d')->name('warehouse3d.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Warehouse3DController::class, 'index'])->name('index');
        Route::get('data', [\App\Http\Controllers\Warehouse3DController::class, 'data'])->name('data');
        Route::get('cell/{cell}', [\App\Http\Controllers\Warehouse3DController::class, 'cellDetail'])->name('cell-detail');
    });

    /*
    |------------------------------------------------------------------
    | Reports (Highcharts)
    |------------------------------------------------------------------
    */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('inventory', [\App\Http\Controllers\Reports\ReportController::class, 'inventory'])->name('inventory');
        Route::get('inbound', [\App\Http\Controllers\Reports\ReportController::class, 'inbound'])->name('inbound');
        Route::get('putaway', [\App\Http\Controllers\Reports\ReportController::class, 'putaway'])->name('putaway');
        Route::get('movements', [\App\Http\Controllers\Reports\ReportController::class, 'movements'])->name('movements');
        Route::get('ga-effectiveness', [\App\Http\Controllers\Reports\ReportController::class, 'gaEffectiveness'])->name('ga-effectiveness');
        Route::get('export/{type}', [\App\Http\Controllers\Reports\ReportController::class, 'export'])
            ->name('export')->middleware('permission:report.export');
    });

    /*
    |------------------------------------------------------------------
    | User & Role Management (Admin only)
    |------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', \App\Http\Controllers\UserManagement\UserController::class);
        Route::resource('roles', \App\Http\Controllers\UserManagement\RoleController::class);
    });
});
