@extends('layouts.adminlte')

@section('title', 'Scan Opname — ' . $opname->opname_number)

@push('styles')
<style>
    #scanInput {
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 1px;
        border: 2px solid #0d8564;
        border-radius: 8px;
        background: #f0fff8;
    }
    #scanInput:focus { box-shadow: 0 0 0 3px rgba(13,133,100,.2); }

    .item-found-card {
        border: 2px solid #0d8564;
        border-radius: 10px;
        background: #f0fff8;
        animation: fadeInDown .25s ease;
    }
    .item-error-card {
        border: 2px solid #dc3545;
        border-radius: 10px;
        background: #fff5f5;
        animation: fadeInDown .25s ease;
    }
    @keyframes fadeInDown {
        from { opacity:0; transform:translateY(-8px); }
        to   { opacity:1; transform:translateY(0); }
    }

    .diff-surplus  { color: #28a745; font-weight: 700; }
    .diff-shortage { color: #dc3545; font-weight: 700; }
    .diff-match    { color: #6c757d; font-weight: 700; }

    .result-row { transition: background .2s; }
    .result-row:hover { background: #f8f9fa; }

    .scan-badge {
        display: inline-flex; align-items: center;
        background: #1a2332; color: #fff;
        border-radius: 20px; padding: 4px 14px;
        font-size: 12px; font-weight: 600;
    }
    .scan-badge i { margin-right: 6px; color: #0d8564; }

    #qty-input-section { display: none; }
</style>
@endpush

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1a2332">
                <i class="fas fa-barcode mr-2" style="color:#0d8564"></i>
                Scan Opname — {{ $opname->opname_number }}
            </h4>
            <small class="text-muted">{{ $opname->warehouse?->name }} · {{ $opname->opname_date?->format('d/m/Y') }}</small>
        </div>
        <div class="d-flex align-items-center" style="gap:8px">
            @if($opname->status === 'in_progress')
                <form action="{{ route('opname.complete', $opname->id) }}" method="POST"
                      onsubmit="return confirm('Selesaikan sesi opname ini? Pastikan semua item sudah di-scan.')">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-check-circle mr-1"></i> Selesaikan Opname
                    </button>
                </form>
            @endif
            <a href="{{ route('opname.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="row">

        {{-- ── KOLOM KIRI: Scan Panel ── --}}
        <div class="col-lg-5 mb-3">

            {{-- Summary badge --}}
            <div class="d-flex flex-wrap gap-2 mb-3" style="gap:8px">
                <span class="scan-badge"><i class="fas fa-clipboard-check"></i> <span id="cntTotal">{{ $totalCounted }}</span> Item Tercatat</span>
                <span class="scan-badge" style="background:#155724"><i class="fas fa-equals"></i> <span id="cntMatch">{{ $totalMatch }}</span> Sesuai</span>
                <span class="scan-badge" style="background:#721c24"><i class="fas fa-arrow-down"></i> <span id="cntShortage">{{ $totalShortage }}</span> Kurang</span>
                <span class="scan-badge" style="background:#856404"><i class="fas fa-arrow-up"></i> <span id="cntSurplus">{{ $totalSurplus }}</span> Lebih</span>
            </div>

            {{-- Input scan --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2" style="background:#1a2332;color:#fff;font-size:13px;font-weight:600">
                    <i class="fas fa-barcode mr-2" style="color:#0d8564"></i>
                    Scan Barcode Item
                </div>
                <div class="card-body">
                    <div class="input-group mb-2">
                        <input type="text" id="scanInput" class="form-control"
                               placeholder="Scan barcode / ketik SKU item…"
                               autocomplete="off" autofocus>
                        <div class="input-group-append">
                            <button class="btn btn-success" id="btnLookup" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        Arahkan scanner ke barcode item (SKU atau ERP Code). Tekan Enter untuk cari.
                    </small>
                </div>
            </div>

            {{-- Hasil lookup item --}}
            <div id="itemFoundCard" class="item-found-card p-3 mb-3" style="display:none">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div>
                        <div class="font-weight-bold" id="foundItemName" style="font-size:15px"></div>
                        <div class="text-muted" style="font-size:12px">
                            SKU: <span id="foundItemSku" class="font-weight-bold text-dark"></span> &nbsp;|&nbsp;
                            Kategori: <span id="foundItemCat"></span>
                        </div>
                    </div>
                    <button type="button" id="btnClearItem" class="btn btn-light btn-xs ml-2">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div style="background:#fff;border-radius:8px;padding:10px;border:1px solid #c3e6cb">
                            <div style="font-size:11px;color:#6c757d;font-weight:600">QTY SISTEM</div>
                            <div style="font-size:28px;font-weight:800;color:#1a2332" id="foundSystemQty">0</div>
                            <div style="font-size:11px;color:#6c757d" id="foundUnit">PCS</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div style="background:#fff;border-radius:8px;padding:10px;border:1px solid #bee5eb">
                            <div style="font-size:11px;color:#6c757d;font-weight:600">LOKASI TERATAS</div>
                            <div style="font-size:13px;font-weight:700;color:#1a2332" id="foundTopCell">—</div>
                            <div style="font-size:11px;color:#6c757d" id="foundTopCellZone">—</div>
                        </div>
                    </div>
                </div>

                {{-- Input qty fisik --}}
                <div id="qty-input-section">
                    <hr class="my-2">
                    <div class="form-group mb-2">
                        <label class="font-weight-bold" style="font-size:13px">
                            Qty Fisik Hasil Hitung <span class="text-danger">*</span>
                        </label>
                        <input type="number" id="physicalQtyInput" class="form-control form-control-lg text-center"
                               min="0" value="0" style="font-size:24px;font-weight:800;border:2px solid #17a2b8">
                    </div>

                    <div class="form-group mb-2">
                        <label class="font-weight-bold" style="font-size:12px">Lokasi Cell (opsional)</label>
                        <select id="cellSelect" class="form-control form-control-sm">
                            <option value="">— Tidak spesifik lokasi —</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold" style="font-size:12px">Catatan</label>
                        <input type="text" id="opnameNotes" class="form-control form-control-sm"
                               placeholder="Opsional...">
                    </div>

                    <button type="button" id="btnSaveItem" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-save mr-2"></i> Simpan Hasil Hitung
                    </button>
                </div>
            </div>

            {{-- Error card --}}
            <div id="itemErrorCard" class="item-error-card p-3 mb-3" style="display:none">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle text-danger mr-2 fa-lg"></i>
                    <div>
                        <div class="font-weight-bold text-danger" id="errorTitle">Item Tidak Ditemukan</div>
                        <div class="text-muted" id="errorMessage" style="font-size:12px"></div>
                    </div>
                </div>
            </div>

        </div>

        {{-- ── KOLOM KANAN: Daftar Hasil Scan ── --}}
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center"
                     style="background:#1a2332;color:#fff;font-size:13px;font-weight:600">
                    <span><i class="fas fa-list mr-2" style="color:#0d8564"></i>Hasil Scan Sesi Ini</span>
                    <span class="badge badge-light text-dark" id="totalBadge">{{ $totalCounted }} item</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:600px;overflow-y:auto">
                        <table class="table table-sm mb-0" style="font-size:12px" id="resultTable">
                            <thead style="background:#f8f9fa;position:sticky;top:0;z-index:1">
                                <tr>
                                    <th class="pl-2">Item</th>
                                    <th class="text-center">Sistem</th>
                                    <th class="text-center">Fisik</th>
                                    <th class="text-center">Selisih</th>
                                    <th class="text-center">Lokasi</th>
                                    <th class="text-center">Waktu</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="resultBody">
                            @forelse($scannedItems as $si)
                                <tr id="row-{{ $si->id }}" class="result-row">
                                    <td class="pl-2">
                                        <div class="font-weight-bold">{{ $si->item?->name }}</div>
                                        <small class="text-muted">{{ $si->item?->sku }}</small>
                                    </td>
                                    <td class="text-center">{{ $si->system_qty }}</td>
                                    <td class="text-center font-weight-bold">{{ $si->physical_qty }}</td>
                                    <td class="text-center">
                                        @php $diff = $si->difference; @endphp
                                        <span class="{{ $diff > 0 ? 'diff-surplus' : ($diff < 0 ? 'diff-shortage' : 'diff-match') }}">
                                            {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-secondary">{{ $si->cell?->code ?? '—' }}</span>
                                    </td>
                                    <td class="text-center text-muted">{{ $si->scanned_at?->format('H:i') }}</td>
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-outline-danger btn-delete-item"
                                                data-id="{{ $si->id }}" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr id="emptyRow">
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-barcode fa-2x mb-2 d-block"></i>
                                        Belum ada item. Scan barcode untuk memulai.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
const opnameId     = {{ $opname->id }};
const warehouseId  = {{ $opname->warehouse_id }};
const lookupUrl    = "{{ route('opname.lookup-item') }}";
const saveUrl      = "{{ route('opname.save-item', $opname->id) }}";
const deleteUrlBase = "{{ url('opname/' . $opname->id . '/items') }}";
const csrfToken    = $('meta[name="csrf-token"]').attr('content');

let currentItem = null;

// ── Lookup item dari barcode/SKU ──────────────────────────────────────────
function doLookup(code) {
    if (!code) return;
    hideCards();

    $.ajax({
        url: lookupUrl,
        method: 'GET',
        data: { sku: code, warehouse_id: warehouseId },
        success: function(res) {
            currentItem = res.item;
            showItemCard(res.item);
        },
        error: function(xhr) {
            showErrorCard(xhr.responseJSON?.message || 'Item tidak ditemukan.');
        }
    });
}

function showItemCard(item) {
    $('#foundItemName').text(item.name);
    $('#foundItemSku').text(item.sku);
    $('#foundItemCat').text(item.category);
    $('#foundSystemQty').text(item.system_qty);
    $('#foundUnit').text(item.unit);

    // Isi dropdown lokasi
    let cellOptions = '<option value="">— Tidak spesifik lokasi —</option>';
    if (item.top_cells && item.top_cells.length > 0) {
        item.top_cells.forEach(function(c) {
            cellOptions += `<option value="${c.id}">${c.code} (${c.zone}) — ${c.qty} ${item.unit}</option>`;
        });
        // Tampilkan lokasi teratas
        $('#foundTopCell').text(item.top_cells[0].code);
        $('#foundTopCellZone').text(item.top_cells[0].zone);
    } else {
        $('#foundTopCell').text('—');
        $('#foundTopCellZone').text('Tidak ada stok');
    }
    $('#cellSelect').html(cellOptions);

    // Set default qty fisik = qty sistem
    $('#physicalQtyInput').val(item.system_qty).focus().select();
    $('#qty-input-section').show();
    $('#itemFoundCard').show();
    $('#itemErrorCard').hide();
}

function showErrorCard(message) {
    $('#errorMessage').text(message);
    $('#itemErrorCard').show();
    $('#itemFoundCard').hide();
    setTimeout(() => $('#scanInput').focus(), 100);
}

function hideCards() {
    $('#itemFoundCard').hide();
    $('#itemErrorCard').hide();
    $('#qty-input-section').hide();
    currentItem = null;
}

// ── Simpan hasil hitung fisik ─────────────────────────────────────────────
function saveItem() {
    if (!currentItem) return;

    const physicalQty = parseInt($('#physicalQtyInput').val()) || 0;
    const cellId      = $('#cellSelect').val() || null;
    const notes       = $('#opnameNotes').val();

    $('#btnSaveItem').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

    $.ajax({
        url: saveUrl,
        method: 'POST',
        data: {
            _token:       csrfToken,
            item_id:      currentItem.id,
            cell_id:      cellId,
            system_qty:   currentItem.system_qty,
            physical_qty: physicalQty,
            notes:        notes,
        },
        success: function(res) {
            prependResultRow(res.item);
            updateSummary(res.summary);
            hideCards();
            $('#scanInput').val('').focus();
            $('#opnameNotes').val('');

            Swal.fire({
                toast: true, position: 'top-end',
                icon: 'success', title: res.message,
                showConfirmButton: false, timer: 2500,
            });
        },
        error: function(xhr) {
            Swal.fire('Gagal', xhr.responseJSON?.message || 'Terjadi kesalahan.', 'error');
        },
        complete: function() {
            $('#btnSaveItem').prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Simpan Hasil Hitung');
        }
    });
}

// ── Tambah baris ke tabel hasil ───────────────────────────────────────────
function prependResultRow(item) {
    $('#emptyRow').remove();

    const diffClass = item.diff_status === 'surplus'  ? 'diff-surplus' :
                      item.diff_status === 'shortage' ? 'diff-shortage' : 'diff-match';
    const diffText  = item.difference > 0 ? '+' + item.difference : item.difference;

    const row = `
        <tr id="row-${item.id}" class="result-row" style="background:#f0fff8">
            <td class="pl-2">
                <div class="font-weight-bold">${item.item_name}</div>
                <small class="text-muted">${item.sku}</small>
            </td>
            <td class="text-center">${item.system_qty}</td>
            <td class="text-center font-weight-bold">${item.physical_qty}</td>
            <td class="text-center"><span class="${diffClass}">${diffText}</span></td>
            <td class="text-center"><span class="badge badge-secondary">${item.cell_code || '—'}</span></td>
            <td class="text-center text-muted">${item.scanned_at}</td>
            <td class="text-center">
                <button class="btn btn-xs btn-outline-danger btn-delete-item" data-id="${item.id}" title="Hapus">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;

    $('#resultBody').prepend(row);

    // Hilangkan highlight setelah 2 detik
    setTimeout(() => $(`#row-${item.id}`).css('background', ''), 2000);
}

// ── Update summary badge ──────────────────────────────────────────────────
function updateSummary(s) {
    $('#cntTotal').text(s.total_counted);
    $('#cntMatch').text(s.total_match);
    $('#cntShortage').text(s.total_shortage);
    $('#cntSurplus').text(s.total_surplus);
    $('#totalBadge').text(s.total_counted + ' item');
}

// ── Event Bindings ────────────────────────────────────────────────────────
$('#scanInput').on('keydown', function(e) {
    if (e.key === 'Enter') {
        doLookup($(this).val().trim());
        $(this).val('');
        e.preventDefault();
    }
});

$('#btnLookup').on('click', function() {
    doLookup($('#scanInput').val().trim());
    $('#scanInput').val('');
});

$('#btnSaveItem').on('click', saveItem);

$('#physicalQtyInput').on('keydown', function(e) {
    if (e.key === 'Enter') { saveItem(); e.preventDefault(); }
});

$('#btnClearItem').on('click', function() {
    hideCards();
    $('#scanInput').val('').focus();
});

// Hapus baris item
$(document).on('click', '.btn-delete-item', function() {
    const itemId = $(this).data('id');
    if (!confirm('Hapus item ini dari daftar opname?')) return;

    $.ajax({
        url: deleteUrlBase + '/' + itemId,
        method: 'DELETE',
        data: { _token: csrfToken },
        success: function() {
            $(`#row-${itemId}`).fadeOut(300, function() { $(this).remove(); });
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Item dihapus.', showConfirmButton: false, timer: 2000 });
        }
    });
});

// Auto-fokus ke input saat halaman dimuat
$(document).ready(function() {
    $('#scanInput').focus();
});
</script>
@endpush
