"""
ga/operators.py — Operator Genetik: Seleksi, Crossover, Mutasi, Elitisme.

Referensi:
    - Holland, J.H. (1975). Adaptation in Natural and Artificial Systems.
      University of Michigan Press, Ann Arbor.
    - Goldberg, D.E. (1989). Genetic Algorithms in Search, Optimization,
      and Machine Learning. Addison-Wesley, Boston.
    - Michalewicz, Z. (1996). Genetic Algorithms + Data Structures = Evolution
      Programs. 3rd ed. Springer-Verlag, Berlin.
    - De Jong, K.A. (1975). An Analysis of the Behavior of a Class of Genetic
      Adaptive Systems. PhD Thesis, University of Michigan.
    - Miller, B.L. & Goldberg, D.E. (1995). Genetic algorithms, tournament
      selection, and the effects of noise. Complex Systems, 9(3), 193-212.
"""

from __future__ import annotations
import random
from typing import Dict, List, Optional, Tuple

from schemas import CellInput, ItemInput


def category_compatible(item: ItemInput, cell: CellInput) -> bool:
    """
    True when a cell is category-valid for the item.

    Same-SKU continuity is valid because the warehouse already stores that SKU
    in the cell.
    """
    if item.item_id in cell.existing_item_ids:
        return True

    return (
        item.category_id is not None
        and cell.dominant_category_id is not None
        and item.category_id == cell.dominant_category_id
    )


def feasible_cell_pool(item: ItemInput, cells: List[CellInput]) -> List[int]:
    """
    Candidate cells for one item.

    Thesis-test rule:
    - Use category-valid, capacity-feasible cells when available.
    - Fall back to category-invalid cells only when no valid category cell exists.
    """
    feasible = [c for c in cells if c.capacity_remaining >= item.quantity]
    if not feasible:
        feasible = cells

    category_valid = [c.cell_id for c in feasible if category_compatible(item, c)]
    if category_valid:
        return category_valid

    return [c.cell_id for c in feasible]


# ─────────────────────────────────────────────────────────────────────────────
# Representasi Kromosom — Direct Value Encoding
# ─────────────────────────────────────────────────────────────────────────────
#
# Satu kromosom merepresentasikan SATU kandidat solusi penempatan barang
# untuk seluruh item dalam satu inbound order (surat jalan).
#
# Panjang kromosom = n = jumlah baris inbound_details pada order tersebut.
# Nilai setiap gen  = cell_id (integer) — lokasi cell yang dipilih GA.
#
# Chromosome Model:
#
#          Gen-1        Gen-2        Gen-3        Gen-4
#       ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
#  SKU  │  SP-001  │ │  SP-002  │ │  SP-001  │ │  SP-003  │  ← TETAP (dari items[i])
#  Item │Piston Set│ │Alternator│ │Piston Set│ │ Brake Pad│  ← TETAP
#  Kat. │Engine Pt │ │Electrical│ │Engine Pt │ │Brake Sys │  ← TETAP
#  Qty  │    10    │ │    5     │ │    8     │ │   15     │  ← TETAP
#       ├──────────┤ ├──────────┤ ├──────────┤ ├──────────┤
#  Cell │    11    │ │    24    │ │    11    │ │    37    │  ← DIOPTIMASI GA
#       └──────────┘ └──────────┘ └──────────┘ └──────────┘
#
# chromosome = [11, 24, 11, 37]
#               ↑    ↑    ↑    ↑
#             Gen-1 Gen-2 Gen-3 Gen-4
#
# * Proses GA hanya mengoptimasi baris "Cell".
#   Atribut lain (SKU, Item, Kategori, Qty) bersifat tetap dan digunakan
#   sebagai penunjang perhitungan fitness cost (FC_CAP, FC_CAT, FC_AFF, FC_SPLIT).
#
# * Gen-1 dan Gen-3 memiliki SKU yang sama (SP-001) → jika cell berbeda,
#   kena penalti FC_SPLIT. GA akan berusaha meminimalkan split ini.
#
# Encoding: Direct Value / Integer Encoding (bukan binary / permutation).
# Cocok untuk Assignment Problem: item → cell.
# ─────────────────────────────────────────────────────────────────────────────


# ─────────────────────────────────────────────────────────────────────────────
# 1. Inisialisasi Populasi
# ─────────────────────────────────────────────────────────────────────────────

def initialize_population(
    pop_size: int,
    items:    List[ItemInput],
    cells:    List[CellInput],
) -> List[List[int]]:
    """
    Buat populasi awal dengan dua strategi (Holland, 1975):

    a) 50% Random Initialization:
       Setiap gen dipilih dari cell_id secara seragam acak.
       Memastikan keberagaman (diversity) populasi.

    b) 50% Greedy Initialization:
       Item dengan qty besar diprioritaskan ke cell berkapasitas besar.
       Menginjeksikan solusi yang sudah cukup baik sejak awal,
       mempercepat konvergensi (Whitley, 1994).

    Referensi: Whitley, D. (1994). A genetic algorithm tutorial.
               Statistics and Computing, 4(2), 65–85.
    """
    n_items  = len(items)
    cell_ids = [c.cell_id for c in cells]
    population: List[List[int]] = []

    half = pop_size // 2

    # ── a) Random (capacity-aware) ────────────────────────────────────────
    # Untuk setiap item, pilih hanya dari cell yang sisa kapasitasnya ≥ qty item.
    # Fallback ke semua cell jika tidak ada yang feasible (mencegah dead-end).
    for _ in range(half):
        chromosome: List[int] = []

        for item in items:
            if item.preferred_cell_id is not None:
                chromosome.append(item.preferred_cell_id)
                continue

            chromosome.append(random.choice(feasible_cell_pool(item, cells)))

        population.append(chromosome)

    # ── b) Greedy ──────────────────────────────────────────────────────────
    sorted_cells  = sorted(cells, key=lambda c: c.capacity_remaining, reverse=True)
    top_cell_ids  = [c.cell_id for c in sorted_cells[:max(1, len(sorted_cells) // 2)]]

    for _ in range(pop_size - half):
        chromosome: List[int] = []

        for item in items:
            if item.preferred_cell_id is not None:
                chromosome.append(item.preferred_cell_id)
                continue

            preferred_pool = feasible_cell_pool(item, sorted_cells)
            top_valid = [cid for cid in preferred_pool if cid in top_cell_ids]
            chromosome.append(random.choice(top_valid if top_valid else preferred_pool))

        population.append(chromosome)

    return population


# ─────────────────────────────────────────────────────────────────────────────
# 2. Seleksi — Tournament Selection
# ─────────────────────────────────────────────────────────────────────────────

def tournament_selection(
    population:      List[List[int]],
    fitnesses:       List[float],
    tournament_size: int = 3,
) -> List[int]:
    """
    Tournament Selection (Miller & Goldberg, 1995):

    Algoritma:
        1. Pilih secara acak sejumlah `tournament_size` individu dari populasi
        2. Kembalikan individu dengan nilai fitness tertinggi dari turnamen tersebut

    Analogi: beberapa peserta dipilih secara acak untuk bertanding,
    pemenang (fitness terbaik) melanjutkan ke tahap reproduksi.

    Keunggulan dibanding Roulette Wheel:
        - Tidak bergantung pada skala fitness (scale-independent)
        - Tekanan seleksi dapat diatur melalui tournament_size:
              size kecil  → seleksi lemah, diversity tinggi (eksplorasi)
              size besar  → seleksi ketat, konvergensi cepat (eksploitasi)
        - Tournament size = 3 menjaga keseimbangan eksplorasi dan eksploitasi
        - Lebih stabil pada fungsi fitness yang memiliki nilai negatif atau nol

    Probabilitas individu terbaik terpilih:
        P(best) = 1 - (1 - 1/N)^k
        di mana N = ukuran populasi, k = tournament_size

    Kompleksitas: O(tournament_size)

    Referensi: Miller, B.L. & Goldberg, D.E. (1995). Genetic algorithms,
               tournament selection, and the effects of noise.
               Complex Systems, 9(3), 193-212.
    """
    k          = min(tournament_size, len(population))
    candidates = random.sample(range(len(population)), k)
    winner_idx = max(candidates, key=lambda i: fitnesses[i])
    return population[winner_idx].copy()


# ─────────────────────────────────────────────────────────────────────────────
# 3. Crossover — Uniform Crossover
# ─────────────────────────────────────────────────────────────────────────────

def uniform_crossover(
    parent1:        List[int],
    parent2:        List[int],
    crossover_rate: float = 0.80,
) -> Tuple[List[int], List[int]]:
    """
    Uniform Crossover (Syswerda, 1989):

    Algoritma:
        1. Dengan probabilitas `crossover_rate`, lakukan crossover
        2. Bangkitkan mask biner acak M = [m₁, m₂, ..., mₙ],
           setiap mᵢ ~ Bernoulli(0.5)
        3. child1[i] = parent1[i] jika mᵢ = 0,  parent2[i] jika mᵢ = 1
           child2[i] = parent2[i] jika mᵢ = 0,  parent1[i] jika mᵢ = 1

    Contoh (n=6, mask=[0,1,0,1,0,1]):
        P1: [A  B  C  D  E  F]
        P2: [a  b  c  d  e  f]
        M : [0  1  0  1  0  1]
               ↓
        C1: [A  b  C  d  E  f]   ← P1 di posisi mask=0, P2 di mask=1
        C2: [a  B  c  D  e  F]   ← P2 di posisi mask=0, P1 di mask=1

    Keunggulan dibanding One-Point Crossover untuk Assignment Problem:
        - Setiap gen dipertukaran secara INDEPENDEN → lebih banyak kombinasi baru
        - Tidak ada "building block" yang tergantung posisi gen
        - Cocok untuk Direct Value Encoding (cell_id assignment):
          setiap gene merepresentasikan keputusan assignment yang independen
        - Meningkatkan eksplorasi ruang solusi yang besar (SLAP berdimensi tinggi)

    Kompleksitas: O(n)

    Referensi: Syswerda, G. (1989). Uniform crossover in genetic algorithms.
               Proceedings of the 3rd International Conference on Genetic
               Algorithms (ICGA), pp. 2–9.
    """
    n = len(parent1)
    if n == 0 or random.random() > crossover_rate:
        return parent1.copy(), parent2.copy()

    child1: List[int] = []
    child2: List[int] = []

    for i in range(n):
        if random.random() < 0.5:
            child1.append(parent1[i])
            child2.append(parent2[i])
        else:
            child1.append(parent2[i])
            child2.append(parent1[i])

    return child1, child2


# ─────────────────────────────────────────────────────────────────────────────
# 4. Mutasi — Random Reset Mutation
# ─────────────────────────────────────────────────────────────────────────────

def random_reset_mutation(
    chromosome:    List[int],
    cell_ids:      List[int],
    mutation_rate: float = 0.15,
    items:         Optional[List[ItemInput]]          = None,
    cells_dict:    Optional[Dict[int, CellInput]]     = None,
) -> List[int]:
    """
    Random Reset Mutation — Capacity-Aware (Michalewicz, 1996):

    Algoritma:
        Untuk setiap gen i:
            Dengan probabilitas `mutation_rate`:
                Jika items + cells_dict tersedia:
                    pool = cell yang capacity_remaining ≥ item.quantity
                    Fallback ke semua cell jika pool kosong
                Else:
                    pool = semua cell_ids
                chromosome[i] ← pilih acak dari pool

    Keunggulan dibanding pure random reset:
        - Menghindari assignment yang jelas infeasible (capacity overflow) sejak mutasi
        - GA tidak membuang generasi untuk memperbaiki solusi yang trivially buruk
        - FC_CAP tetap aktif sebagai penalti lunak; constraint ini hanya mempersempit domain

    Pool feasible dihitung sekali di awal (O(n_cells)) untuk efisiensi.

    Kompleksitas: O(n_items × n_cells) pre-compute + O(n_items) mutasi

    Referensi: Michalewicz, Z. (1996). Genetic Algorithms + Data Structures
               = Evolution Programs. 3rd ed. Springer-Verlag, Berlin.
    """
    # Pre-compute feasible cell pool per item (snapshot kapasitas awal order)
    if items and cells_dict:
        cells = list(cells_dict.values())
        feasible_pools: List[List[int]] = [
            feasible_cell_pool(item, cells)
            for item in items
        ]
    else:
        feasible_pools = []

    mutated = chromosome.copy()

    for i in range(len(mutated)):
        if items and items[i].preferred_cell_id is not None:
            mutated[i] = items[i].preferred_cell_id
            continue

        if random.random() < mutation_rate:
            if feasible_pools:
                pool = feasible_pools[i] if feasible_pools[i] else cell_ids
            else:
                pool = cell_ids

            mutated[i] = random.choice(pool)

    return mutated


# ─────────────────────────────────────────────────────────────────────────────
# 5. Elitisme
# ─────────────────────────────────────────────────────────────────────────────

def apply_elitism(
    old_population: List[List[int]],
    old_fitnesses:  List[float],
    new_population: List[List[int]],
    new_fitnesses:  List[float],
    elite_count:    int = 3,
) -> Tuple[List[List[int]], List[float]]:
    """
    Elitisme (De Jong, 1975):

    Pertahankan `elite_count` individu terbaik dari generasi sebelumnya
    ke dalam populasi baru dengan menggantikan `elite_count` individu terburuk.

    Properti yang dijamin:
        - Monotonic improvement: fitness terbaik tidak pernah menurun antar generasi
        - Solusi terbaik yang pernah ditemukan tidak hilang akibat crossover/mutasi

    Algoritma:
        1. Ambil top-k individu dari populasi lama (sorted by fitness desc)
        2. Ganti k individu terburuk dari populasi baru dengan elite tersebut

    Referensi: De Jong, K.A. (1975). An Analysis of the Behavior of a Class
               of Genetic Adaptive Systems. PhD Thesis, University of Michigan.
    """
    if elite_count <= 0:
        return new_population, new_fitnesses

    # Urutkan populasi lama, ambil yang terbaik
    sorted_old  = sorted(
        zip(old_fitnesses, old_population),
        key=lambda x: x[0],
        reverse=True,
    )
    elite_count = min(elite_count, len(sorted_old))
    elites      = [(fit, ind.copy()) for fit, ind in sorted_old[:elite_count]]

    # Urutkan populasi baru ascending (terburuk di depan)
    combined = sorted(
        zip(new_fitnesses, new_population),
        key=lambda x: x[0],
    )

    # Ganti individu terburuk dengan elite
    for i, (efit, eind) in enumerate(elites):
        if i < len(combined):
            combined[i] = (efit, eind)

    # Kembalikan dalam urutan acak agar posisi elite tidak bias seleksi berikutnya
    random.shuffle(combined)

    final_fits = [f for f, _ in combined]
    final_pop  = [ind for _, ind in combined]
    return final_pop, final_fits
