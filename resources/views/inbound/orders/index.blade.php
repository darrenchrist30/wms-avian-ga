@extends('layouts.adminlte')
@section('title', 'Penerimaan Barang')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h3 class="mt-2">Penerimaan Barang (Inbound)</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:6px;">
                            <div class="font-weight-bold">
                                <i class="fas fa-truck-loading mr-1"></i> Daftar Inbound Order
                            </div>
                            <div class="d-flex align-items-center flex-wrap" style="gap:6px;">
                                {{-- Batch GA button --}}
                                <button id="btnBatchGa" class="btn btn-primary btn-sm" disabled
                                    title="Pilih order terlebih dahulu">
                                    <i class="fas fa-dna mr-1"></i>
                                    Run GA Batch
                                    <span class="badge badge-light ml-1" id="selectedCount">0</span>
                                </button>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-dark btnFilter" data-toggle="collapse"
                                        data-target=".filter">
                                        <i class="fas fa-filter mr-2"></i>Filter
                                    </button>
                                    <button class="btn btn-sm btn-outline-dark btnRefresh">
                                        <i class="fas fa-redo mr-2"></i>Refresh
                                    </button>
                                    <a href="{{ route('inbound.orders.create') }}" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus mr-2"></i>Add
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="filter-container">
                            <div class="row m-2 filter collapse mb-3">
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Status</label>
                                        <div class="col-sm-8">
                                            <select class="form-control form-control-sm" id="filter-status">
                                                <option value="">Semua Status</option>
                                                <option value="inbound" selected>Inbound</option>
                                                <option value="put_away">Put Away</option>
                                                <option value="completed">Completed</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group row">
                                        <label class="col-sm-4 col-form-label small font-weight-bold">Warehouse</label>
                                        <div class="col-sm-8">
                                            <select class="form-control form-control-sm" id="filter-warehouse">
                                                <option value="">Semua Warehouse</option>
                                                @foreach ($warehouses as $wh)
                                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <table id="datatable"
                            class="table table-bordered table-sm table-striped table-hover w-100">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center">
                                        <input type="checkbox" id="checkAll" title="Pilih semua">
                                    </th>
                                    <th width="90" class="text-center">Actions</th>
                                    <th width="50" class="text-center">#</th>
                                    <th width="150">No. Surat Jalan</th>
                                    <th width="100" class="text-center">Tgl SJ</th>
                                    <th width="160">Warehouse</th>
                                    <th width="60" class="text-center">Items</th>
                                    <th width="110" class="text-center">Status</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Batch GA Progress & Results --}}
    <div class="modal fade" id="modalBatchGa" tabindex="-1" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-dna mr-2"></i>Batch Run GA
                    </h5>
                </div>
                <div class="modal-body">

                    {{-- Loading state --}}
                    <div id="batchLoading" class="text-center py-4">
                        <div class="spinner-border text-success mb-3" style="width:3rem;height:3rem;"></div>
                        <div class="font-weight-bold" id="batchLoadingText">Memproses GA...</div>
                        <small class="text-muted">Harap tunggu, GA sedang berjalan untuk semua order yang dipilih</small>
                    </div>

                    {{-- Results --}}
                    <div id="batchResults" style="display:none;">
                        {{-- Summary cards --}}
                        <div class="row text-center mb-3" id="batchSummary"></div>

                        {{-- Per-order results table --}}
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>No. Surat Jalan</th>
                                        @if(!auth()->user()->hasRole('operator'))<th class="text-center">Fitness</th>@endif
                                        <th class="text-center">Hasil</th>
                                    </tr>
                                </thead>
                                <tbody id="batchResultBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="batchFooter" style="display:none;"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Pre-fill filter dari URL param ?status=
            var urlStatus = new URLSearchParams(window.location.search).get('status');
            if (urlStatus !== null) {
                $('#filter-status').val(urlStatus);
            }

            var baseURL      = "{{ route('inbound.orders.datatable') }}";
            var routeDestroy    = "{{ route('inbound.orders.destroy', ':id') }}";
            var routeBatchGa    = "{{ route('inbound.orders.batch-ga') }}";
            var routePutawayQ   = "{{ route('putaway.queue') }}";
            var csrfToken    = $('meta[name="csrf-token"]').attr('content');
            var isOperator   = {{ auth()->user()->hasRole('operator') ? 'true' : 'false' }};

            var selectedIds  = {};  // { id: do_number }

            // ── DataTable ─────────────────────────────────────────────────
            var table = $('#datatable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                order: [[3, 'desc']],
                ajax: {
                    url: baseURL,
                    data: function(d) {
                        d.status       = $('#filter-status').val();
                        d.warehouse_id = $('#filter-warehouse').val();
                    },
                    type: 'GET'
                },
                columns: [
                    { data: 'checkbox',      name: 'checkbox',      orderable: false, searchable: false, className: 'text-center' },
                    { data: 'action',        name: 'action',        orderable: false, searchable: false, className: 'text-center' },
                    { data: 'DT_RowIndex',   name: 'DT_RowIndex',   orderable: false, searchable: false, className: 'text-center' },
                    { data: 'do_number',     name: 'do_number' },
                    { data: 'do_date',       name: 'do_date',       className: 'text-center' },
                    { data: 'warehouse.name',name: 'warehouse.name',defaultContent: '-' },
                    { data: 'items_count',   name: 'items_count',   orderable: false, className: 'text-center' },
                    { data: 'status_badge',  name: 'status',        orderable: false, searchable: false, className: 'text-center' },
                ]
            });

            // Re-bind checkboxes after each DataTable draw (server-side redraws DOM)
            table.on('draw', function() {
                // Restore checked state for IDs already in selectedIds
                $('#datatable .order-check').each(function() {
                    var id = $(this).val();
                    if (selectedIds[id]) $(this).prop('checked', true);
                });
                updateBatchButton();
            });

            // ── Select All (current page) ────────────────────────────────
            $('#checkAll').on('change', function() {
                var checked = $(this).is(':checked');
                $('#datatable .order-check').each(function() {
                    $(this).prop('checked', checked);
                    var id  = $(this).val();
                    var doN = $(this).data('do');
                    if (checked) {
                        selectedIds[id] = doN;
                    } else {
                        delete selectedIds[id];
                    }
                });
                updateBatchButton();
            });

            // ── Individual checkbox ──────────────────────────────────────
            $(document).on('change', '.order-check', function() {
                var id  = $(this).val();
                var doN = $(this).data('do');
                if ($(this).is(':checked')) {
                    selectedIds[id] = doN;
                } else {
                    delete selectedIds[id];
                    $('#checkAll').prop('checked', false);
                }
                updateBatchButton();
            });

            function updateBatchButton() {
                var count = Object.keys(selectedIds).length;
                $('#selectedCount').text(count);
                $('#btnBatchGa').prop('disabled', count === 0)
                    .attr('title', count === 0 ? 'Pilih order terlebih dahulu' : count + ' order dipilih');
            }

            // ── Batch GA ─────────────────────────────────────────────────
            $('#btnBatchGa').on('click', function() {
                var ids = Object.keys(selectedIds);
                if (ids.length === 0) return;

                var doNumbers = Object.values(selectedIds).join(', ');
                Swal.fire({
                    title: 'Run GA Batch?',
                    html: 'GA akan dijalankan untuk <strong>' + ids.length + ' order</strong>:<br>' +
                          '<small class="text-muted">' + doNumbers + '</small><br><br>' +
                          '<span class="text-warning"><i class="fas fa-clock mr-1"></i>Proses mungkin memerlukan beberapa menit.</span>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-dna mr-1"></i>Ya, Jalankan!',
                    cancelButtonText: 'Batal'
                }).then(function(result) {
                    if (!result.isConfirmed) return;

                    // Show loading modal
                    $('#batchLoading').show();
                    $('#batchResults').hide();
                    $('#batchFooter').hide();
                    $('#batchLoadingText').text('Memproses ' + ids.length + ' order...');
                    $('#modalBatchGa').modal('show');

                    $.ajax({
                        url: routeBatchGa,
                        type: 'POST',
                        data: {
                            _token: csrfToken,
                            order_ids: ids
                        },
                        timeout: 600000,  // 10 menit
                        success: function(response) {
                            renderBatchResults(response);
                        },
                        error: function(xhr) {
                            var msg = xhr?.responseJSON?.message || 'Terjadi kesalahan pada server.';
                            $('#batchLoading').hide();
                            $('#batchResults').show();
                            $('#batchResultBody').html(
                                '<tr><td colspan="4" class="text-center text-danger"><i class="fas fa-exclamation-circle mr-1"></i>' + msg + '</td></tr>'
                            );
                            $('#batchFooter').show();
                        }
                    });
                });
            });

            function renderBatchResults(response) {
                $('#batchLoading').hide();

                var s = response.summary;
                $('#batchSummary').html(
                    summaryCard('Total', s.total, 'secondary', 'fas fa-list') +
                    summaryCard('Diterima', s.accepted, 'success', 'fas fa-check-circle') +
                    summaryCard('Perlu Review', s.review, 'warning', 'fas fa-exclamation-triangle') +
                    summaryCard('Gagal/Skip', s.errors, 'danger', 'fas fa-times-circle')
                );

                var rows = '';
                $.each(response.results, function(i, r) {
                    var statusBadge;

                    if (r.status === 'accepted') {
                        statusBadge = '<span class="badge badge-success">Diterima (Auto)</span>';
                    } else if (r.status === 'pending_review') {
                        statusBadge = '<span class="badge badge-warning">Perlu Review</span>';
                    } else if (r.status === 'skip') {
                        statusBadge = '<span class="badge badge-secondary">Dilewati</span>';
                    } else {
                        statusBadge = '<span class="badge badge-danger">Gagal</span>';
                    }

                    var fitnessCell = isOperator ? '' :
                        '<td class="text-center">' + (r.fitness_score ? '<strong>' + r.fitness_score + '</strong>/100' : '-') + '</td>';

                    rows += '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td><code>' + r.do_number + '</code><br><small class="text-muted">' + r.message + '</small></td>' +
                        fitnessCell +
                        '<td class="text-center">' + statusBadge + '</td>' +
                        '</tr>';
                });

                $('#batchResultBody').html(rows);
                $('#batchResults').show();

                // Bangun footer: tombol Put-Away (jika ada order diterima) + Tutup & Refresh
                var footerHtml = '<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" onclick="location.reload()">'
                    + '<i class="fas fa-times mr-1"></i>Tutup &amp; Refresh</button>';

                if (s.accepted > 0) {
                    footerHtml = '<a href="' + routePutawayQ + '" class="btn btn-success btn-sm mr-2">'
                        + '<i class="fas fa-dolly-flatbed mr-1"></i>Mulai Put-Away (' + s.accepted + ' DO)</a>'
                        + footerHtml;
                }

                $('#batchFooter').html(footerHtml).show();

                // Reset selection
                selectedIds = {};
                updateBatchButton();
            }

            function summaryCard(label, value, color, icon) {
                return '<div class="col-3">' +
                    '<div class="card border-' + color + '">' +
                    '<div class="card-body py-2 px-2 text-center">' +
                    '<i class="' + icon + ' text-' + color + ' fa-lg mb-1 d-block"></i>' +
                    '<div class="font-weight-bold" style="font-size:22px;">' + value + '</div>' +
                    '<small class="text-muted">' + label + '</small>' +
                    '</div></div></div>';
            }

            // ── Refresh & Filter ─────────────────────────────────────────
            $('.btnRefresh').on('click', function() {
                $('#filter-status').val('inbound');
                $('#filter-warehouse').val('');
                selectedIds = {};
                updateBatchButton();
                table.ajax.reload();
            });

            $('#filter-status, #filter-warehouse').on('change', function() {
                table.ajax.reload();
            });

            // ── Delete ───────────────────────────────────────────────────
            $(document).on('click', '.btnDel', function(e) {
                e.preventDefault();
                let id      = $(this).data('id');
                let name    = $(this).data('name');
                let ajaxurl = routeDestroy.replace(':id', id);
                Swal.fire({
                    title: 'Hapus Order?',
                    html: 'Order <strong>' + name + '</strong> akan dihapus permanen.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'DELETE',
                            data: { _token: csrfToken },
                            success: function(response) {
                                Swal.fire(
                                    response.status === 'success' ? 'Berhasil!' : 'Gagal!',
                                    response.message,
                                    response.status === 'success' ? 'success' : 'error'
                                );
                                table.ajax.reload(null, false);
                            },
                            error: function(xhr) {
                                Swal.fire('Gagal!', xhr?.responseJSON?.message || 'Terjadi kesalahan.', 'error');
                                table.ajax.reload(null, false);
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
