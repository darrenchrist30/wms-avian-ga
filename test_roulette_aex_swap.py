"""
Test single configuration:
Roulette + Uniform Crossover + Hybrid Mutation (RSM+PSM)
Mengadaptasi operator GA untuk konteks SLAP
dengan representasi kromosom SKU -> Cell ID.
"""

import random
import time
from dataclasses import dataclass
from typing import List, Tuple
import numpy as np

# ============================================================================
# DATA CLASSES
# ============================================================================

@dataclass
class SKU:
    """Represents an incoming SKU"""
    id: str
    category: str
    quantity: int
    affinity_group: str


@dataclass
class Cell:
    """Represents a storage cell"""
    id: str
    zone: str
    capacity: int
    current_usage: int
    row: int
    col: int


# ============================================================================
# DATA GENERATION
# ============================================================================

def generate_warehouse_data(num_skus: int = 25, num_cells: int = 21):
    """Generate warehouse data for testing GA."""

    categories = ["Paint", "Hardware", "Plumbing", "Electrical", "Lumber"]
    affinity_groups = ["Group_A", "Group_B", "Group_C", "Group_D", "Group_E"]

    skus: List[SKU] = []
    for i in range(num_skus):
        sku = SKU(
            id=f"SKU-{i + 1:03d}",
            category=random.choice(categories),
            quantity=random.randint(5, 20),
            affinity_group=random.choice(affinity_groups),
        )
        skus.append(sku)

    cells: List[Cell] = []
    for row in range(1, 8):
        for col in ["A", "B", "C"]:
            cell_id = f"{row}{col}"
            cells.append(
                Cell(
                    id=cell_id,
                    zone=random.choice(categories + ["Mixed"]),
                    capacity=random.randint(20, 50),
                    current_usage=random.randint(0, 10),
                    row=row,
                    col=ord(col),
                )
            )

    return skus[:num_skus], cells[:num_cells]


# ============================================================================
# FITNESS FUNCTIONS
# ============================================================================

def get_adjacent_cells(cell: Cell, cells: List[Cell]) -> List[str]:
    """
    Get IDs of adjacent cells (atas-bawah / kiri-kanan) berdasarkan row & col.
    """
    adjacent: List[str] = []

    for other in cells:
        if other.id == cell.id:
            continue

        row_diff = abs(other.row - cell.row)
        col_diff = abs(other.col - cell.col)

        # Orthogonal adjacency (no diagonals)
        if (row_diff == 1 and col_diff == 0) or (row_diff == 0 and col_diff == 1):
            adjacent.append(other.id)

    return adjacent


def calculate_fitness_gene(
    sku: SKU,
    cell: Cell,
    chromosome: List[str],
    skus: List[SKU],
    cells: List[Cell],
) -> float:
    """
    Calculate fitness for one gene (satu SKU pada satu cell) sesuai proposal:

    Fitness Gene = FC_CAP + FC_CAT + FC_AFF + FC_SPLIT

    - FC_CAP  (40) : kapasitas cell mencukupi
    - FC_CAT  (30/15) : kategori cocok / Mixed
    - FC_AFF  (20/10) : dekat dengan SKU se-kategori (adjacent / same-row-adjacent)
    - FC_SPLIT (10) : prefer cell yang hanya menyimpan satu SKU
                      (mengurangi overcrowding / cell terlalu "ramai").
    """
    fitness = 0.0

    # ----------------------------------------------------------------------
    # FC_CAP (40 points) - Capacity constraint
    # ----------------------------------------------------------------------
    remaining_capacity = cell.capacity - cell.current_usage
    if sku.quantity <= remaining_capacity:
        fitness += 40.0

    # ----------------------------------------------------------------------
    # FC_CAT (30 points) - Category matching
    # ----------------------------------------------------------------------
    if sku.category == cell.zone:
        fitness += 30.0
    elif cell.zone == "Mixed":
        fitness += 15.0

    # ----------------------------------------------------------------------
    # FC_AFF (20 points) - Affinity (same category in adjacent cells)
    # ----------------------------------------------------------------------
    adjacent_cells = get_adjacent_cells(cell, cells)
    has_affinity = False
    same_row_affinity = False

    for adj_cell_id in adjacent_cells:
        # Cari cell adjacent di list cells
        adj_cell_idx = next((i for i, c in enumerate(cells) if c.id == adj_cell_id), None)
        if adj_cell_idx is None:
            continue

        # Cek SKU apa saja yang ditempatkan di cell adjacent itu
        skus_in_adj = [skus[i] for i, cid in enumerate(chromosome) if cid == adj_cell_id]
        for adj_sku in skus_in_adj:
            if adj_sku.category == sku.category:
                has_affinity = True
                adj_cell = cells[adj_cell_idx]
                if adj_cell.row == cell.row:
                    same_row_affinity = True
                break

        if has_affinity:
            break

    if has_affinity:
        # Ada SKU dengan kategori sama di cell adjacent
        fitness += 20.0
    elif same_row_affinity:
        # (fallback – secara logika ini sudah tertangkap di atas, tapi dibiarkan
        #  untuk jaga struktur / kemudahan modifikasi bobot nanti)
        fitness += 10.0

    # ----------------------------------------------------------------------
    # FC_SPLIT (10 points) - Prefer cell with single SKU (avoid overcrowding)
    # ----------------------------------------------------------------------
    # Menghitung berapa banyak SKU yang menggunakan cell ini.
    # Jika hanya 1 SKU yang menggunakan cell tersebut, diberi bonus,
    # sehingga GA cenderung menghindari terlalu banyak SKU sharing satu cell.
    count_skus_in_cell = sum(1 for cid in chromosome if cid == cell.id)
    if count_skus_in_cell == 1:
        fitness += 10.0

    return fitness


def calculate_fitness(chromosome: List[str], skus: List[SKU], cells: List[Cell]) -> float:
    """
    Calculate total fitness sebagai jumlah Fitness Gene untuk semua SKU.
    """
    total = 0.0
    for i, cell_id in enumerate(chromosome):
        cell = next((c for c in cells if c.id == cell_id), None)
        if cell is not None:
            total += calculate_fitness_gene(skus[i], cell, chromosome, skus, cells)
    return total


# ============================================================================
# GA OPERATORS
# ============================================================================

def initialize_population(pop_size: int, skus: List[SKU], cells: List[Cell]) -> List[List[str]]:
    """
    Initialize random population.
    Kromosom: array panjang n_sku, tiap gen = ID cell untuk SKU ke-i.
    """
    population: List[List[str]] = []
    cell_ids = [cell.id for cell in cells]

    for _ in range(pop_size):
        chromosome = [random.choice(cell_ids) for _ in range(len(skus))]
        population.append(chromosome)

    return population


def selection_roulette(
    population: List[List[str]],
    fitnesses: List[float],
    num_parents: int,
) -> List[List[str]]:
    """
    Roulette wheel selection.
    Fitness yang lebih besar -> probabilitas terpilih lebih besar.

    Untuk menghindari kasus fitness negatif / 0, dilakukan shifting
    sehingga minimum fitness menjadi 1.
    """
    min_fitness = min(fitnesses)
    adjusted_fitnesses = [f - min_fitness + 1.0 for f in fitnesses]
    total_fitness = sum(adjusted_fitnesses)

    if total_fitness <= 0:
        # fallback: jika sesuatu sangat salah, pilih parent acak
        return random.choices(population, k=num_parents)

    parents = random.choices(population, weights=adjusted_fitnesses, k=num_parents)
    return parents


def crossover_uniform(
    parent1: List[str],
    parent2: List[str],
    skus: List[SKU],
    cells: List[Cell],
) -> Tuple[List[str], List[str]]:
    """
    Uniform Crossover untuk representasi assignment (SKU -> Cell ID).

    Untuk setiap posisi gen i:
    - dengan probabilitas 0.5: child1[i] = parent1[i], child2[i] = parent2[i]
    - dengan probabilitas 0.5: child1[i] = parent2[i], child2[i] = parent1[i]

    Tidak memaksa keunikan gen dan cocok untuk kromosom di mana nilai
    boleh berulang (cell dapat dipakai beberapa SKU).
    """
    n = len(parent1)
    if n == 0 or len(parent2) != n:
        return parent1.copy(), parent2.copy()

    child1: List[str] = []
    child2: List[str] = []

    for i in range(n):
        if random.random() < 0.5:
            child1.append(parent1[i])
            child2.append(parent2[i])
        else:
            child1.append(parent2[i])
            child2.append(parent1[i])

    return child1, child2


# ============================================================================
# HYBRID MUTATION LOGIC (Kordos et al. inspired)
# ============================================================================

def mutation_rsm_logic(chromosome: List[str]) -> List[str]:
    """Reverse Sequence Mutation (RSM): membalik urutan suatu subsequence."""
    n = len(chromosome)
    if n < 2:
        return chromosome

    idx1, idx2 = random.sample(range(n), 2)
    if idx1 > idx2:
        idx1, idx2 = idx2, idx1

    segment = chromosome[idx1 : idx2 + 1]
    chromosome[idx1 : idx2 + 1] = segment[::-1]
    return chromosome2


def mutation_psm_logic(chromosome: List[str]) -> List[str]:
    """Partial Shuffle Mutation (PSM): mengacak urutan suatu subsequence."""
    n = len(chromosome)
    if n < 2:
        return chromosome

    idx1, idx2 = random.sample(range(n), 2)
    if idx1 > idx2:
        idx1, idx2 = idx2, idx1

    sub_segment = chromosome[idx1 : idx2 + 1]
    random.shuffle(sub_segment)
    chromosome[idx1 : idx2 + 1] = sub_segment
    return chromosome

def mutation_hybrid(chromosome: List[str], mutation_rate: float) -> List[str]:
    """
    Hybrid Mutation (RSM + PSM) terinspirasi Kordos et al.:

    - Mutasi terjadi dengan probabilitas 'mutation_rate'.
    - Jika mutasi terjadi:
        - 75%: pakai RSM
        - 25%: pakai PSM
    """
    mutated = chromosome.copy()

    if random.random() < mutation_rate:
        if random.random() < 0.75:
            mutated = mutation_rsm_logic(mutated)
        else:
            mutated = mutation_psm_logic(mutated)

    return mutated


# ============================================================================
# MAIN GA
# ============================================================================

def run_ga(
    skus: List[SKU],
    cells: List[Cell],
    pop_size: int = 100,
    num_generations: int = 150,
    mutation_rate: float = 0.15,
    elitism: int = 3,
) -> dict:
    """Run Genetic Algorithm with Roulette + Uniform crossover + Hybrid mutation."""

    print(f"\n{'=' * 60}")
    print("Testing configuration: roulette + uniform crossover + hybrid mutation")
    print(f"Population: {pop_size}, Generations: {num_generations}")
    print(f"{'=' * 60}\n")

    start_time = time.time()

    # Initialize population
    population = initialize_population(pop_size, skus, cells)

    best_fitness = float("-inf")
    best_chromosome: List[str] | None = None
    fitness_history: List[float] = []

    # Evolution loop
    for gen in range(num_generations):
        # Evaluate fitness
        fitnesses = [calculate_fitness(chrom, skus, cells) for chrom in population]

        # Track best individual
        gen_best_fitness = max(fitnesses)
        gen_best_idx = fitnesses.index(gen_best_fitness)

        if gen_best_fitness > best_fitness:
            best_fitness = gen_best_fitness
            best_chromosome = population[gen_best_idx].copy()

        fitness_history.append(best_fitness)

        if gen % 10 == 0:
            print(f"Gen {gen:3d}: Best Fitness = {best_fitness:.2f}")

        # Create new population
        new_population: List[List[str]] = []

        # Elitism: copy individu terbaik ke generasi berikutnya
        sorted_pop = sorted(
            zip(population, fitnesses), key=lambda x: x[1], reverse=True
        )
        for i in range(min(elitism, pop_size)):
            new_population.append(sorted_pop[i][0])

        # Generate offspring
        while len(new_population) < pop_size:
            # Selection
            parents = selection_roulette(population, fitnesses, 2)

            # Crossover (Uniform)
            child1, child2 = crossover_uniform(parents[0], parents[1], skus, cells)

            # Mutation (Hybrid RSM + PSM)
            child1 = mutation_hybrid(child1, mutation_rate)
            child2 = mutation_hybrid(child2, mutation_rate)

            new_population.append(child1)
            if len(new_population) < pop_size:
                new_population.append(child2)

        population = new_population[:pop_size]

    elapsed = time.time() - start_time

    print(f"\n{'=' * 60}")
    print("FINAL RESULTS")
    print(f"{'=' * 60}")
    print(f"Best Fitness: {best_fitness:.2f}")
    print(f"Time: {elapsed:.2f}s")
    print(f"{'=' * 60}\n")

    return {
        "best_fitness": best_fitness,
        "best_chromosome": best_chromosome,
        "fitness_history": fitness_history,
        "time": elapsed,
    }


# ============================================================================
# RUN TEST
# ============================================================================

if __name__ == "__main__":
    random.seed(42)
    np.random.seed(42)

    print("\n" + "=" * 80)
    print("SINGLE CONFIGURATION TEST: Roulette + Uniform Crossover + Hybrid (RSM+PSM)")
    print("=" * 80)

    # Generate data
    skus, cells = generate_warehouse_data(num_skus=25, num_cells=21)

    max_possible = len(skus) * 100
    print(f"\nScenario: {len(skus)} SKUs, {len(cells)} cells")
    print(f"Max possible fitness (theoretical): {max_possible}")

    # Run GA
    result = run_ga(
        skus=skus,
        cells=cells,
        pop_size=100,
        num_generations=150,
        mutation_rate=0.15,
        elitism=3,
    )

    achievement = (result["best_fitness"] / max_possible) * 100
    print(f"\nAchievement: {achievement:.1f}%")
    print(f"Convergence (last best fitness): {result['fitness_history'][-1]:.2f}")
