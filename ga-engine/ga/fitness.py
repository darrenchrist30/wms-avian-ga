"""
ga/fitness.py — Fungsi fitness multi-objektif untuk warehouse slotting.

Fitness Function:
    F(chromosome) = FC_CAP + FC_CAT + FC_AFF + FC_SPLIT + FC_MOV
    Maksimum = 30 + 25 + 20 + 15 + 10 = 100

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
# FC_CAP — Fitness Kapasitas (maks 30)
# ─────────────────────────────────────────────────────────────────────────────

def fc_capacity(
    gene_idx:   int,
    chromosome: List[int],
    items:      List[ItemInput],
    cells_dict: Dict[int, CellInput],
) -> float:
    """
    FC_CAP (maks 30 poin):

    Mengukur apakah total demand kapasitas yang dialokasikan ke satu cell
    tidak melebihi sisa kapasitasnya. Capacity demand dikirim Laravel
    sebagai quantity langsung terhadap cells.capacity_max.

    Rumus:
        demand = total capacity_demand item ke cell yang sama
        Jika demand ≤ capacity_remaining  → fc_cap = 30 (feasible)
        Jika demand > capacity_remaining  → fc_cap = 30 × (capacity_remaining / demand)
                                                        [penalti proporsional, Goldberg 1989]

    Semakin penuh cell namun masih dalam batas = nilai tetap 30.
    Semakin besar overflow = nilai semakin mendekati 0.

    Bobot diturunkan dari 35 → 30 untuk mengakomodasi FC_MOV (slotting FSN).
    """
    cell_id = chromosome[gene_idx]
    cell    = cells_dict.get(cell_id)
    if cell is None:
        return 0.0

    demand = sum(
        items[j].capacity_demand
        for j in range(len(chromosome))
        if chromosome[j] == cell_id
    )

    if demand <= cell.capacity_remaining:
        return 30.0

    ratio = cell.capacity_remaining / demand if demand > 0 else 0.0
    return round(max(0.0, 30.0 * ratio), 6)


# ─────────────────────────────────────────────────────────────────────────────
# FC_CAT — Fitness Kategori (maks 25)
# ─────────────────────────────────────────────────────────────────────────────

def fc_category(
    item: ItemInput,
    cell: CellInput,
    item_cells: Dict[int, set[int]],
    cells_dict: Dict[int, CellInput],
) -> float:
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

    # Tier 2: cell belum memiliki kategori dominan (kosong/baru).
    #
    # Jika item sudah punya lokasi existing, cell netral yang dekat lokasi
    # existing adalah expansion cell yang valid secara operasional. Beri skor
    # hampir setara category match supaya GA tidak memilih cell jauh hanya demi
    # FC_CAT.
    if cell.dominant_category_id is None:
        existing_cell_ids = item_cells.get(item.item_id, set())
        if existing_cell_ids:
            distances = [
                cell_distance(cell, cells_dict.get(existing_cell_id))
                for existing_cell_id in existing_cell_ids
                if cells_dict.get(existing_cell_id) is not None
            ]
            min_dist = min(distances) if distances else 9999.0

            if min_dist <= 1:
                return 24.0
            if min_dist <= 5:
                return 22.0
            if min_dist <= 10:
                return 18.0

        return 12.5

    # Tier 3: kategori dominan cell berbeda dengan item → penalti
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

    if seen_cells:
        # For MSpart, rack_code may only represent the blok. That is too broad:
        # 2-A and 2-C share the same blok but are operationally different areas.
        # Score continuity by physical distance to the nearest existing SKU cell.
        distances = [
            cell_distance(cell, cells_dict.get(existing_cell_id))
            for existing_cell_id in seen_cells
            if cells_dict.get(existing_cell_id) is not None
        ]
        min_dist = min(distances) if distances else 9999.0

        if min_dist <= 0:
            continuity_score = 20.0
        elif min_dist <= 1:
            continuity_score = 19.0
        elif min_dist <= 5:
            continuity_score = 16.0
        elif min_dist <= 10:
            continuity_score = 8.0
        else:
            continuity_score = 0.0
    elif seen_racks:
        if cell.rack_code and cell.rack_code in seen_racks:
            continuity_score = 12.0
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
# FC_SPLIT — Fitness Anti-Split + Jarak Lokasi (maks 15)
# ─────────────────────────────────────────────────────────────────────────────

def cell_distance(cell_a: Optional[CellInput], cell_b: Optional[CellInput]) -> float:
    """
    Hitung jarak fisik berdasarkan koordinat denah:
    blok, grup, kolom, baris.

    Rack distance diberi bobot ×10 karena berpindah rack jauh lebih
    melelahkan daripada berpindah cell dalam satu rack.

    Skala bobot default:
        beda blok  = 10 poin jarak
        beda grup  = 5 poin jarak
        beda kolom = 2 poin jarak
        beda baris = 1 poin jarak
    """
    if cell_a is None or cell_b is None:
        return 9999.0

    if (
        cell_a.blok is not None
        and cell_b.blok is not None
        and cell_a.grup is not None
        and cell_b.grup is not None
        and cell_a.kolom is not None
        and cell_b.kolom is not None
        and cell_a.baris is not None
        and cell_b.baris is not None
    ):
        grup_a = ord(str(cell_a.grup).upper()[0]) - ord("A") + 1
        grup_b = ord(str(cell_b.grup).upper()[0]) - ord("A") + 1

        return (
            abs(cell_a.blok - cell_b.blok) * 10
            + abs(grup_a - grup_b) * 5
            + abs(cell_a.kolom - cell_b.kolom) * 2
            + abs(cell_a.baris - cell_b.baris) * 1
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
    FC_SPLIT (maks 15 poin) = FC_SPLIT_COUNT (maks 7.5) + FC_SPLIT_DISTANCE (maks 7.5):

    Bobot diturunkan dari 20 → 15 untuk mengakomodasi FC_MOV.

    FC_SPLIT_COUNT — menghukum jumlah lokasi untuk SKU yang sama.
        1 lokasi  → 10 (tidak ada split)
        2 lokasi  →  5
        3 lokasi  →  3.33
        dst.

    FC_SPLIT_DISTANCE — menghukum jarak fisik antar lokasi split.
    Menggunakan cell_distance() yang mempertimbangkan rack_index dan cell_index,
    sehingga 1-F + 1-G (jarak 1) jauh lebih baik dari 1-F + 10-G (jarak 91).

        jarak <= 1  -> 7.5 (sel bersebelahan)
        jarak <= 5  -> 5.0 (dalam area dekat)
        jarak <= 10 -> 3.0 (masih cukup dekat)
        jarak > 10  -> 0.0 (terlalu jauh)

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

    # ── FC_SPLIT_COUNT (maks 7.5) ─────────────────────────────────────────────
    all_cell_ids   = recommended_cells | existing_cells
    location_count = len(all_cell_ids)

    if location_count <= 1:
        split_count_score = 7.5
    else:
        split_count_score = max(0.0, round(7.5 / location_count, 6))

    # ── FC_SPLIT_DISTANCE (maks 7.5) ──────────────────────────────────────────
    reference_ids = all_cell_ids - {current_cell_id}

    if not reference_ids:
        distance_score = 7.5
    else:
        distances = [
            cell_distance(current_cell, cells_dict.get(ref_id))
            for ref_id in reference_ids
        ]
        min_dist = min(distances) if distances else 9999.0

        if min_dist <= 1:
            distance_score = 7.5
        elif min_dist <= 5:
            distance_score = 5.0
        elif min_dist <= 10:
            distance_score = 3.0
        else:
            distance_score = 0.0

    return round(split_count_score + distance_score, 6)


# ─────────────────────────────────────────────────────────────────────────────
# FC_MOV — Fitness Movement Type / Slotting FSN (maks 10)
# ─────────────────────────────────────────────────────────────────────────────

def fc_movement(item: ItemInput, cell: CellInput) -> float:
    """
    FC_MOV (maks 10 poin):

    Menerapkan prinsip slotting FSN (Fast-Slow-Non moving): item fast-moving
    ditempatkan di zona dekat pintu masuk/akses (blok kecil) agar operator
    cepat mengambilnya; item slow-moving di zona belakang.

    Proksi jarak dari pintu = nilai blok (blok 1 = paling dekat pintu).
    Cell tanpa koordinat blok (non-mspart) mendapat skor netral (5.0).

    Referensi:
        Frazelle, E.H. (2002). World-Class Warehousing and Material Handling.
        McGraw-Hill. pp. 54-58 (ABC/FSN slotting).
    """
    blok = cell.blok
    mov  = item.movement_type

    if blok is None or mov is None:
        return 5.0  # neutral: no movement/coordinate data, not full reward

    if mov == 'fast_moving':
        # Reward dekat pintu (blok rendah)
        if blok <= 1:   return 10.0
        if blok <= 2:   return 8.0
        if blok <= 3:   return 5.0
        return 2.0

    if mov == 'slow_moving':
        # Reward jauh dari pintu (blok tinggi), bebaskan area depan untuk fast
        if blok >= 4:   return 10.0
        if blok >= 3:   return 8.0
        if blok >= 2:   return 6.0
        return 3.0

    # non_moving — paling jauh
    if blok >= 5:   return 10.0
    if blok >= 4:   return 7.0
    if blok >= 3:   return 4.0
    return 1.0


# ─────────────────────────────────────────────────────────────────────────────
# Evaluator Utama
# ─────────────────────────────────────────────────────────────────────────────

def evaluate_chromosome(
    chromosome: List[int],
    items:      List[ItemInput],
    cells_dict: Dict[int, CellInput],
    aff_map:    AffinityMap,
    item_racks: Optional[Dict[int, set]] = None,
    item_cells: Optional[Dict[int, set]] = None,
) -> Tuple[float, List[dict]]:
    """
    Hitung fitness keseluruhan kromosom dan breakdown FC per gen.

    Fitness kromosom = rata-rata gene_fitness semua gen.
    Gene_fitness_i   = FC_CAP_i + FC_CAT_i + FC_AFF_i + FC_SPLIT_i + FC_MOV_i
                       (maks 30+25+20+15+10 = 100)

    item_racks / item_cells bisa di-pass dari luar agar tidak dibangun ulang
    setiap evaluasi (pre-built sekali di __init__ engine, tidak berubah selama GA).

    Returns:
        total_fitness  : float, rata-rata fitness (0-100)
        gene_details   : list[dict] dengan fc_cap, fc_cat, fc_aff, fc_split, fc_mov, gene_fitness
    """
    n = len(chromosome)
    if n == 0:
        return 0.0, []

    gene_details: List[dict] = []
    if item_racks is None:
        item_racks = build_item_rack_map(cells_dict)
    if item_cells is None:
        item_cells = build_item_cell_map(cells_dict)

    for i in range(n):
        cell = cells_dict.get(chromosome[i])
        if cell is None:
            gene_details.append(
                {"fc_cap": 0.0, "fc_cat": 0.0, "fc_aff": 0.0, "fc_split": 0.0, "fc_mov": 0.0, "gene_fitness": 0.0}
            )
            continue

        cap   = fc_capacity(i, chromosome, items, cells_dict)
        cat   = fc_category(items[i], cell, item_cells, cells_dict)
        aff   = fc_affinity(i, chromosome, items, cells_dict, aff_map, item_racks, item_cells)
        split = fc_split(i, chromosome, items, item_cells, cells_dict)
        mov   = fc_movement(items[i], cell)

        gene_fit = cap + cat + aff + split + mov   # maks 30+25+20+15+10 = 100
        gene_details.append({
            "fc_cap":       round(cap,   4),
            "fc_cat":       round(cat,   4),
            "fc_aff":       round(aff,   4),
            "fc_split":     round(split, 4),
            "fc_mov":       round(mov,   4),
            "gene_fitness": round(gene_fit, 4),
        })

    total_fitness = round(
        sum(g["gene_fitness"] for g in gene_details) / n,
        4,
    )
    return total_fitness, gene_details
