"""
ga/fitness.py — Fungsi fitness multi-objektif untuk warehouse slotting.

Fitness Function:
    F(chromosome) = FC_CAP + FC_CAT + FC_AFF + FC_SPLIT   (maks 100)

Referensi:
    - Goldberg, D.E. (1989). Genetic Algorithms in Search, Optimization,
      and Machine Learning. Addison-Wesley, Boston.
    - Henn, S. & Wäscher, G. (2012). Metaheuristics for order batching in
      warehouses. Computers & Industrial Engineering, 58(2), 270-280.
    - Van den Berg, J.P. (1999). A literature survey on planning and control
      of warehousing systems. IIE Transactions, 31(8), 751-762.
"""

from __future__ import annotations
from typing import Dict, List, Optional, Tuple

from schemas import AffinityInput, CellInput, ItemInput


# ─────────────────────────────────────────────────────────────────────────────
# Helper: Affinity Map
# ─────────────────────────────────────────────────────────────────────────────

AffinityMap = Dict[Tuple[int, int], float]


def build_affinity_map(affinities: List[AffinityInput]) -> AffinityMap:
    """
    Bangun lookup dict simetris: (item_id_a, item_id_b) → skor afinitas (0-1).
    Simetris karena afinitas A→B = B→A.
    """
    aff_map: AffinityMap = {}
    for a in affinities:
        aff_map[(a.item_id, a.related_item_id)] = a.affinity_score
        aff_map[(a.related_item_id, a.item_id)] = a.affinity_score
    return aff_map


def get_affinity(item_a: int, item_b: int, aff_map: AffinityMap) -> float:
    return aff_map.get((item_a, item_b), 0.0)


def build_item_rack_map(cells_dict: Dict[int, CellInput]) -> Dict[int, set[str]]:
    """
    Bangun map item_id -> set rack_code berdasarkan stok existing per cell.

    Dipakai untuk memberi preferensi: jika item sudah tersimpan di rack tertentu,
    GA cenderung memilih rack yang sama (travel/picking lebih konsisten).
    """
    item_racks: Dict[int, set[str]] = {}
    for cell in cells_dict.values():
        if not cell.rack_code:
            continue
        for item_id in cell.existing_item_ids:
            racks = item_racks.setdefault(item_id, set())
            racks.add(cell.rack_code)
    return item_racks

def build_item_cell_map(cells_dict: Dict[int, CellInput]) -> Dict[int, set[int]]:
    """
    Bangun map item_id -> set cell_id berdasarkan stok existing per cell.

    Digunakan untuk mengetahui apakah rekomendasi GA menambah lokasi baru
    untuk SKU yang sebenarnya sudah tersimpan di cell tertentu.
    """
    item_cells: Dict[int, set[int]] = {}

    for cell_id, cell in cells_dict.items():
        for item_id in cell.existing_item_ids:
            cells = item_cells.setdefault(item_id, set())
            cells.add(cell_id)

    return item_cells


# ─────────────────────────────────────────────────────────────────────────────
# Pemetaan movement_type → kode zona
# ─────────────────────────────────────────────────────────────────────────────

# ─────────────────────────────────────────────────────────────────────────────
# FC_CAP — Fitness Kapasitas (maks 40)
# ─────────────────────────────────────────────────────────────────────────────

def fc_capacity(
    gene_idx:   int,
    chromosome: List[int],
    items:      List[ItemInput],
    cells_dict: Dict[int, CellInput],
) -> float:
    """
    FC_CAP (maks 35 poin):

    Mengukur apakah total barang yang dialokasikan ke satu cell tidak melebihi
    sisa kapasitasnya.

    Rumus:
        total_qty = Σ qty item yang dialokasikan ke cell yang sama
        Jika total_qty ≤ capacity_remaining  → fc_cap = 35 (feasible)
        Jika total_qty > capacity_remaining  → fc_cap = 35 × (capacity_remaining / total_qty)
                                                        [penalti proporsional, Goldberg 1989]

    Semakin penuh cell namun masih dalam batas = nilai tetap 35.
    Semakin besar overflow = nilai semakin mendekati 0.

    Bobot diturunkan dari 40 → 35 untuk memberi ruang FC_SPLIT distance penalty.
    """
    cell_id = chromosome[gene_idx]
    cell    = cells_dict.get(cell_id)
    if cell is None:
        return 0.0

    # Hitung total qty semua item yang dialokasikan ke cell ini dalam kromosom ini
    total_qty = sum(
        items[j].quantity
        for j in range(len(chromosome))
        if chromosome[j] == cell_id
    )

    if total_qty <= cell.capacity_remaining:
        return 35.0

    ratio = cell.capacity_remaining / total_qty if total_qty > 0 else 0.0
    return round(max(0.0, 35.0 * ratio), 6)


# ─────────────────────────────────────────────────────────────────────────────
# FC_CAT — Fitness Kategori (maks 30)
# ─────────────────────────────────────────────────────────────────────────────

def fc_category(item: ItemInput, cell: CellInput) -> float:
    """
    FC_CAT (maks 25 poin):

    Mengukur kesesuaian kategori item dengan kategori dominan cell.
    Jika cell sudah menyimpan item yang sama, cell dianggap cocok secara kategori
    karena sudah menjadi lokasi existing untuk item tersebut.

    Bobot diturunkan dari 30 → 25 untuk memberi ruang FC_SPLIT distance penalty.
    """

    # Existing same-item continuity: jika cell sudah menyimpan item yang sama,
    # maka cell dianggap sangat sesuai untuk item tersebut.
    if item.item_id in cell.existing_item_ids:
        return 25.0

    # Perfect match: kategori item = kategori dominan cell
    if (
        item.category_id is not None
        and cell.dominant_category_id is not None
        and item.category_id == cell.dominant_category_id
    ):
        return 25.0

    return 0.0

# ─────────────────────────────────────────────────────────────────────────────
# FC_AFF — Fitness Afinitas (maks 20)
# ─────────────────────────────────────────────────────────────────────────────

def fc_affinity(
    gene_idx:   int,
    chromosome: List[int],
    items:      List[ItemInput],
    cells_dict: Dict[int, CellInput],
    aff_map:    AffinityMap,
    item_racks: Dict[int, set[str]],
    item_cells: Dict[int, set[int]],
) -> float:
    """
    FC_AFF (maks 20 poin):

    Mendorong penempatan item-item yang berelasi (sering muncul bersama dalam
    satu order / memiliki co-occurrence tinggi) pada cell atau rack yang sama
    secara logis, berdasarkan tiga sumber informasi:

      1. Co-location dalam kromosom saat ini:
         Jika item lain dengan afinitas tinggi dialokasikan ke cell yang sama,
         skor dihitung dari rata-rata affinity_score pasangan tersebut.
         fc_aff = 20 × mean(affinity_score pasangan se-cell)

      2. Kontinuitas stok existing (jika item sendirian di cell):
         Menggunakan histori stok yang sudah tersimpan di warehouse sebagai
         tie-breaker — mendorong konsistensi lokasi antar-inbound order.
         - Item yang sama sudah ada di cell ini      → 20 (kontinuitas sempurna)
         - Item yang sama ada di rack yang sama      → 20
         - Item sudah ada di gudang tetapi rack baru →  0 (penalti)

      3. Cohesion sibling chunk (partial allocation):
         Jika item dibagi menjadi beberapa chunk dalam order yang sama
         (salah satu terkunci ke cell existing, sisa chunk bebas), reward
         penempatan di rack yang sama dengan sibling chunk terkunci.
         - Rack sama dengan sibling chunk → 18
         - Rack berbeda dari sibling chunk →  2 (penalti jarak fisik)

    Catatan implementasi:
        FC_AFF tidak menghitung jarak fisik antar-cell berdasarkan koordinat
        level/column. Kedekatan yang dimaksud adalah kedekatan LOGIS:
        berada dalam cell yang sama atau rack yang sama. Pendekatan ini
        konsisten dengan data yang tersedia (item_affinities dari co-occurrence
        inbound) dan cukup untuk memodelkan kemudahan picking order.

    Referensi: Henn, S. & Wäscher, G. (2012). Metaheuristics for order batching
               in warehouses. Computers & Industrial Engineering, 58(2), 270-280.

    affinity_score ∈ [0, 1] (dinormalisasi dari tabel item_affinities).
    """
    cell_id = chromosome[gene_idx]
    item    = items[gene_idx]
    cell    = cells_dict.get(cell_id)

    if cell is None:
        return 0.0

    # Prioritas tertinggi: item yang sama sudah ada di cell ini.
    if item.item_id in cell.existing_item_ids:
        return 20.0

    # Hitung skor co-location dengan item lain dalam kromosom yang sama.
    colocated_idx = [
        j for j in range(len(chromosome))
        if chromosome[j] == cell_id and j != gene_idx
    ]

    coloc_score = 0.0
    if colocated_idx:
        total_score = sum(
            get_affinity(item.item_id, items[j].item_id, aff_map)
            for j in colocated_idx
        )
        avg_score = total_score / len(colocated_idx)

        if avg_score > 0.0:
            coloc_score = 20.0 * avg_score

    # Kontinuitas berdasarkan lokasi existing.
    seen_cells = item_cells.get(item.item_id, set())
    seen_racks = item_racks.get(item.item_id, set())

    if seen_racks:
        if cell.rack_code and cell.rack_code in seen_racks:
            continuity_score = 20.0
        else:
            continuity_score = 0.0
    else:
        # Tidak ada riwayat stok existing. Cek cohesion dengan sibling chunk
        # dalam kromosom yang sama (partial allocation: satu chunk locked,
        # satu chunk bebas). Mendorong chunk bebas ke rack yang sama.
        sibling_racks: set[str] = set()
        for j in range(len(chromosome)):
            if items[j].item_id == item.item_id and j != gene_idx:
                sib_cell = cells_dict.get(chromosome[j])
                if sib_cell and sib_cell.rack_code:
                    sibling_racks.add(sib_cell.rack_code)

        if sibling_racks:
            if cell.rack_code and cell.rack_code in sibling_racks:
                continuity_score = 18.0   # rack sama dengan sibling chunk
            else:
                continuity_score = 2.0    # rack berbeda, penalti jarak fisik
        elif seen_cells:
            continuity_score = 2.0
        else:
            # Item benar-benar baru, belum ada histori lokasi.
            continuity_score = 10.0

    return round(max(coloc_score, continuity_score), 6)


# ─────────────────────────────────────────────────────────────────────────────
# FC_SPLIT — Fitness Anti-Split + Jarak Lokasi (maks 20)
# ─────────────────────────────────────────────────────────────────────────────

def cell_distance(cell_a: Optional[CellInput], cell_b: Optional[CellInput]) -> float:
    """
    Hitung jarak fisik antara dua cell berdasarkan rack_index dan cell_index.

    Rack distance diberi bobot ×10 karena berpindah rack jauh lebih
    melelahkan daripada berpindah cell dalam satu rack.

    Skala hasil:
        1-F ke 1-G : abs(1-1)×10 + abs(6-7) = 1   (sangat dekat)
        1-F ke 1-A : abs(1-1)×10 + abs(6-1) = 5   (satu rack)
        1-F ke 2-F : abs(1-2)×10 + abs(6-6) = 10  (rack sebelah)
        1-F ke 10-G: abs(1-10)×10 + abs(6-7) = 91 (sangat jauh)
    """
    if cell_a is None or cell_b is None:
        return 9999.0

    if (
        cell_a.blok is not None
        and cell_b.blok is not None
        and cell_a.baris_rak is not None
        and cell_b.baris_rak is not None
        and cell_a.kolom is not None
        and cell_b.kolom is not None
    ):
        return (
            abs(cell_a.blok - cell_b.blok) * 10
            + abs(cell_a.baris_rak - cell_b.baris_rak) * 2
            + abs(cell_a.kolom - cell_b.kolom)
        )

    rack_a = cell_a.rack_index if cell_a.rack_index is not None else 9999
    rack_b = cell_b.rack_index if cell_b.rack_index is not None else 9999
    idx_a  = cell_a.cell_index  if cell_a.cell_index  is not None else 9999
    idx_b  = cell_b.cell_index  if cell_b.cell_index  is not None else 9999

    return abs(rack_a - rack_b) * 10 + abs(idx_a - idx_b)


def fc_split(
    gene_idx:   int,
    chromosome: List[int],
    items:      List[ItemInput],
    item_cells: Dict[int, set[int]],
    cells_dict: Dict[int, CellInput],
) -> float:
    """
    FC_SPLIT (maks 20 poin) = FC_SPLIT_COUNT (maks 10) + FC_SPLIT_DISTANCE (maks 10):

    FC_SPLIT_COUNT — menghukum jumlah lokasi untuk SKU yang sama.
        1 lokasi  → 10 (tidak ada split)
        2 lokasi  →  5
        3 lokasi  →  3.33
        dst.

    FC_SPLIT_DISTANCE — menghukum jarak fisik antar lokasi split.
    Menggunakan cell_distance() yang mempertimbangkan rack_index dan cell_index,
    sehingga 1-F + 1-G (jarak 1) jauh lebih baik dari 1-F + 10-G (jarak 91).

        jarak ≤ 1  → 10  (sel bersebelahan)
        jarak ≤ 5  →  7  (dalam satu rack)
        jarak ≤ 10 →  4  (rack tetangga)
        jarak > 10 →  0  (terlalu jauh)

    Referensi: Van den Berg, J.P. (1999). A literature survey on planning and
               control of warehousing systems. IIE Transactions, 31(8), 751-762.
    """
    item         = items[gene_idx]
    current_cell_id = chromosome[gene_idx]
    current_cell = cells_dict.get(current_cell_id)

    recommended_cells = {
        chromosome[j]
        for j in range(len(chromosome))
        if items[j].item_id == item.item_id
    }

    existing_cells = item_cells.get(item.item_id, set())

    # ── FC_SPLIT_COUNT ────────────────────────────────────────────────────────
    all_cell_ids   = recommended_cells | existing_cells
    location_count = len(all_cell_ids)

    if location_count <= 1:
        split_count_score = 10.0
    else:
        split_count_score = max(0.0, round(10.0 / location_count, 6))

    # ── FC_SPLIT_DISTANCE ─────────────────────────────────────────────────────
    reference_ids = all_cell_ids - {current_cell_id}

    if not reference_ids:
        # Hanya satu lokasi — tidak ada split, skor sempurna.
        distance_score = 10.0
    else:
        distances = [
            cell_distance(current_cell, cells_dict.get(ref_id))
            for ref_id in reference_ids
        ]
        min_dist = min(distances) if distances else 9999.0

        if min_dist <= 1:
            distance_score = 10.0
        elif min_dist <= 5:
            distance_score = 7.0
        elif min_dist <= 10:
            distance_score = 4.0
        else:
            distance_score = 0.0

    return round(split_count_score + distance_score, 6)


# ─────────────────────────────────────────────────────────────────────────────
# Evaluator Utama
# ─────────────────────────────────────────────────────────────────────────────

def evaluate_chromosome(
    chromosome: List[int],
    items:      List[ItemInput],
    cells_dict: Dict[int, CellInput],
    aff_map:    AffinityMap,
) -> Tuple[float, List[dict]]:
    """
    Hitung fitness keseluruhan kromosom dan breakdown FC per gen.

    Fitness kromosom = rata-rata gene_fitness semua gen.
    Gene_fitness_i   = FC_CAP_i + FC_CAT_i + FC_AFF_i + FC_SPLIT_i  (maks 35+25+20+20 = 100)

    Returns:
        total_fitness  : float, rata-rata fitness (0-100)
        gene_details   : list[dict] dengan fc_cap, fc_cat, fc_aff, fc_split, gene_fitness
    """
    n = len(chromosome)
    if n == 0:
        return 0.0, []

    gene_details: List[dict] = []
    item_racks = build_item_rack_map(cells_dict)
    item_cells = build_item_cell_map(cells_dict)

    for i in range(n):
        cell = cells_dict.get(chromosome[i])
        if cell is None:
            gene_details.append(
                {"fc_cap": 0.0, "fc_cat": 0.0, "fc_aff": 0.0, "fc_split": 0.0, "gene_fitness": 0.0}
            )
            continue

        cap   = fc_capacity(i, chromosome, items, cells_dict)
        cat   = fc_category(items[i], cell)
        aff = fc_affinity(i, chromosome, items, cells_dict, aff_map, item_racks, item_cells)
        split = fc_split(i, chromosome, items, item_cells, cells_dict)

        gene_fit = cap + cat + aff + split   # maks 40+30+20+10 = 100
        gene_details.append({
            "fc_cap":       round(cap,   4),
            "fc_cat":       round(cat,   4),
            "fc_aff":       round(aff,   4),
            "fc_split":     round(split, 4),
            "gene_fitness": round(gene_fit, 4),
        })

    total_fitness = round(
        sum(g["gene_fitness"] for g in gene_details) / n,
        4,
    )
    return total_fitness, gene_details
