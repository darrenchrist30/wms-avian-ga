@extends('layouts.adminlte')

@section('title', 'API Token — Integrasi ERP')

@section('content')
<div class="container-fluid">

    <div class="row mb-3">
        <div class="col-md-8">
            <h4 class="mt-2 mb-0">
                <i class="fas fa-key mr-2 text-warning"></i>
                API Token — Integrasi ERP
            </h4>
            <p class="text-muted mb-0" style="font-size:13px">
                Token digunakan sistem ERP untuk terhubung ke WMS via API.
                Token hanya ditampilkan <strong>sekali</strong> saat dibuat — simpan di tempat aman.
            </p>
        </div>
        <div class="col-md-4 text-right pt-2">
            <button class="btn btn-warning" data-toggle="modal" data-target="#modalCreate">
                <i class="fas fa-plus mr-1"></i> Buat Token Baru
            </button>
        </div>
    </div>

    {{-- ── Alert sukses/error ── --}}
    @if (session('success') && !session('new_token'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         MODAL: Token Baru Berhasil Dibuat
    ══════════════════════════════════════════════════════ --}}
    @if (session('new_token'))
    <div class="modal fade show d-block" id="modalNewToken" tabindex="-1"
         style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog">
            <div class="modal-content border-warning">
                <div class="modal-header" style="background:#ffc107">
                    <h6 class="modal-title text-dark font-weight-bold">
                        <i class="fas fa-key mr-1"></i>
                        Token Baru Berhasil Dibuat — "{{ session('new_token_name') }}"
                    </h6>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 mb-3" style="font-size:12px">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Salin token ini sekarang.</strong>
                        Token tidak akan ditampilkan lagi setelah jendela ini ditutup.
                    </div>
                    <label class="text-muted small">Bearer Token</label>
                    <div class="input-group mb-3">
                        <input type="text" id="newTokenValue"
                               class="form-control font-weight-bold"
                               value="{{ session('new_token') }}"
                               readonly
                               style="font-family:monospace;font-size:13px;background:#f8f9fa">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="copyToken()" id="btnCopy">
                                <i class="fas fa-copy mr-1"></i> Salin
                            </button>
                        </div>
                    </div>
                    <div class="card bg-light border mb-0">
                        <div class="card-body py-2 px-3" style="font-size:12px">
                            <strong>Cara pakai di ERP / Postman:</strong>
                            <pre class="mb-0 mt-1" style="font-size:11px;background:transparent">Authorization: Bearer {{ session('new_token') }}</pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <small class="text-muted mr-auto">
                        <i class="fas fa-info-circle mr-1"></i>
                        Setelah ditutup, token tidak bisa dilihat lagi.
                    </small>
                    <button type="button" class="btn btn-warning" onclick="closeNewToken()">
                        <i class="fas fa-check mr-1"></i> Sudah Disalin, Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         INFO ENDPOINT
    ══════════════════════════════════════════════════════ --}}
    <div class="card mb-3">
        <div class="card-header py-2">
            <h6 class="mb-0" style="font-size:13px">
                <i class="fas fa-plug mr-1 text-primary"></i>
                Endpoint API yang Tersedia
            </h6>
        </div>
        <div class="card-body py-2" style="font-size:12px">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <thead><tr class="text-muted"><th>Method</th><th>Endpoint</th><th>Fungsi</th></tr></thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-success">GET</span></td>
                                <td><code>/api/v1/ping</code></td>
                                <td>Health check (tanpa auth)</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-warning text-dark">POST</span></td>
                                <td><code>/api/v1/inbound/receive</code></td>
                                <td>Kirim Delivery Order baru dari ERP</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-success">GET</span></td>
                                <td><code>/api/v1/inbound/{doNumber}</code></td>
                                <td>Cek status satu DO</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-success">GET</span></td>
                                <td><code>/api/v1/inbound?status=draft</code></td>
                                <td>List semua DO (bisa filter)</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-warning text-dark">POST</span></td>
                                <td><code>/api/v1/master/items/sync</code></td>
                                <td>Sinkronisasi item dari ERP</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <div class="bg-dark text-light p-2 rounded" style="font-size:11px;font-family:monospace">
                        <div class="text-warning mb-1">// Header yang wajib dikirim ERP:</div>
                        Authorization: Bearer &lt;token&gt;<br>
                        Content-Type: application/json<br>
                        Accept: application/json<br>
                        <br>
                        <div class="text-warning mb-1">// Contoh status DO:</div>
                        "draft"       → Diterima, menunggu GA<br>
                        "recommended" → GA selesai, siap put-away<br>
                        "put_away"    → Sedang diletakkan<br>
                        "completed"   → Selesai masuk rak
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         DAFTAR TOKEN
    ══════════════════════════════════════════════════════ --}}
    <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="fas fa-list mr-1"></i>
                Token Aktif
                <span class="badge badge-secondary ml-1">{{ $tokens->count() }}</span>
            </h6>
            <small class="text-muted">Token yang sudah dibuat dan aktif</small>
        </div>
        <div class="card-body p-0">
            @if ($tokens->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-key fa-3x mb-3"></i>
                    <div>Belum ada token. Buat token pertama untuk menghubungkan ERP.</div>
                </div>
            @else
            <table class="table table-hover mb-0" style="font-size:13px">
                <thead class="thead-light">
                    <tr>
                        <th width="30" class="text-center">#</th>
                        <th>Nama Token</th>
                        <th>Pemilik</th>
                        <th>Dibuat</th>
                        <th>Terakhir Digunakan</th>
                        <th width="80" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tokens as $i => $token)
                    <tr>
                        <td class="text-center text-muted">{{ $i + 1 }}</td>
                        <td>
                            <i class="fas fa-key text-warning mr-1"></i>
                            <strong>{{ $token->name }}</strong>
                            <br>
                            <small class="text-muted" style="font-family:monospace">
                                {{ substr($token->token, 0, 8) }}••••••••••••••••••••••••
                            </small>
                        </td>
                        <td>
                            <i class="fas fa-user mr-1 text-muted"></i>
                            {{ $token->tokenable?->name ?? '-' }}
                        </td>
                        <td>
                            {{ $token->created_at->format('d M Y H:i') }}
                        </td>
                        <td>
                            @if ($token->last_used_at)
                                <span class="text-success">
                                    <i class="fas fa-circle mr-1" style="font-size:8px"></i>
                                    {{ $token->last_used_at->diffForHumans() }}
                                </span>
                            @else
                                <span class="text-muted">Belum pernah digunakan</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <form action="{{ route('api-tokens.destroy', $token->id) }}"
                                  method="POST"
                                  onsubmit="return confirm('Revoke token \"{{ $token->name }}\"?\nToken ini tidak akan bisa digunakan lagi.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger"
                                        title="Revoke token ini">
                                    <i class="fas fa-trash mr-1"></i> Revoke
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
        @if ($tokens->isNotEmpty())
        <div class="card-footer text-muted" style="font-size:11px">
            <i class="fas fa-info-circle mr-1"></i>
            Revoke token jika sudah tidak digunakan atau diduga bocor.
            Setelah direvoke, ERP harus menggunakan token baru.
        </div>
        @endif
    </div>

</div>

{{-- ══════════════════════════════════════════════════════
     MODAL: Buat Token Baru
══════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#ffc107">
                <h6 class="modal-title text-dark font-weight-bold">
                    <i class="fas fa-plus mr-1"></i> Buat Token Baru
                </h6>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('api-tokens.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Nama Token <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               placeholder="Contoh: ERP-Integration, Postman-Test, SAP-WH001"
                               required maxlength="100" autofocus>
                        <small class="text-muted">
                            Gunakan nama yang menggambarkan sistem yang akan menggunakan token ini.
                        </small>
                    </div>
                    <div class="alert alert-warning py-2 mb-0" style="font-size:12px">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Token hanya ditampilkan <strong>sekali</strong> setelah dibuat.
                        Pastikan langsung disalin dan disimpan di tempat aman.
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key mr-1"></i> Generate Token
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function copyToken() {
    const input = document.getElementById('newTokenValue');
    input.select();
    document.execCommand('copy');
    const btn = document.getElementById('btnCopy');
    btn.innerHTML = '<i class="fas fa-check mr-1"></i> Tersalin!';
    btn.classList.replace('btn-outline-secondary', 'btn-success');
    setTimeout(() => {
        btn.innerHTML = '<i class="fas fa-copy mr-1"></i> Salin';
        btn.classList.replace('btn-success', 'btn-outline-secondary');
    }, 2500);
}

function closeNewToken() {
    document.getElementById('modalNewToken').remove();
}
</script>
@endpush
