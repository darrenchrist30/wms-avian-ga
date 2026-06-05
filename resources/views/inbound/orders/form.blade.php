@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Inbound Order' : 'Edit Inbound Order')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css">
<style>
    .select2-container--bootstrap4 .select2-selection--single {
        height: calc(1.5em + .5rem + 2px) !important;
        font-size: .875rem !important;
        padding: .25rem .5rem !important;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 1.5 !important;
        padding-left: 0 !important;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + .5rem + 2px) !important;
    }
    .select2-container { width: 100% !important; }
</style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">
                    {{ $typeForm == 'create' ? 'Tambah Inbound Order' : 'Edit Inbound Order: ' . ($data->do_number ?? '') }}
                </h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">
                                @if ($typeForm == 'create')
                                    <i class="fas fa-plus-circle mr-1"></i> Form Inbound Order Baru
                                @else
                                    <i class="fas fa-edit mr-1"></i> Edit Inbound Order
                                @endif
                            </div>
                            <a href="{{ route('inbound.orders.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                        </div>
                    </div>

                    <form id="form-inbound"
                        action="{{ $typeForm == 'create' ? route('inbound.orders.store') : route('inbound.orders.update', $data->id) }}"
                        method="POST">
                        @csrf
                        @if ($typeForm == 'edit')
                            @method('PUT')
                        @endif

                        <div class="card-body">

                            @if ($errors->any())
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                    {{ session('error') }}
                                </div>
                            @endif

                            {{-- Header --}}
                            <div class="card card-outline mb-3">
                                <div class="card-header">
                                    <div class="font-weight-bold"><i class="fas fa-file-alt mr-1"></i> Header Surat Jalan</div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">No. Surat Jalan <span class="text-danger">*</span></label>
                                                <div class="col-sm-8">
                                                    <input type="text" name="do_number"
                                                        class="form-control @error('do_number') is-invalid @enderror"
                                                        value="{{ old('do_number', $data->do_number ?? '') }}"
                                                        placeholder="Contoh: SJ-2026-00001"
                                                        style="text-transform:uppercase;">
                                                    @error('do_number')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Tgl Surat Jalan <span class="text-danger">*</span></label>
                                                <div class="col-sm-8">
                                                    <input type="date" name="do_date" id="doDate"
                                                        class="form-control @error('do_date') is-invalid @enderror"
                                                        value="{{ old('do_date', isset($data->do_date) ? $data->do_date->format('Y-m-d') : '') }}">
                                                    @error('do_date')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Tgl Diterima</label>
                                                <div class="col-sm-8">
                                                    <input type="date" name="received_at" id="receivedAt"
                                                        class="form-control @error('received_at') is-invalid @enderror"
                                                        value="{{ old('received_at', isset($data->received_at) ? $data->received_at->format('Y-m-d') : '') }}"
                                                        readonly style="background:#e9ecef;cursor:not-allowed;">
                                                    @error('received_at')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Warehouse <span class="text-danger">*</span></label>
                                                <div class="col-sm-8">
                                                    <select name="warehouse_id"
                                                        class="form-control @error('warehouse_id') is-invalid @enderror">
                                                        @php
                                                            $defaultWh = old('warehouse_id',
                                                                $data->warehouse_id ?? $warehouses->first(fn($w) => str_contains(strtolower($w->name), 'sparepart'))?->id ?? ''
                                                            );
                                                        @endphp
                                                        <option value="">-- Pilih Warehouse --</option>
                                                        @foreach ($warehouses as $wh)
                                                            <option value="{{ $wh->id }}"
                                                                {{ $defaultWh == $wh->id ? 'selected' : '' }}>
                                                                {{ $wh->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('warehouse_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group row mb-0">
                                        <label class="col-sm-2 col-form-label">Catatan</label>
                                        <div class="col-sm-10">
                                            <textarea name="notes" rows="2"
                                                class="form-control @error('notes') is-invalid @enderror"
                                                placeholder="Catatan tambahan...">{{ old('notes', $data->notes ?? '') }}</textarea>
                                            @error('notes')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Detail Items --}}
                            <div class="card card-secondary card-outline">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div class="font-weight-bold"><i class="fas fa-boxes mr-1"></i> Detail Item</div>
                                    <button type="button" class="btn btn-primary btn-sm ml-auto" id="btnAddRow">
                                        <i class="fas fa-plus mr-1"></i> Tambah Item
                                    </button>
                                </div>
                                <div class="card-body p-0">
                                    @error('items')
                                        <div class="alert alert-danger m-2">{{ $message }}</div>
                                    @enderror
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm mb-0" id="item-table">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th width="35" class="text-center">#</th>
                                                    <th>Item / Sparepart</th>
                                                    <th width="110" class="text-center">Qty Order <span class="text-danger">*</span></th>
                                                    <th width="110" class="text-center">Qty Terima</th>
                                                    <th>Catatan Item</th>
                                                    <th width="50" class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="item-rows">
                                                @if ($typeForm == 'edit' && $data->items->isNotEmpty())
                                                    @foreach ($data->items as $idx => $oi)
                                                        <tr>
                                                            <td class="text-center row-num">{{ $idx + 1 }}</td>
                                                            <td>
                                                                <select name="items[{{ $idx }}][item_id]"
                                                                    class="form-control form-control-sm select-item-ajax @error('items.' . $idx . '.item_id') is-invalid @enderror">
                                                                    @if ($oi->item)
                                                                        <option value="{{ $oi->item->id }}" selected
                                                                            data-unit="{{ $oi->item->unit->code ?? '' }}">
                                                                            [{{ $oi->item->sku }}] {{ $oi->item->name }}
                                                                        </option>
                                                                    @endif
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <input type="number" name="items[{{ $idx }}][quantity_ordered]"
                                                                    class="form-control form-control-sm text-center"
                                                                    value="{{ $oi->quantity_ordered }}" min="1">
                                                            </td>
                                                            <td>
                                                                <input type="number" name="items[{{ $idx }}][quantity_received]"
                                                                    class="form-control form-control-sm text-center"
                                                                    value="{{ $oi->quantity_received }}" min="0">
                                                            </td>
                                                            <td>
                                                                <input type="text" name="items[{{ $idx }}][notes]"
                                                                    class="form-control form-control-sm"
                                                                    value="{{ $oi->notes }}">
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-xs btn-danger btnRemoveRow">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @else
                                                    <tr>
                                                        <td class="text-center row-num">1</td>
                                                        <td>
                                                            <select name="items[0][item_id]"
                                                                class="form-control form-control-sm select-item-ajax">
                                                            </select>
                                                        </td>
                                                        <td><input type="number" name="items[0][quantity_ordered]" class="form-control form-control-sm text-center" value="1" min="1"></td>
                                                        <td><input type="number" name="items[0][quantity_received]" class="form-control form-control-sm text-center" value="1" min="0"></td>
                                                        <td><input type="text" name="items[0][notes]" class="form-control form-control-sm"></td>
                                                        <td class="text-center">
                                                            <button type="button" class="btn btn-xs btn-danger btnRemoveRow">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        </div>{{-- end card-body --}}

                        <div class="card-footer">
                            <div class="d-flex justify-content-end">
                                <a href="{{ route('inbound.orders.index') }}" class="btn btn-secondary mr-2">
                                    <i class="fas fa-times mr-1"></i>Batal
                                </a>
                                @if ($typeForm == 'create')
                                    <button type="submit" form="form-inbound" class="btn btn-primary">
                                        <i class="fas fa-save mr-1"></i>Simpan
                                    </button>
                                @else
                                    <button type="submit" form="form-inbound" class="btn btn-warning text-white">
                                        <i class="fas fa-save mr-1"></i>Update
                                    </button>
                                @endif
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Template row (tanpa option — diisi Select2 saat init) --}}
    <template id="row-template">
        <tr>
            <td class="text-center row-num"></td>
            <td>
                <select name="items[__IDX__][item_id]" class="form-control form-control-sm select-item-ajax"></select>
            </td>
            <td><input type="number" name="items[__IDX__][quantity_ordered]" class="form-control form-control-sm text-center" value="1" min="1"></td>
            <td><input type="number" name="items[__IDX__][quantity_received]" class="form-control form-control-sm text-center" value="1" min="0"></td>
            <td><input type="text" name="items[__IDX__][notes]" class="form-control form-control-sm"></td>
            <td class="text-center">
                <button type="button" class="btn btn-xs btn-danger btnRemoveRow"><i class="fas fa-times"></i></button>
            </td>
        </tr>
    </template>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    var SEARCH_URL = '{{ route('inbound.orders.search-items') }}';
    var rowIndex   = $('#item-rows tr').length;

    function initSelect2(el) {
        $(el).select2({
            theme: 'bootstrap4',
            placeholder: '-- Ketik SKU / nama item --',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: SEARCH_URL,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    return { results: data.results };
                },
                cache: true,
            },
        });
    }

    // Init semua select yang sudah ada di DOM (create 1 row + edit rows)
    $('.select-item-ajax').each(function () {
        initSelect2(this);
    });

    function reNumberRows() {
        $('#item-rows tr').each(function (i) {
            $(this).find('.row-num').text(i + 1);
        });
    }

    $('#btnAddRow').on('click', function () {
        var template = document.getElementById('row-template').innerHTML;
        var html     = template.replace(/__IDX__/g, rowIndex);
        var $row     = $(html);
        $('#item-rows').append($row);
        initSelect2($row.find('.select-item-ajax')[0]);
        rowIndex++;
        reNumberRows();
    });

    $(document).on('click', '.btnRemoveRow', function () {
        if ($('#item-rows tr').length <= 1) {
            Swal.fire('Peringatan', 'Minimal harus ada 1 item.', 'warning');
            return;
        }
        $(this).closest('tr').remove();
        reNumberRows();
    });

    // Sync tgl diterima = tgl surat jalan secara realtime
    $('#doDate').on('change', function () {
        $('#receivedAt').val($(this).val());
    });

    // Sync qty_received = qty_ordered secara realtime
    $(document).on('input change', '[name*="quantity_ordered"]', function () {
        var $row = $(this).closest('tr');
        $row.find('[name*="quantity_received"]').val($(this).val());
    });
</script>
@endpush
