@extends('layouts.adminlte')

@section('title', $typeForm == 'create' ? 'Tambah Inbound Order' : 'Edit Inbound Order')

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
                            <div class="card {{ $typeForm == 'create' ? 'card-primary' : 'card-warning' }} card-outline mb-3">
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
                                                    <input type="date" name="do_date"
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
                                                    <input type="datetime-local" name="received_at"
                                                        class="form-control @error('received_at') is-invalid @enderror"
                                                        value="{{ old('received_at', isset($data->received_at) ? $data->received_at->format('Y-m-d\TH:i') : '') }}">
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
                                                        <option value="">-- Pilih Warehouse --</option>
                                                        @foreach ($warehouses as $wh)
                                                            <option value="{{ $wh->id }}"
                                                                {{ old('warehouse_id', $data->warehouse_id ?? '') == $wh->id ? 'selected' : '' }}>
                                                                {{ $wh->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('warehouse_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label class="col-sm-4 col-form-label">Supplier</label>
                                                <div class="col-sm-8">
                                                    <select name="supplier_id"
                                                        class="form-control @error('supplier_id') is-invalid @enderror">
                                                        <option value="">-- Tanpa Supplier --</option>
                                                        @foreach ($suppliers as $sup)
                                                            <option value="{{ $sup->id }}"
                                                                {{ old('supplier_id', $data->supplier_id ?? '') == $sup->id ? 'selected' : '' }}>
                                                                {{ $sup->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('supplier_id')
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
                                    <button type="button" class="btn btn-sm btn-success" id="btnAddRow">
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
                                                    <th width="130">LPN</th>
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
                                                                    class="form-control form-control-sm select-item @error('items.' . $idx . '.item_id') is-invalid @enderror">
                                                                    <option value="">-- Pilih Item --</option>
                                                                    @foreach ($items as $item)
                                                                        <option value="{{ $item->id }}"
                                                                            data-unit="{{ $item->unit->code ?? '' }}"
                                                                            {{ $oi->item_id == $item->id ? 'selected' : '' }}>
                                                                            [{{ $item->sku }}] {{ $item->name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <input type="text" name="items[{{ $idx }}][lpn]"
                                                                    class="form-control form-control-sm"
                                                                    value="{{ $oi->lpn }}" placeholder="LPN">
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
                                                    {{-- one blank row for create --}}
                                                    <tr>
                                                        <td class="text-center row-num">1</td>
                                                        <td>
                                                            <select name="items[0][item_id]"
                                                                class="form-control form-control-sm select-item">
                                                                <option value="">-- Pilih Item --</option>
                                                                @foreach ($items as $item)
                                                                    <option value="{{ $item->id }}"
                                                                        data-unit="{{ $item->unit->code ?? '' }}">
                                                                        [{{ $item->sku }}] {{ $item->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td><input type="text" name="items[0][lpn]" class="form-control form-control-sm" placeholder="LPN"></td>
                                                        <td><input type="number" name="items[0][quantity_ordered]" class="form-control form-control-sm text-center" value="1" min="1"></td>
                                                        <td><input type="number" name="items[0][quantity_received]" class="form-control form-control-sm text-center" value="0" min="0"></td>
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

    {{-- Hidden template row --}}
    <template id="row-template">
        <tr>
            <td class="text-center row-num"></td>
            <td>
                <select name="items[__IDX__][item_id]" class="form-control form-control-sm select-item">
                    <option value="">-- Pilih Item --</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}" data-unit="{{ $item->unit->code ?? '' }}">
                            [{{ $item->sku }}] {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td><input type="text" name="items[__IDX__][lpn]" class="form-control form-control-sm" placeholder="LPN"></td>
            <td><input type="number" name="items[__IDX__][quantity_ordered]" class="form-control form-control-sm text-center" value="1" min="1"></td>
            <td><input type="number" name="items[__IDX__][quantity_received]" class="form-control form-control-sm text-center" value="0" min="0"></td>
            <td><input type="text" name="items[__IDX__][notes]" class="form-control form-control-sm"></td>
            <td class="text-center">
                <button type="button" class="btn btn-xs btn-danger btnRemoveRow"><i class="fas fa-times"></i></button>
            </td>
        </tr>
    </template>
@endsection

@push('scripts')
<script>
    var rowIndex = $('#item-rows tr').length;

    function reNumberRows() {
        $('#item-rows tr').each(function(i) {
            $(this).find('.row-num').text(i + 1);
        });
    }

    $('#btnAddRow').on('click', function() {
        var template = document.getElementById('row-template').innerHTML;
        var html = template.replace(/__IDX__/g, rowIndex);
        $('#item-rows').append(html);
        rowIndex++;
        reNumberRows();
    });

    $(document).on('click', '.btnRemoveRow', function() {
        if ($('#item-rows tr').length <= 1) {
            Swal.fire('Peringatan', 'Minimal harus ada 1 item.', 'warning');
            return;
        }
        $(this).closest('tr').remove();
        reNumberRows();
    });
</script>
@endpush
