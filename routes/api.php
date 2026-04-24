<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\InboundReceiveController;
use App\Http\Controllers\Api\V1\MasterSyncController;

/*
|--------------------------------------------------------------------------
| WMS API Routes — v1
|--------------------------------------------------------------------------
|
| Semua endpoint disini digunakan oleh sistem ERP untuk berinteraksi
| dengan WMS. Autentikasi menggunakan Laravel Sanctum (Bearer Token).
|
| Rate limit default: 60 request per menit per token.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    /*
    |------------------------------------------------------------------
    | Health Check (public — tidak butuh autentikasi)
    | GET /api/v1/ping
    |------------------------------------------------------------------
    */
    Route::get('ping', function () {
        return response()->json([
            'success'   => true,
            'message'   => 'WMS Avian API aktif',
            'version'   => 'v1',
            'timestamp' => now()->toIso8601String(),
        ]);
    })->name('ping');

    /*
    |------------------------------------------------------------------
    | ERP Integration Endpoints (butuh Bearer Token)
    |------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

        /*
        |------------------------------------------------------------------
        | Master Data Sync — ERP → WMS
        |
        | Dipanggil ERP untuk menjaga konsistensi master data.
        | Sebaiknya dijadwalkan setiap malam atau saat ada perubahan di ERP.
        |
        | POST /api/v1/master/items/sync      — upsert item dari ERP
        | POST /api/v1/master/suppliers/sync  — upsert supplier dari ERP
        |------------------------------------------------------------------
        */
        Route::prefix('master')->name('master.')->group(function () {
            Route::post('items/sync',     [MasterSyncController::class, 'syncItems'])
                ->name('items.sync');

            Route::post('suppliers/sync', [MasterSyncController::class, 'syncSuppliers'])
                ->name('suppliers.sync');
        });

        /*
        |------------------------------------------------------------------
        | Inbound — Penerimaan Barang dari ERP
        |
        | Dipanggil ERP setiap kali Delivery Order baru dibuat di SAP.
        |
        | POST   /api/v1/inbound/receive     — ERP kirim data DO baru
        | GET    /api/v1/inbound             — ERP list semua DO (filter + paginasi)
        | GET    /api/v1/inbound/{doNumber}  — ERP cek status 1 DO
        |------------------------------------------------------------------
        */
        Route::prefix('inbound')->name('inbound.')->group(function () {
            Route::post('receive',       [InboundReceiveController::class, 'receive'])
                ->name('receive');

            Route::get('/',              [InboundReceiveController::class, 'index'])
                ->name('index');

            Route::get('{doNumber}',     [InboundReceiveController::class, 'show'])
                ->name('show');
        });

    });

});
