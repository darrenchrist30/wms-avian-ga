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
from typing import Dict, List, Tuple

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


# ─────────────────────────────────────────────────────────────────────────────
# Pemetaan movement_type → kode zona
# ─────────────────────────────────────────────────────────────────────────────

MOVEMENT_ZONE_MAP = {
    "fast_moving": "A",   # Zona A – Fast Moving
    "slow_moving": "B",   # Zona B – Slow Moving
    "heavy":       "C",   # Zona C – Heavy
}


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
    FC_CAP (maks 40 poin):

    Mengukur apakah total barang yang dialokasikan ke satu cell tidak melebihi
    sisa kapasitasnya.

    Rumus:
        total_qty = Σ qty item yang dialokasikan ke cell yang sama
        Jika total_qty ≤ capacity_remaining  → fc_cap = 40 (feasible)
        Jika total_qty > capacity_remaining  → fc_cap = 40 × (capacity_remaining / total_qty)
                                                        [penalti proporsional, Goldberg 1989]

    Semakin penuh cell namun masih dalam batas = nilai tetap 40.
    Semakin besar overflow = nilai semakin mendekati 0.
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
        return 40.0

    ratio = cell.capacity_remaining / total_qty if total_qty > 0 else 0.0
    return round(max(0.0, 40.0 * ratio), 6)


# ─────────────────────────────────────────────────────────────────────────────
# FC_CAT — Fitness Kategori/Zona (maks 30)
# ─────────────────────────────────────────────────────────────────────────────

def fc_category(item: ItemInput, cell: CellInput) -> float:
    """
    FC_CAT (maks 30 poin):

    Mengukur kesesuaian kategori item dengan kategori dominan cell / zona.
    Prinsip: barang yang sejenis disimpan di area yang sama (slotting policy).

    Referensi: Malmborg, C.J. (1996). An evaluation of the cycle time performance
               of automated storage and retrieval systems. European Journal of
               Operational Research, 90(3), 598-612.

    Skor:
        item.category_id == cell.dominant_category_id  → 30 (perfect match)
        movement_type cocok dengan zona cell            → 15 (zone match)
        Tidak cocok                                     →  0
    """
    # Perfect match: kategori item = kategori dominan cell
    if (
        item.category_id is not None
        and cell.dominant_category_id is not None
        and item.category_id == cell.dominant_category_id
    ):
        return 30.0

    # Zone match: movement_type item sesuai kode zona cell
    expected_zone = MOVEMENT_ZONE_MAP.get(item.movement_type)
    if expected_zone and cell.zone_category and expected_zone == cell.zone_category:
        return 15.0

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
) -> float:
    """
    FC_AFF (maks 20 poin):

    Mengukur seberapa baik item-item yang memiliki afinitas tinggi
    (sering diambil bersama / satu order) ditempatkan dalam cell yang sama.

    Referensi: Henn, S. & Wäscher, G. (2012). Metaheuristics for order batching
               in warehouses. Computers & Industrial Engineering, 58(2), 270-280.

    Rumus inti:
        colocated = item lain yang berada di cell yang sama
        Jika colocated ada:
            fc_aff = 20 × mean(affinity_score untuk setiap pasangan)
        Jika sendirian di cell, pakai konteks stok existing:
            - item yang sama sudah ada di cell ini      -> 20 (best continuity)
            - item yang sama ada di rack yang sama      -> 16
            - item yang sama ada di rack lain           -> 8  (penalti ringan)
            - belum ada histori item di warehouse       -> 10 (netral)

    Catatan: affinity_score ∈ [0, 1] (sudah dinormalisasi dari tabel item_affinities)
    """
    cell_id = chromosome[gene_idx]
    item    = items[gene_idx]
    cell    = cells_dict.get(cell_id)
    if cell is None:
        return 0.0

    colocated_idx = [
        j for j in range(len(chromosome))
        if chromosome[j] == cell_id and j != gene_idx
    ]

    if colocated_idx:
        total_score = sum(
            get_affinity(item.item_id, items[j].item_id, aff_map)
            for j in colocated_idx
        )
        avg_score = total_score / len(colocated_idx)   # 0.0 – 1.0
        # Jika belum ada data afinitas (avg=0), kembalikan netral 10.0
        # agar tidak menghukum colocation — konsisten dengan fc_split
        if avg_score == 0.0:
            return 10.0
        return round(20.0 * avg_score, 6)

    # Item sendirian di cell: gunakan histori stok existing untuk tie-break
    if item.item_id in cell.existing_item_ids:
        return 20.0

    seen_racks = item_racks.get(item.item_id, set())
    if seen_racks:
        if cell.rack_code and cell.rack_code in seen_racks:
            return 16.0
        return 8.0

    return 10.0  # Netral: item baru, belum ada histori cell/rack


# ─────────────────────────────────────────────────────────────────────────────
# FC_SPLIT — Fitness Anti-Split (maks 10)
# ─────────────────────────────────────────────────────────────────────────────

def fc_split(
    gene_idx:   int,
    chromosome: List[int],
    items:      List[ItemInput],
) -> float:
    """
    FC_SPLIT (maks 10 poin):

    Penalti jika satu SKU (item_id yang sama) dipecah ke lebih dari satu cell.
    Konsolidasi SKU memudahkan picking dan meminimalkan order travel time.

    Referensi: Van den Berg, J.P. (1999). A literature survey on planning and
               control of warehousing systems. IIE Transactions, 31(8), 751-762.

    Rumus:
        k = jumlah cell unik yang menampung item dengan item_id ini
        fc_split = 10 / k

        k = 1 → 10 (tidak dipecah, sempurna)
        k = 2 → 5
        k = 3 → 3.33
        k ≥ 4 → ≤ 2.5
    """
    item = items[gene_idx]
    cells_used = {
        chromosome[j]
        for j in range(len(chromosome))
        if items[j].item_id == item.item_id
    }
    k = len(cells_used)
    return round(10.0 / k, 6) if k > 0 else 10.0


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
    Gene_fitness_i   = FC_CAP_i + FC_CAT_i + FC_AFF_i + FC_SPLIT_i  (maks 100)

    Returns:
        total_fitness  : float, rata-rata fitness (0-100)
        gene_details   : list[dict] dengan fc_cap, fc_cat, fc_aff, fc_split, gene_fitness
    """
    n = len(chromosome)
    if n == 0:
        return 0.0, []

    gene_details: List[dict] = []
    item_racks = build_item_rack_map(cells_dict)

    for i in range(n):
        cell = cells_dict.get(chromosome[i])
        if cell is None:
            gene_details.append(
                {"fc_cap": 0.0, "fc_cat": 0.0, "fc_aff": 0.0, "fc_split": 0.0, "gene_fitness": 0.0}
            )
            continue

        cap   = fc_capacity(i, chromosome, items, cells_dict)
        cat   = fc_category(items[i], cell)
        aff   = fc_affinity(i, chromosome, items, cells_dict, aff_map, item_racks)
        split = fc_split(i, chromosome, items)

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
