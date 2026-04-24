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
"""

from __future__ import annotations
import random
from typing import List, Tuple

from schemas import CellInput, ItemInput


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

    # ── a) Random ─────────────────────────────────────────────────────────
    for _ in range(half):
        population.append([random.choice(cell_ids) for _ in range(n_items)])

    # ── b) Greedy ──────────────────────────────────────────────────────────
    sorted_cells = sorted(cells, key=lambda c: c.capacity_remaining, reverse=True)
    top_cell_ids = [c.cell_id for c in sorted_cells[:max(1, len(sorted_cells) // 2)]]

    for _ in range(pop_size - half):
        chromosome: List[int] = []
        for item in items:
            if item.quantity <= sorted_cells[0].capacity_remaining:
                # Pilih dari cell berkapasitas besar dengan sedikit randomness
                chromosome.append(random.choice(top_cell_ids))
            else:
                chromosome.append(random.choice(cell_ids))
        population.append(chromosome)

    return population


# ─────────────────────────────────────────────────────────────────────────────
# 2. Seleksi — Roulette Wheel Selection (Fitness Proportionate Selection)
# ─────────────────────────────────────────────────────────────────────────────

def roulette_wheel_selection(
    population: List[List[int]],
    fitnesses:  List[float],
) -> List[int]:
    """
    Roulette Wheel Selection / Fitness Proportionate Selection (Goldberg, 1989):

    Analogi: setiap individu mendapat jatah lingkaran roulette sebanding
    dengan fitness-nya. Semakin tinggi fitness, semakin besar peluang terpilih.

    Algoritma:
        1. Hitung total fitness  S = Σ fitness[i]
        2. Bangkitkan bilangan acak  r ~ Uniform(0, S)
        3. Iterasi populasi, akumulasikan fitness sampai kumulatif ≥ r
        4. Kembalikan individu saat kondisi terpenuhi

    Probabilitas seleksi individu ke-i:
        P(i) = fitness[i] / S

    Properti:
        - Individu dengan fitness lebih tinggi memiliki peluang terpilih lebih besar
        - Semua individu (termasuk yang lemah) tetap punya peluang terpilih
        - Menjaga keberagaman (diversity) populasi

    Kompleksitas: O(n)

    Referensi: Goldberg, D.E. (1989). Genetic Algorithms in Search,
               Optimization, and Machine Learning. Addison-Wesley, Boston.
    """
    total_fitness = sum(fitnesses)

    if total_fitness <= 0:
        return random.choice(population).copy()

    r          = random.uniform(0, total_fitness)
    cumulative = 0.0

    for individual, fitness in zip(population, fitnesses):
        cumulative += fitness
        if cumulative >= r:
            return individual.copy()

    return population[-1].copy()


# ─────────────────────────────────────────────────────────────────────────────
# 3. Crossover — One-Point Crossover
# ─────────────────────────────────────────────────────────────────────────────

def one_point_crossover(
    parent1:        List[int],
    parent2:        List[int],
    crossover_rate: float = 0.80,
) -> Tuple[List[int], List[int]]:
    """
    One-Point Crossover (Holland, 1975):

    Algoritma:
        1. Dengan probabilitas `crossover_rate`, lakukan crossover
        2. Pilih satu titik potong acak: point ∈ [1, n-1]
        3. Child1 = parent1[:point] + parent2[point:]
           Child2 = parent2[:point] + parent1[point:]

    Contoh (n=6, point=3):
        P1: [A  B  C | D  E  F]   →   C1: [A  B  C | d  e  f]
        P2: [a  b  c | d  e  f]   →   C2: [a  b  c | D  E  F]

    Cocok untuk integer encoding (assignment problem) karena:
        - Tidak seperti permutation encoding, gen yang sama boleh muncul berulang
        - Tidak perlu repair operator seperti pada TSP

    Kompleksitas: O(n)
    """
    n = len(parent1)
    if n <= 1 or random.random() > crossover_rate:
        return parent1.copy(), parent2.copy()

    point  = random.randint(1, n - 1)
    child1 = parent1[:point] + parent2[point:]
    child2 = parent2[:point] + parent1[point:]
    return child1, child2


# ─────────────────────────────────────────────────────────────────────────────
# 4. Mutasi — Random Reset Mutation
# ─────────────────────────────────────────────────────────────────────────────

def random_reset_mutation(
    chromosome:    List[int],
    cell_ids:      List[int],
    mutation_rate: float = 0.15,
) -> List[int]:
    """
    Random Reset Mutation (Michalewicz, 1996):

    Algoritma:
        Untuk setiap gen i:
            Dengan probabilitas `mutation_rate`:
                chromosome[i] ← pilih cell_id baru secara acak

    Berbeda dengan Bit-Flip Mutation (hanya untuk binary encoding).
    Untuk integer encoding, reset ke nilai lain dalam domain yang valid.

    Fungsi:
        - Menjaga keberagaman populasi
        - Mencegah konvergensi prematur ke local optimum
        - mutation_rate = 0.15 → sekitar 15% gen akan dimutasi per individu

    Kompleksitas: O(n)
    """
    mutated = chromosome.copy()
    for i in range(len(mutated)):
        if random.random() < mutation_rate:
            mutated[i] = random.choice(cell_ids)
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
    """
    if elite_count <= 0:
        return new_population, new_fitnesses

    # Urutkan populasi lama, ambil yang terbaik
    sorted_old = sorted(
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
