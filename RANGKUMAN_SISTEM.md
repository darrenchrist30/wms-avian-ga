# Rangkuman Sistem WMS Avian – Bab 4 (Implementasi)

> **Judul Skripsi:** Rancang Bangun Sistem Manajemen Gudang (WMS) dengan Optimasi Penempatan Barang Menggunakan Algoritma Genetika  
> **Studi Kasus:** PT Avian Brands (Spare Part Divisi)  
> **Mahasiswa:** Darren Christopher  
> **Tujuan dokumen ini:** Panduan lengkap bagi Claude untuk menulis Bab 4 Implementasi, diselaraskan dengan desain Bab 3.

---

## 1. Teknologi & Lingkungan Pengembangan

| Komponen | Teknologi |
|---|---|
| Backend Framework | Laravel 11 (PHP 8.2) |
| Frontend Template | AdminLTE 3 (Bootstrap 4) |
| Database | MySQL 8 |
| ORM | Eloquent ORM (Laravel) |
| DataTable Server-Side | Yajra DataTables |
| Dropdown AJAX | Select2 v4 (Bootstrap 4 theme) |
| Charting | Highcharts |
| 3D Visualization | Three.js |
| GA Engine | Python 3.12 + FastAPI (uvicorn port 8001) |
| GA Library | Custom pure-Python (bukan pygad — file pygad_engine.py ada tapi tidak dipakai) |
| Queue/Job | Laravel Queue (database driver) |
| Auth | Laravel Sanctum + custom Role-Permission |
| Web Server (dev) | Laragon (Apache/Nginx) |

---

## 2. Arsitektur Sistem

```
Browser (AdminLTE/Bootstrap)
        │  HTTP
        ▼
Laravel App (port 80)
   ├── Controllers (HTTP layer)
   ├── Services (GaService, PutAwayService, CellCapacityService)
   ├── Models (Eloquent)
   ├── Jobs (RecalculateAffinityJob)
   └── Middleware (auth, active.user, role, permission)
        │  HTTP POST /api/optimize
        ▼
Python FastAPI GA Engine (port 8001)
   ├── main.py (endpoint /optimize)
   ├── ga/engine.py (GeneticAlgorithmEngine)
   ├── ga/fitness.py (evaluate_chromosome)
   └── ga/operators.py (selection, crossover, mutation, elitism)
        │
        ▼
MySQL Database (tabel: warehouses, racks, cells, items, inbound_orders, ...)
```

Laravel memanggil GA Engine via HTTP POST ke `http://localhost:8001/optimize` dengan payload JSON (items, cells, affinities, parameters). GA Engine mengembalikan kromosom terbaik + breakdown fitness.

---

## 3. Struktur Database (Tabel Utama)

### 3.1 Hierarki Lokasi
```
warehouses (id, code, name, address, pic, phone, is_active)
    └── racks (id, warehouse_id, code, name, total_levels, total_columns,
               pos_x, pos_z, rotation_y, is_active, dominant_category_id)
            └── cells (id, rack_id, code, label, level, column,
                       blok, grup, kolom, baris,          ← koordinat MSpart
                       capacity_max, capacity_used, status,
                       dominant_category_id, zone_category,
                       qr_code, is_active)
```

**Status cell:** `available` | `partial` | `full` | `blocked` | `reserved`

Cell memiliki dua sistem koordinat:
- **Standard cell:** `code = RACK_CODE-LEVEL_LETTER` (mis. `R-01-A`), multi-kolom: `R-01-A1`
- **MSpart cell:** koordinat fisik `blok-grup-kolom-baris` (mis. `2-A-3-1`), digunakan untuk data riil client Avian

### 3.2 Master Data
```
item_categories (id, code, name, color_code, is_active)
units (id, code, name, is_active)
items (id, sku, name, merk, category_id, unit_id,
       min_stock, max_stock, deadstock_threshold_days,
       home_cell_id, is_active)
item_affinities (id, item_id, related_item_id,
                 co_occurrence_count, affinity_score)
```

### 3.3 Proses Inbound
```
inbound_orders (id, do_number, do_date, warehouse_id,
                status, ga_run_at, created_at)
    └── inbound_order_details (id, inbound_order_id, item_id,
                               qty_ordered, qty_received,
                               lpn, put_away_status)
```
**Status inbound_order:** `inbound` | `ga_processed` | `putaway` | `completed`  
**Status detail (put_away_status):** `pending` | `partial` | `completed`

### 3.4 GA & Rekomendasi
```
ga_recommendations (id, inbound_order_id, fitness_score,
                    generations_run, execution_time_ms,
                    status, generated_by, generated_at)
    └── ga_recommendation_details (id, ga_recommendation_id,
                                    inbound_detail_id, cell_id, quantity,
                                    gene_fitness, fc_cap, fc_cat, fc_aff, fc_split)
```
**Status ga_recommendation:** `pending` | `accepted` | `rejected`

### 3.5 Put-Away & Stok
```
put_away_confirmations (id, inbound_order_id, inbound_detail_id,
                        cell_id, quantity, is_override,
                        confirmed_by, confirmed_at)

stocks (id, item_id, cell_id, warehouse_id,
        quantity, batch_number, received_at, consumed_at)

stock_movements (id, item_id, from_cell_id, to_cell_id,
                 quantity, movement_type, reference_id, created_by)
```

### 3.6 Lain-lain
```
users (id, name, email, role_id, warehouse_id, is_active)
roles (id, name)
permissions (id, name)
role_permission (role_id, permission_id)
audit_logs (id, user_id, action, model_type, model_id, changes, ip_address)
notifications (id, user_id, type, data, read_at)
deadstock_notifications (id, item_id, cell_id, triggered_at, acknowledged_at)
```

---

## 4. Modul & Fitur Implementasi

### 4.1 Autentikasi & Manajemen Pengguna
- Login/logout dengan throttle (5 percobaan/menit)
- Tiga role: **admin**, **supervisor**, **operator**
- Middleware `role:` dan `permission:` per route
- CRUD user & role (admin only)
- Audit log otomatis untuk semua perubahan data penting

### 4.2 Master Data

**4.2.1 Sparepart (Items)**
- CRUD dengan validasi SKU unik
- Import massal dari Excel/CSV (format kolom: SKU, Nama, Kategori, Satuan, ...)
- Dropdown Kategori & Satuan menggunakan **Select2 AJAX** (theme Bootstrap 4)
- Setelah create, redirect otomatis ke halaman barcode label
- Scan barcode untuk lookup item

**4.2.2 Kategori Item**
- CRUD dengan color picker (kode warna hex untuk badge di UI)
- Digunakan GA sebagai input FC_CAT

**4.2.3 Satuan (Unit)**
- CRUD (kode + nama)

**4.2.4 Co-Occurrence / Afinitas**
- Halaman read-only (tidak ada CRUD — data dihitung otomatis)
- **Summary cards:** total pasangan, rata-rata skor afinitas, co-occurrence tertinggi
- **Bar chart (Highcharts):** top 10 pasangan sparepart — batang biru (co-occurrence count) + garis oranye (affinity score 0–1) dengan dual Y-axis
- **DataTable server-side:** Item A | Item B | Co-Occurrence | Progress bar skor afinitas
- Filter dropdown per kategori
- Panel metodologi (collapsible): menjelaskan formula normalisasi dan kaitan ke FC_AFF
- Data diperbarui otomatis oleh `RecalculateAffinityJob` setiap order selesai

**Formula normalisasi:**  
`affinity_score = co_occurrence_count / max(co_occurrence_count_semua_pasangan)`

### 4.3 Manajemen Lokasi

**Hierarki:** Warehouse → Rak → Sel (tanpa zona — zona dihapus pada migrasi Mei 2026)

**4.3.1 Warehouse**
- CRUD
- **Fitur Generate Layout Otomatis** (saat create): admin mengisi parameter (jumlah rak, prefix kode, jumlah level A–G, jumlah kolom per level, rak per baris untuk layout 3D, kapasitas default sel). Sistem otomatis generate semua rak dan sel sekaligus. `pos_x`/`pos_z` dihitung berdasarkan posisi baris × kolom lantai gudang (spacing 2.5 × 3.5 unit).
- Preview real-time JS: "Akan dibuat: 10 rak × 5 level × 1 kolom = 50 sel"

**4.3.2 Rak**
- CRUD dengan Select2 AJAX untuk warehouse
- Saat create rak manual, sel level A–G di-generate otomatis
- Edit rak tidak generate ulang sel (dipisah)
- Dropdown kategori dominan menggunakan **Select2 AJAX**

**4.3.3 Sel (Cell)**
- CRUD
- Dropdown rak menggunakan **Select2 AJAX**
- Status diupdate otomatis berdasarkan kapasitas (`updateStatus()` pada model)
- QR code label: halaman cetak label QR per sel atau bulk (semua sel rak/warehouse)
- Halaman publik `/c/{code}` (tanpa auth): tampilkan info sel saat scan QR
- Halaman stok per sel: list item yang tersimpan di sel tersebut

### 4.4 Inbound Order

**Alur:**
1. Order dibuat / disync dari ERP (mengisi do_number, do_date, warehouse, detail item + qty)
2. Status: `inbound`
3. Admin/supervisor klik **Process GA** → Laravel memanggil Python GA Engine
4. Status berubah ke `ga_processed`, rekomendasi penempatan tersimpan
5. Operator melakukan **Put-Away** via scan QR
6. Status berubah ke `putaway`, lalu `completed`

**Fitur:**
- CRUD inbound order + detail item
- Sync dari ERP (tombol Sync ERP per order)
- Filter DataTable: status, start_date/end_date (filter pada do_date), warehouse
- Tombol Process GA (per order) dan Batch Process GA (multi-order sekaligus)
- Tampilkan rekomendasi GA dengan breakdown fitness per item

### 4.5 Algoritma Genetika (GA Engine)

**Teknologi:** Python 3.12, FastAPI, custom pure-Python GA (tidak menggunakan library external GA)

**Endpoint:** `POST /optimize` (port 8001)

**Input (GARequest JSON):**
- `items[]`: inbound_detail_id, item_id, quantity, category_id, capacity_demand, preferred_cell_id
- `cells[]`: cell_id, rack_code, rack_index, cell_index, capacity_remaining, dominant_category_id, existing_item_ids, blok, grup, kolom, baris
- `affinities[]`: item_id, related_item_id, affinity_score
- `parameters`: population, max_generations, mutation_rate, crossover_rate, elitism, early_stopping, seed

**Output (GAResponse JSON):**
- fitness_score, generations_run, execution_time_ms
- `chromosome[]`: inbound_detail_id, cell_id, quantity, gene_fitness, fc_cap, fc_cat, fc_aff, fc_split

#### 4.5.1 Representasi Kromosom (Direct Value / Integer Encoding)
```
Panjang kromosom = n = jumlah baris inbound_order_details
Nilai gen ke-i   = cell_id (integer) — lokasi yang dipilih GA untuk item ke-i

Contoh (4 item):
chromosome = [11, 24, 11, 37]
             ↑    ↑    ↑    ↑
           item1 item2 item3 item4
```
Gen-1 dan Gen-3 (item sama / SP-001) di cell yang sama (11) → tidak kena penalti FC_SPLIT.

#### 4.5.2 Fungsi Fitness (Skor 0–100)

`F(kromosom) = rata-rata(FC_CAP_i + FC_CAT_i + FC_AFF_i + FC_SPLIT_i) untuk semua i`

| Komponen | Maks | Keterangan |
|---|---|---|
| **FC_CAP** | 35 | Kapasitas: skor 35 jika demand ≤ sisa kapasitas sel; penalti proporsional jika overflow |
| **FC_CAT** | 25 | Kesesuaian kategori: 25 jika kategori item = kategori dominan sel atau item sudah ada di sel |
| **FC_AFF** | 20 | Afinitas: mendorong item ber-afinitas tinggi di sel/rak yang sama; juga mempertimbangkan kontinuitas lokasi existing |
| **FC_SPLIT** | 20 | Anti-split + jarak: 10 poin untuk jumlah lokasi (1 lokasi = 10, 2 = 5, dst.) + 10 poin untuk jarak fisik antar lokasi split |
| **Total** | **100** | |

**FC_SPLIT** menggunakan fungsi `cell_distance()`:
- MSpart cell: `|blok_a - blok_b|×10 + |grup_a - grup_b|×3 + |kolom_a - kolom_b| + |baris_a - baris_b|×0.5`
- Standard cell: `|rack_index_a - rack_index_b|×10 + |cell_index_a - cell_index_b|`

#### 4.5.3 Parameter & Operator GA

| Parameter/Operator | Nilai/Metode | Referensi |
|---|---|---|
| Ukuran Populasi | 100 | Holland (1975) |
| Maks Generasi | 150 | |
| Early Stopping | 20 generasi tanpa perbaikan | |
| Inisialisasi | 50% Random + 50% Greedy | Whitley (1994) |
| Seleksi | Tournament Selection (size=3) | Miller & Goldberg (1995) |
| Crossover | Uniform Crossover (rate=0.80) | Syswerda (1989) |
| Mutasi | Random Reset Mutation — capacity-aware (rate=0.15) | Michalewicz (1996) |
| Elitisme | Pertahankan top-3 individu | De Jong (1975) |
| Repair | `repair_category_invalid_genes()` setelah crossover/mutasi | |

**Inisialisasi Populasi:**
- 50% **Random** (capacity-aware): setiap gen dipilih acak dari sel dengan sisa kapasitas ≥ demand item
- 50% **Greedy**: item dengan qty besar diprioritaskan ke sel berkapasitas besar (mempercepat konvergensi)

**Seleksi (Tournament):**
- Pilih 3 individu secara acak, kembalikan individu dengan fitness tertinggi
- `P(terbaik terpilih) = 1 - (1 - 1/N)^k`

**Crossover (Uniform):**
- Bangkitkan mask biner M = [m₁...mₙ], mᵢ ~ Bernoulli(0.5)
- child1[i] = parent1[i] jika mᵢ=0, parent2[i] jika mᵢ=1

**Mutasi (Random Reset):**
- Setiap gen dengan probabilitas 0.15 diganti dengan cell_id acak dari pool feasible
- Pool feasible = sel dengan capacity_remaining ≥ capacity_demand item

**Alur Lengkap GA:**
```
1. Inisialisasi populasi (50% random + 50% greedy)
2. Evaluasi fitness semua individu
3. Loop (maks 150 generasi):
   a. Tournament Selection → parent1, parent2
   b. Uniform Crossover (p=0.80) → child1, child2
   c. Random Reset Mutation (p=0.15) → child1, child2
   d. repair_category_invalid_genes() → child1, child2
   e. Evaluasi fitness child1, child2
   f. Elitisme: ganti 3 individu terburuk dengan 3 individu terbaik dari generasi lama
   g. Update solusi terbaik
   h. Jika tidak ada perbaikan selama 20 generasi → Early Stop
4. Kembalikan kromosom terbaik + breakdown fitness
```

### 4.6 Put-Away

**Alur Put-Away (Step 4 proses WMS):**
1. Operator buka halaman Put-Away Queue
2. Pilih order, lihat rekomendasi GA per item
3. Scan QR code sel yang dituju (atau pilih manual)
4. Sistem validasi kapasitas & status sel
5. Konfirmasi → stok tersimpan, sel diupdate, `put_away_confirmation` dicatat
6. Operator dapat **override** lokasi (role admin/supervisor) jika rekomendasi tidak memungkinkan
7. Setelah semua item confirmed → status order berubah ke `completed`
8. `RecalculateAffinityJob` di-dispatch: hitung ulang co-occurrence & normalisasi affinity_score

**Fitur tambahan:**
- Suggest alternative cells jika sel rekomendasi sudah penuh
- Fast/slow moving suggestions
- Batch scan (konfirmasi banyak item sekaligus)

### 4.7 Manajemen Stok

- **Stock Index:** list semua item + qty + lokasi sel
- **Stock Search:** cari item by SKU/nama
- **Stock Movements:** histori keluar-masuk stok (inbound, outbound, transfer)
- **Low Stock:** item dengan qty < min_stock
- **Deadstock:** item tidak bergerak melebihi threshold hari
- **Near Expiry:** (field tersedia)
- **Transfer Scan:** pindah stok antar sel via scan QR
- FIFO diterapkan pada outbound (earliest `received_at` dikeluarkan lebih dulu)

### 4.8 Outbound

- Buat order keluar: pilih item + qty
- Preview FIFO picking: sistem menentukan dari sel mana & batch mana yang diambil
- Batch confirm: kurangi stok, catat movement
- Validasi ketersediaan stok sebelum konfirmasi

### 4.9 Laporan

| Laporan | Keterangan |
|---|---|
| Inventory | Ringkasan stok per item + sel |
| Inbound | Histori penerimaan barang per periode |
| Put-Away | Aktivitas penempatan barang |
| Movements | Semua pergerakan stok |
| **GA Effectiveness** | Metrik GA: fitness score, waktu eksekusi, kepatuhan rekomendasi, perbandingan skenario |

**Laporan GA Effectiveness** adalah laporan kunci untuk skripsi:
- Summary cards: total run GA, avg fitness, best fitness, avg waktu eksekusi (ms), tingkat kepatuhan (%)
- Metrik efektivitas penempatan: split location count, avg lokasi per SKU, utilisasi kapasitas, estimasi waktu put-away
- Perbandingan 3 skenario: **Kondisi Aktual** vs **Penempatan Acak** (simulasi) vs **Rekomendasi GA**
- Grafik tren fitness score per bulan (Highcharts spline)
- Grafik pie kepatuhan rekomendasi (diikuti vs di-override)
- Grafik kolom rata-rata waktu eksekusi per bulan
- Export PDF & Excel
- Tabel riwayat 50 run GA terakhir

### 4.10 Visualisasi 3D Gudang

- Dibangun dengan **Three.js**
- Setiap rak dirender berdasarkan `pos_x`, `pos_z`, `rotation_y` dari database
- Warna sel berdasarkan status (available=hijau, partial=kuning, full=merah, blocked=abu)
- Klik sel → popup detail (item tersimpan, kapasitas, status)
- Filter: highlight sel berdasarkan item tertentu
- Tampilkan denah per kolom/grup
- Data diambil dari endpoint `GET /warehouse-3d/data`

### 4.11 Notifikasi & Audit

- **Notifikasi in-app:** stok rendah, deadstock, GA selesai diproses
- **WhatsApp alert** (opsional): `POST /dashboard/send-wa-alert`
- **Audit Log:** setiap create/update/delete model penting dicatat (user, action, perubahan field before/after, IP address)

---

## 5. Alur Sistem End-to-End

```
[Admin] Buat/sync Inbound Order
         │
         ▼
[Admin/Supervisor] Process GA
   Laravel (GaService) mengumpulkan:
   - item list dari inbound_order_details
   - cell list dari warehouse aktif
   - affinity scores dari item_affinities
         │ HTTP POST /optimize
         ▼
[Python GA Engine]
   Jalankan GA 100 individu, maks 150 generasi
   Kembalikan kromosom terbaik + fitness breakdown
         │
         ▼
[Laravel] Simpan ga_recommendation + ga_recommendation_details
   Status order → ga_processed
         │
         ▼
[Operator] Put-Away via QR Scan
   Scan QR code sel → konfirmasi
   Sistem: simpan put_away_confirmation, update stock, update cell status
   Status order → putaway → completed
         │
         ▼
[Job] RecalculateAffinityJob
   Hitung ulang co-occurrence semua pasangan item
   Normalisasi affinity_score = co_count / max(co_count)
   Update tabel item_affinities
```

---

## 6. Implementasi Khusus yang Perlu Dibahas di Bab 4

### 6.1 Capacity Demand (capacity_points)
Item tidak langsung menggunakan kuantitas sebagai bobot kapasitas. Laravel menghitung `capacity_demand = ceil(qty / item.max_stock * 100)` sebagai proxy "normalized capacity points". Ini agar item dengan max_stock besar tidak mendominasi kapasitas sel secara tidak proporsional.

### 6.2 preferred_cell_id
Jika item sudah memiliki `home_cell_id` (sel tetap) di master data, GA akan mengunci gen tersebut ke sel itu — tidak dioptimasi. Ini memungkinkan barang tertentu selalu ditempatkan di lokasi fixed.

### 6.3 Repair Chromosome
Setelah crossover dan mutasi, `repair_category_invalid_genes()` dipanggil untuk mengganti gen yang menghasilkan assignment sel dengan kategori salah. Priority: (1) sel kategori cocok + cukup kapasitas, (2) sel netral (tanpa kategori), (3) pertahankan jika tidak ada alternatif.

### 6.4 MSpart vs Standard Cell
Sistem mendukung dua jenis cell:
- **Standard:** `code = RACK-LEVEL` (mis. `R-01-A`), dibuat via Create Rak / Generate Layout
- **MSpart:** koordinat fisik blok-grup-kolom-baris (mis. `2-A-3-1`), diimport dari data fisik gudang client Avian. Cell MSpart menggunakan formula jarak berbeda di FC_SPLIT.

### 6.5 RecalculateAffinityJob
Saat order berubah ke status `completed`, job ini di-dispatch ke queue:
1. Ambil semua pair item dari semua order `completed`
2. Hitung co_occurrence_count untuk setiap pasangan
3. Normalisasi: `affinity_score = co_count / max_co_count`
4. Upsert ke tabel `item_affinities`

---

## 7. Antarmuka Pengguna (UI) — Halaman Utama

| Halaman | URL | Keterangan |
|---|---|---|
| Dashboard | `/` | KPI cards: inbound hari ini, stok rendah, kapasitas gudang, dll. |
| Sparepart | `/master/items` | DataTable + import Excel |
| Kategori | `/master/categories` | CRUD + color picker |
| Co-Occurrence | `/master/affinities` | Bar chart + DataTable afinitas |
| Warehouse | `/location/warehouses` | CRUD + generate layout |
| Rak | `/location/racks` | CRUD + DataTable |
| Sel | `/location/cells` | CRUD + QR label |
| Inbound Orders | `/inbound/orders` | Filter tanggal range (start_date/end_date) |
| Put-Away Queue | `/putaway/queue` | List order siap put-away |
| Put-Away Detail | `/putaway/{order}` | Scan QR + konfirmasi per item |
| Stok | `/stock` | Inventory list |
| Transfer Scan | `/stock/transfer-scan` | Pindah stok antar sel |
| Outbound | `/outbound` | FIFO picking |
| Laporan GA | `/reports/ga-effectiveness` | Laporan utama skripsi |
| Visualisasi 3D | `/warehouse-3d` | Three.js 3D warehouse |
| Scan QR Publik | `/c/{code}` | Tanpa auth, untuk scan label sel |

---

## 8. Poin Kesesuaian Bab 3 → Bab 4

| Artefak Bab 3 | Implementasi Bab 4 |
|---|---|
| Use Case Diagram (UC6: Proses GA) | GaService + Python GA Engine (POST /optimize) |
| Use Case Diagram (UC7: Put-Away) | PutAwayController + scan QR + konfirmasi |
| Activity Diagram inbound | InboundOrderController + status flow |
| Activity Diagram GA | GeneticAlgorithmEngine.run() |
| ERD (item_affinities) | Tabel item_affinities + RecalculateAffinityJob |
| ERD (ga_recommendations) | Tabel ga_recommendations + ga_recommendation_details |
| Desain antarmuka (mockup) | View blade AdminLTE — masing-masing sesuai route |
| Fitness Function F = FC_CAP + FC_CAT + FC_AFF + FC_SPLIT | fitness.py: evaluate_chromosome() |
| Rancangan operator GA | operators.py: tournament_selection(), uniform_crossover(), random_reset_mutation() |

---

## 9. Referensi Utama (Digunakan dalam Kode)

1. Holland, J.H. (1975). *Adaptation in Natural and Artificial Systems.* University of Michigan Press.
2. Goldberg, D.E. (1989). *Genetic Algorithms in Search, Optimization, and Machine Learning.* Addison-Wesley.
3. De Jong, K.A. (1975). *An Analysis of the Behavior of a Class of Genetic Adaptive Systems.* PhD Thesis, University of Michigan.
4. Michalewicz, Z. (1996). *Genetic Algorithms + Data Structures = Evolution Programs.* 3rd ed. Springer-Verlag.
5. Whitley, D. (1994). A genetic algorithm tutorial. *Statistics and Computing*, 4(2), 65–85.
6. Miller, B.L. & Goldberg, D.E. (1995). Genetic algorithms, tournament selection, and the effects of noise. *Complex Systems*, 9(3), 193–212.
7. Syswerda, G. (1989). Uniform crossover in genetic algorithms. *Proceedings of the 3rd ICGA*, pp. 2–9.
8. Henn, S. & Wäscher, G. (2012). Metaheuristics for order batching in warehouses. *Computers & Industrial Engineering*, 58(2), 270–280.
9. Van den Berg, J.P. (1999). A literature survey on planning and control of warehousing systems. *IIE Transactions*, 31(8), 751–762.
