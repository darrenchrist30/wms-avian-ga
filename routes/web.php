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
Route::post('/login', [LoginController::class, 'login'])->name('login.post')->middleware('throttle:5,1');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Public Cell Detail (no auth — scan QR label)
|--------------------------------------------------------------------------
*/
Route::get('/c/{code}', [\App\Http\Controllers\PublicCellController::class, 'show'])
    ->name('public.cell')
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Protected Routes (auth + active user)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active.user'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/trend', [DashboardController::class, 'trendData'])->name('dashboard.trend');
    Route::post('dashboard/send-wa-alert', [DashboardController::class, 'sendWaAlert'])->name('dashboard.send-wa-alert');

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
        Route::post('{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('read');
        Route::post('read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('read-all');
    });

    /*
    |------------------------------------------------------------------
    | Master Data
    |------------------------------------------------------------------
    */
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('categories/datatable', [\App\Http\Controllers\Master\ItemCategoryController::class, 'datatable'])->name('categories.datatable');
        Route::resource('categories', \App\Http\Controllers\Master\ItemCategoryController::class);

        Route::get('units/datatable', [\App\Http\Controllers\Master\UnitController::class, 'datatable'])->name('units.datatable');
        Route::resource('units', \App\Http\Controllers\Master\UnitController::class);

        Route::get('suppliers/datatable', [\App\Http\Controllers\Master\SupplierController::class, 'datatable'])->name('suppliers.datatable');
        Route::resource('suppliers', \App\Http\Controllers\Master\SupplierController::class);

        Route::get('affinities/datatable', [\App\Http\Controllers\Master\ItemAffinityController::class, 'datatable'])->name('affinities.datatable');
        Route::get('affinities',           [\App\Http\Controllers\Master\ItemAffinityController::class, 'index'])->name('affinities.index');

        Route::get('items/datatable', [\App\Http\Controllers\Master\ItemController::class, 'datatable'])->name('items.datatable');
        Route::get('items/template',  [\App\Http\Controllers\Master\ItemController::class, 'downloadTemplate'])->name('items.template');
        Route::post('items/import',   [\App\Http\Controllers\Master\ItemController::class, 'import'])->name('items.import');
        Route::get('items/scan',      [\App\Http\Controllers\Master\ItemController::class, 'scanPage'])->name('items.scan');
        Route::get('items/lookup',    [\App\Http\Controllers\Master\ItemController::class, 'lookup'])->name('items.lookup');
        Route::resource('items', \App\Http\Controllers\Master\ItemController::class);
        Route::get('items/{item}/barcode', [\App\Http\Controllers\Master\ItemController::class, 'barcode'])->name('items.barcode');
    });

    /*
    |------------------------------------------------------------------
    | Location Management (Warehouse → Zone → Rack → Cell)
    |------------------------------------------------------------------
    */
    Route::prefix('location')->name('location.')->group(function () {
        Route::get('warehouses/datatable', [\App\Http\Controllers\Location\WarehouseController::class, 'datatable'])->name('warehouses.datatable');
        Route::resource('warehouses', \App\Http\Controllers\Location\WarehouseController::class);

Route::get('racks/datatable', [\App\Http\Controllers\Location\RackController::class, 'datatable'])->name('racks.datatable');
        Route::resource('racks', \App\Http\Controllers\Location\RackController::class);

        Route::get('cells/datatable', [\App\Http\Controllers\Location\CellController::class, 'datatable'])->name('cells.datatable');
        Route::get('cells/lookup',    [\App\Http\Controllers\Location\CellController::class, 'lookup'])->name('cells.lookup');
        Route::get('cells/bulk-qr',   [\App\Http\Controllers\Location\CellController::class, 'bulkQrLabel'])->name('cells.bulk-qr');
        Route::resource('cells', \App\Http\Controllers\Location\CellController::class);
        Route::get('cells/{cell}/stock',    [\App\Http\Controllers\Location\CellController::class, 'stockDetail'])->name('cells.stock');
        Route::get('cells/{cell}/qr-label', [\App\Http\Controllers\Location\CellController::class, 'qrLabel'])->name('cells.qr-label');
    });

    /*
    |------------------------------------------------------------------
    | Inbound (Penerimaan Barang dari ERP)
    |------------------------------------------------------------------
    */
    Route::prefix('inbound')->name('inbound.')->group(function () {
        Route::get('orders/datatable', [\App\Http\Controllers\Inbound\InboundOrderController::class, 'datatable'])->name('orders.datatable');
        Route::resource('orders', \App\Http\Controllers\Inbound\InboundOrderController::class);
        Route::post('orders/{order}/sync-erp', [\App\Http\Controllers\Inbound\InboundOrderController::class, 'syncFromErp'])
            ->name('orders.sync-erp');
        Route::post('orders/{order}/process-ga', [\App\Http\Controllers\Inbound\InboundOrderController::class, 'processGA'])
            ->name('orders.process-ga')->middleware('role:admin,supervisor,operator');
        Route::post('orders/batch-ga', [\App\Http\Controllers\Inbound\InboundOrderController::class, 'batchProcessGA'])
            ->name('orders.batch-ga')->middleware('role:admin,supervisor,operator');
    });

    /*
    |------------------------------------------------------------------
    | Put-Away — Penempatan Barang via QR Scan (STEP 4)
    |------------------------------------------------------------------
    */
    Route::prefix('putaway')->name('putaway.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PutAway\PutAwayController::class, 'index'])->name('index');
        Route::get('queue', [\App\Http\Controllers\PutAway\PutAwayController::class, 'queue'])->name('queue');
        Route::get('{order}', [\App\Http\Controllers\PutAway\PutAwayController::class, 'show'])->name('show');
        Route::post('scan-qr', [\App\Http\Controllers\PutAway\PutAwayController::class, 'scanQr'])->name('scan-qr');
        Route::get('{order}/alternative-cells', [\App\Http\Controllers\PutAway\PutAwayController::class, 'alternativeCells'])
            ->name('alternative-cells');
        Route::get('{order}/fast-slow-suggestions', [\App\Http\Controllers\PutAway\PutAwayController::class, 'fastSlowSuggestions'])
            ->name('fast-slow-suggestions');
        Route::post('{order}/items/{detail}/confirm', [\App\Http\Controllers\PutAway\PutAwayController::class, 'confirm'])
            ->name('confirm');
        Route::post('{order}/items/{detail}/override', [\App\Http\Controllers\PutAway\PutAwayController::class, 'override'])
            ->name('override')->middleware('role:admin,supervisor');
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
        Route::post('transfer', [\App\Http\Controllers\Stock\StockController::class, 'transfer'])
            ->name('transfer')->middleware('role:admin,supervisor');

        Route::get('{item}', [\App\Http\Controllers\Stock\StockController::class, 'show'])->name('show');
    });

    /*
    |------------------------------------------------------------------
    | Outbound (FIFO Picking)
    |------------------------------------------------------------------
    */
    Route::prefix('outbound')->name('outbound.')->middleware('role:admin,supervisor,operator')->group(function () {
        Route::get('/',              [\App\Http\Controllers\Outbound\OutboundController::class, 'index'])->name('index');
        Route::get('datatable',      [\App\Http\Controllers\Outbound\OutboundController::class, 'datatable'])->name('datatable');
        Route::get('search-items',   [\App\Http\Controllers\Outbound\OutboundController::class, 'searchItems'])->name('search-items');
        Route::get('find-item',      [\App\Http\Controllers\Outbound\OutboundController::class, 'findItem'])->name('find-item');
        Route::get('create',         [\App\Http\Controllers\Outbound\OutboundController::class, 'create'])->name('create');
        Route::post('batch-preview', [\App\Http\Controllers\Outbound\OutboundController::class, 'batchPreview'])->name('batch-preview');
        Route::post('batch-confirm', [\App\Http\Controllers\Outbound\OutboundController::class, 'batchConfirm'])->name('batch-confirm');
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
        Route::get('cells-by-item', [\App\Http\Controllers\Warehouse3DController::class, 'cellsByItem'])->name('cells-by-item');
        Route::get('column', [\App\Http\Controllers\Warehouse3DController::class, 'columnDetail'])->name('column-detail');
        Route::get('grup', [\App\Http\Controllers\Warehouse3DController::class, 'grupDetail'])->name('grup-detail');
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
        Route::get('ga-effectiveness/export/pdf', [\App\Http\Controllers\Reports\GaEffectivenessExportController::class, 'pdf'])
            ->name('ga-effectiveness.export.pdf')->middleware('permission:report.export');
        Route::get('ga-effectiveness/export/excel', [\App\Http\Controllers\Reports\GaEffectivenessExportController::class, 'excel'])
            ->name('ga-effectiveness.export.excel')->middleware('permission:report.export');
        Route::get('export/{type}', [\App\Http\Controllers\Reports\ReportController::class, 'export'])
            ->name('export')->middleware('permission:report.export');
    });


    /*
    |------------------------------------------------------------------
    | User & Role Management (Admin only)
    |------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        Route::get('api-tokens', [\App\Http\Controllers\ApiTokenController::class, 'index'])->name('api-tokens.index');
        Route::post('api-tokens', [\App\Http\Controllers\ApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::delete('api-tokens/{id}', [\App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

        Route::get('users/datatable', [\App\Http\Controllers\UserManagement\UserController::class, 'datatable'])->name('users.datatable');
        Route::resource('users', \App\Http\Controllers\UserManagement\UserController::class);

        Route::get('roles/datatable', [\App\Http\Controllers\UserManagement\RoleController::class, 'datatable'])->name('roles.datatable');
        Route::resource('roles', \App\Http\Controllers\UserManagement\RoleController::class);

        Route::get('audit', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit/datatable', [\App\Http\Controllers\AuditLogController::class, 'datatable'])->name('audit.datatable');
    });
});
