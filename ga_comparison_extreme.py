"""
Extreme Test: 100 SKU Incoming - Roulette vs Tournament
Perbandingan head-to-head dengan logging per generasi
"""

import random
import numpy as np
import matplotlib.pyplot as plt
from typing import List, Tuple, Dict
from dataclasses import dataclass, field
import time

# ============================================================================
# DATA STRUCTURES
# ============================================================================

@dataclass
class SKU:
    """Representasi Stock Keeping Unit (Barang)"""
    id: str
    name: str
    quantity: int
    category: str

    def __repr__(self):
        return f"{self.id}({self.name}, {self.quantity}pcs, {self.category})"

@dataclass
class Cell:
    """Representasi Cell/Lokasi Rak di Gudang"""
    id: str
    location: str
    capacity_max: int
    remaining_capacity: int
    zone_category: str
    stored_skus: List[str] = field(default_factory=list)

    def can_fit(self, quantity: int) -> bool:
        return self.remaining_capacity >= quantity

    def __repr__(self):
        return f"Cell({self.id}, {self.location}, {self.remaining_capacity}/{self.capacity_max}, {self.zone_category})"

# ============================================================================
# EXTREME SCENARIO: 100 SKUs
# ============================================================================

def create_warehouse_extreme() -> List[Cell]:
    """
    Expanded warehouse: 5 rows × 10 columns = 50 cells
    Capacity sangat terbatas untuk 100 SKU
    """
    cells = []
    zones = {
        'A': 'Paint', 'B': 'Paint', 'C': 'Paint',
        'D': 'Thinner', 'E': 'Thinner', 'F': 'Thinner',
        'G': 'Accessories', 'H': 'Accessories',
        'I': 'Mixed', 'J': 'Mixed'
    }

    for row in range(1, 6):  # 5 rows
        for col in ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']:
            cell_id = f"{row}{col}"
            location = f"{row}-{col}"

            # Capacity moderate
            capacity = random.randint(20, 50)

            # Gudang 80-95% penuh
            usage_percent = random.uniform(0.80, 0.95)
            used = int(capacity * usage_percent)
            remaining = capacity - used

            cells.append(Cell(
                id=cell_id,
                location=location,
                capacity_max=capacity,
                remaining_capacity=remaining,
                zone_category=zones[col]
            ))

    return cells

def create_100_skus() -> List[SKU]:
    """Generate 100 SKU incoming dengan distribusi realistis"""
    skus = []

    # Paint: 40 items
    for i in range(1, 41):
        skus.append(SKU(
            id=f"PAINT-{i:03d}",
            name=f"Paint Product {i}",
            quantity=random.randint(3, 12),
            category="Paint"
        ))

    # Thinner: 30 items
    for i in range(1, 31):
        skus.append(SKU(
            id=f"THIN-{i:03d}",
            name=f"Thinner Product {i}",
            quantity=random.randint(4, 10),
            category="Thinner"
        ))

    # Accessories: 30 items
    for i in range(1, 31):
        skus.append(SKU(
            id=f"ACC-{i:03d}",
            name=f"Accessory Product {i}",
            quantity=random.randint(5, 15),
            category="Accessories"
        ))

    return skus

# ============================================================================
# FITNESS FUNCTIONS
# ============================================================================

def calculate_fc_capacity(cell: Cell, sku: SKU) -> float:
    if cell.can_fit(sku.quantity):
        return 40.0
    return 0.0

def calculate_fc_category(cell: Cell, sku: SKU) -> float:
    if cell.zone_category == sku.category:
        return 30.0
    elif cell.zone_category == "Mixed":
        return 15.0
    return 0.0

def calculate_fc_affinity(cell: Cell, sku: SKU, cells: List[Cell]) -> float:
    cell_row = int(cell.id[0])
    cell_col = cell.id[1]

    for other_cell in cells:
        if other_cell.id == cell.id:
            continue

        other_row = int(other_cell.id[0])
        other_col = other_cell.id[1]

        if other_cell.zone_category == sku.category:
            if abs(cell_row - other_row) <= 1 and cell_col == other_col:
                return 20.0
            elif cell_row == other_row and abs(ord(cell_col) - ord(other_col)) <= 1:
                return 20.0
            elif cell_row == other_row:
                return 10.0

    return 0.0

def calculate_fc_split(sku_id: str, chromosome: List[str]) -> float:
    if chromosome.count(sku_id) <= 1:
        return 10.0
    return 0.0

def calculate_gene_fitness(sku: SKU, cell: Cell, cells: List[Cell], chromosome: List[str]) -> float:
    fc_cap = calculate_fc_capacity(cell, sku)
    fc_cat = calculate_fc_category(cell, sku)
    fc_aff = calculate_fc_affinity(cell, sku, cells)
    fc_split = calculate_fc_split(sku.id, chromosome)
    return fc_cap + fc_cat + fc_aff + fc_split

def calculate_chromosome_fitness(chromosome: List[str], skus: List[SKU], cells: List[Cell]) -> float:
    total_fitness = 0.0
    for i, cell_id in enumerate(chromosome):
        sku = skus[i]
        cell = next((c for c in cells if c.id == cell_id), None)
        if cell:
            total_fitness += calculate_gene_fitness(sku, cell, cells, chromosome)
    return total_fitness

# ============================================================================
# GA OPERATORS
# ============================================================================

def create_random_chromosome(skus: List[SKU], cells: List[Cell]) -> List[str]:
    chromosome = []
    for sku in skus:
        valid_cells = [c for c in cells if c.can_fit(sku.quantity)]
        if valid_cells:
            chromosome.append(random.choice(valid_cells).id)
        else:
            chromosome.append(random.choice(cells).id)
    return chromosome

def initialize_population(pop_size: int, skus: List[SKU], cells: List[Cell]) -> List[List[str]]:
    return [create_random_chromosome(skus, cells) for _ in range(pop_size)]

def selection_roulette(population: List[List[str]], fitness_scores: List[float], num_parents: int) -> List[List[str]]:
    total_fitness = sum(fitness_scores)
    if total_fitness == 0:
        return random.sample(population, num_parents)
    probabilities = [f / total_fitness for f in fitness_scores]
    return random.choices(population, weights=probabilities, k=num_parents)

def selection_tournament(population: List[List[str]], fitness_scores: List[float], num_parents: int, tournament_size: int = 3) -> List[List[str]]:
    parents = []
    for _ in range(num_parents):
        tournament_indices = random.sample(range(len(population)), tournament_size)
        tournament_fitness = [fitness_scores[i] for i in tournament_indices]
        winner_idx = tournament_indices[tournament_fitness.index(max(tournament_fitness))]
        parents.append(population[winner_idx].copy())
    return parents

def crossover_uniform(parent1: List[str], parent2: List[str], prob: float = 0.5) -> Tuple[List[str], List[str]]:
    child1, child2 = [], []
    for i in range(len(parent1)):
        if random.random() < prob:
            child1.append(parent2[i])
            child2.append(parent1[i])
        else:
            child1.append(parent1[i])
            child2.append(parent2[i])
    return child1, child2

def mutation_swap(chromosome: List[str], mutation_rate: float, cells: List[Cell]) -> List[str]:
    mutated = chromosome.copy()
    if random.random() < mutation_rate and len(mutated) > 1:
        idx1, idx2 = random.sample(range(len(mutated)), 2)
        mutated[idx1], mutated[idx2] = mutated[idx2], mutated[idx1]
    return mutated

# ============================================================================
# MAIN GA WITH DETAILED LOGGING
# ============================================================================

def run_ga_with_logging(
    skus: List[SKU],
    cells: List[Cell],
    selection_method: str,
    config_name: str,
    pop_size: int = 150,
    num_generations: int = 200,
    mutation_rate: float = 0.15,
    elitism_count: int = 5
) -> Dict:
    """
    GA dengan logging detail per generasi
    """
    print(f"\n{'='*80}")
    print(f"🚀 Running: {config_name}")
    print(f"{'='*80}")

    start_time = time.time()

    # Initialize
    population = initialize_population(pop_size, skus, cells)
    fitness_history = []
    best_solution = None
    best_fitness = -float('inf')

    # Log initial state
    print(f"Generation    Best Fitness    Avg Fitness    Time (s)")
    print(f"{'-'*60}")

    for generation in range(num_generations):
        # Evaluate fitness
        fitness_scores = [calculate_chromosome_fitness(chrom, skus, cells) for chrom in population]

        # Track best
        gen_best_fitness = max(fitness_scores)
        gen_avg_fitness = np.mean(fitness_scores)
        gen_best_idx = fitness_scores.index(gen_best_fitness)

        if gen_best_fitness > best_fitness:
            best_fitness = gen_best_fitness
            best_solution = population[gen_best_idx].copy()

        fitness_history.append(gen_best_fitness)

        # Log setiap 10 generasi atau jika ada improvement
        if generation % 10 == 0 or gen_best_fitness > (fitness_history[-2] if len(fitness_history) > 1 else 0):
            elapsed = time.time() - start_time
            print(f"{generation:5d}        {gen_best_fitness:8.2f}       {gen_avg_fitness:8.2f}      {elapsed:6.2f}")

        # Selection
        num_parents = pop_size - elitism_count
        if selection_method == "roulette":
            parents = selection_roulette(population, fitness_scores, num_parents)
        else:
            parents = selection_tournament(population, fitness_scores, num_parents)

        # Crossover
        offspring = []
        for i in range(0, len(parents) - 1, 2):
            child1, child2 = crossover_uniform(parents[i], parents[i + 1])
            offspring.extend([child1, child2])

        # Mutation
        for i in range(len(offspring)):
            offspring[i] = mutation_swap(offspring[i], mutation_rate, cells)

        # Elitism
        elite_indices = sorted(range(len(fitness_scores)), key=lambda i: fitness_scores[i], reverse=True)[:elitism_count]
        elite = [population[i].copy() for i in elite_indices]

        # New generation
        population = elite + offspring[:pop_size - elitism_count]

    # Final log
    total_time = time.time() - start_time
    final_avg = np.mean([calculate_chromosome_fitness(chrom, skus, cells) for chrom in population])

    print(f"{'-'*60}")
    print(f"✅ Final:    {best_fitness:8.2f}       {final_avg:8.2f}      {total_time:6.2f}")
    print(f"\n📊 Results:")
    print(f"   Best Fitness: {best_fitness:.2f} / {len(skus) * 100:.2f} ({(best_fitness/(len(skus)*100))*100:.1f}%)")
    print(f"   Total Time: {total_time:.2f} seconds")
    print(f"   Generations: {num_generations}")

    return {
        'best_solution': best_solution,
        'best_fitness': best_fitness,
        'fitness_history': fitness_history,
        'config': config_name,
        'time': total_time,
        'avg_final': final_avg
    }

# ============================================================================
# HEAD-TO-HEAD COMPARISON
# ============================================================================

def compare_extreme():
    """
    Perbandingan head-to-head: Roulette vs Tournament
    Dengan 100 SKU incoming
    """
    print("\n" + "="*80)
    print("🔥 EXTREME SCENARIO: 100 SKUs Incoming")
    print("Head-to-Head Comparison: Roulette vs Tournament")
    print("Both using: Uniform Crossover + Swap Mutation")
    print("="*80)

    # Setup
    cells = create_warehouse_extreme()
    skus = create_100_skus()

    print(f"\n📦 Incoming SKUs: {len(skus)} items")
    total_qty = sum(sku.quantity for sku in skus)
    print(f"   Total quantity: {total_qty} units")
    print(f"   Paint: {sum(1 for s in skus if s.category=='Paint')} items")
    print(f"   Thinner: {sum(1 for s in skus if s.category=='Thinner')} items")
    print(f"   Accessories: {sum(1 for s in skus if s.category=='Accessories')} items")

    print(f"\n🏭 Warehouse: {len(cells)} cells")
    total_capacity = sum(c.capacity_max for c in cells)
    total_remaining = sum(c.remaining_capacity for c in cells)
    print(f"   Total capacity: {total_capacity} units")
    print(f"   Remaining: {total_remaining} units")
    print(f"   Usage: {((total_capacity-total_remaining)/total_capacity)*100:.1f}%")
    print(f"   Space shortage: {max(0, total_qty - total_remaining)} units")

    # Use same seed for fair comparison
    random.seed(42)
    np.random.seed(42)

    # Run Roulette
    result_roulette = run_ga_with_logging(
        skus=skus,
        cells=cells,
        selection_method="roulette",
        config_name="ROULETTE + UNIFORM + SWAP",
        pop_size=150,
        num_generations=200,
        mutation_rate=0.15,
        elitism_count=5
    )

    # Reset seed for Tournament
    random.seed(42)
    np.random.seed(42)

    # Run Tournament
    result_tournament = run_ga_with_logging(
        skus=skus,
        cells=cells,
        selection_method="tournament",
        config_name="TOURNAMENT + UNIFORM + SWAP",
        pop_size=150,
        num_generations=200,
        mutation_rate=0.15,
        elitism_count=5
    )

    # Final Comparison
    print("\n" + "="*80)
    print("🏆 FINAL COMPARISON")
    print("="*80)

    print(f"\n{'Config':<30} {'Best Fitness':<15} {'Achievement':<15} {'Time (s)':<10}")
    print(f"{'-'*70}")

    max_possible = len(skus) * 100

    roulette_pct = (result_roulette['best_fitness'] / max_possible) * 100
    print(f"{'Roulette+Uniform+Swap':<30} {result_roulette['best_fitness']:<15.2f} {roulette_pct:<14.1f}% {result_roulette['time']:<10.2f}")

    tournament_pct = (result_tournament['best_fitness'] / max_possible) * 100
    print(f"{'Tournament+Uniform+Swap':<30} {result_tournament['best_fitness']:<15.2f} {tournament_pct:<14.1f}% {result_tournament['time']:<10.2f}")

    print(f"\n{'Difference:':<30} {abs(result_roulette['best_fitness'] - result_tournament['best_fitness']):<15.2f} {abs(roulette_pct - tournament_pct):<14.1f}%")

    # Winner
    if result_roulette['best_fitness'] > result_tournament['best_fitness']:
        winner = "ROULETTE"
        diff = result_roulette['best_fitness'] - result_tournament['best_fitness']
    else:
        winner = "TOURNAMENT"
        diff = result_tournament['best_fitness'] - result_roulette['best_fitness']

    print(f"\n🥇 WINNER: {winner} (+{diff:.2f} points)")

    # Plot comparison
    plot_head_to_head(result_roulette, result_tournament, len(skus))

    return result_roulette, result_tournament

def plot_head_to_head(result_r: Dict, result_t: Dict, num_skus: int):
    """Visualisasi perbandingan head-to-head"""
    fig, axes = plt.subplots(1, 2, figsize=(15, 5))

    # Convergence comparison
    ax1 = axes[0]
    ax1.plot(result_r['fitness_history'], linewidth=2, color='blue', label='Roulette', alpha=0.8)
    ax1.plot(result_t['fitness_history'], linewidth=2, color='red', label='Tournament', alpha=0.8)
    ax1.axhline(y=num_skus * 100, color='green', linestyle='--', label='Maximum Possible', alpha=0.5)
    ax1.set_xlabel('Generation', fontsize=12)
    ax1.set_ylabel('Best Fitness', fontsize=12)
    ax1.set_title('Convergence Comparison (100 SKUs)', fontsize=14, fontweight='bold')
    ax1.legend(fontsize=10)
    ax1.grid(alpha=0.3)

    # Final comparison bar
    ax2 = axes[1]
    configs = ['Roulette\n+Uniform+Swap', 'Tournament\n+Uniform+Swap']
    fitnesses = [result_r['best_fitness'], result_t['best_fitness']]
    colors = ['blue', 'red']

    bars = ax2.bar(configs, fitnesses, color=colors, alpha=0.7, edgecolor='black', linewidth=2)
    ax2.axhline(y=num_skus * 100, color='green', linestyle='--', label='Max Possible', alpha=0.5)
    ax2.set_ylabel('Best Fitness', fontsize=12)
    ax2.set_title('Final Performance (100 SKUs)', fontsize=14, fontweight='bold')
    ax2.legend(fontsize=10)
    ax2.grid(axis='y', alpha=0.3)

    # Add value labels on bars
    for bar, fitness in zip(bars, fitnesses):
        height = bar.get_height()
        ax2.text(bar.get_x() + bar.get_width()/2., height,
                f'{fitness:.0f}\n({(fitness/(num_skus*100))*100:.1f}%)',
                ha='center', va='bottom', fontsize=11, fontweight='bold')

    plt.tight_layout()
    plt.savefig('ga_extreme_comparison.png', dpi=300, bbox_inches='tight')
    print("\n📈 Visualization saved as 'ga_extreme_comparison.png'")
    plt.show()

# ============================================================================
# MAIN
# ============================================================================

if __name__ == "__main__":
    result_r, result_t = compare_extreme()

    print("\n✅ Extreme comparison complete!")
    print(f"📁 Results saved to: ga_extreme_comparison.png")
