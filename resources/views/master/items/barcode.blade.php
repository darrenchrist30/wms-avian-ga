@extends('layouts.adminlte')

@section('title', 'Barcode - ' . $item->sku)

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                <i class="fas fa-barcode mr-1"></i> Label Barcode Sparepart
                            </div>
                            <a href="{{ route('master.items.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body text-center">

                        <div id="barcode-label" class="p-3 border rounded d-inline-block" style="min-width:300px;">
                            <div class="mb-1">
                                <small class="text-muted font-weight-bold">{{ config('app.name') }}</small>
                            </div>
                            <div class="font-weight-bold mb-1">{{ $item->name }}</div>
                            <svg id="barcode"></svg>
                            <div class="mt-1">
                                <small class="text-muted">SKU: {{ $item->sku }}</small><br>
                                @if ($item->category)
                                    <small class="text-muted">Kategori: {{ $item->category->name }}</small><br>
                                @endif
                                @if ($item->unit)
                                    <small class="text-muted">Satuan: {{ $item->unit->code }}</small>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3">
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="fas fa-print mr-1"></i>Cetak
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        var barcodeValue = '{{ $item->barcode ?? $item->sku }}';
        JsBarcode('#barcode', barcodeValue, {
            format: 'CODE128',
            width: 2,
            height: 60,
            displayValue: true,
            fontSize: 14,
            margin: 8
        });
    </script>
    <style>
        @media print {
            .main-header, .main-sidebar, .content-header, .card-footer,
            .btn, .breadcrumb, footer { display: none !important; }
            .content-wrapper { margin-left: 0 !important; }
            #barcode-label { border: 1px solid #999 !important; }
        }
    </style>
@endpush
