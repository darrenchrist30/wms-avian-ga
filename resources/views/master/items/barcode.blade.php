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
                    <div class="card-body text-center pt-2">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible text-left">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
                            </div>
                        @endif

                        <div id="barcode-label" class="p-3 border rounded text-center mx-auto" style="width:300px;">
                            <div class="mb-1">
                                <small class="text-muted font-weight-bold">{{ config('app.name') }}</small>
                            </div>
                            <div class="font-weight-bold mb-2">{{ $item->name }}</div>
                            <canvas id="itemQr" style="margin-bottom:8px;"></canvas>
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
    <script src="{{ asset('js/qrious.min.js') }}"></script>
    <script>
        var skuValue = '{{ $item->barcode ?? $item->sku }}';
        new QRious({
            element: document.getElementById('itemQr'),
            value: skuValue,
            size: 160,
            level: 'H',
            background: '#ffffff',
            foreground: '#1a2332',
            padding: 4
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
