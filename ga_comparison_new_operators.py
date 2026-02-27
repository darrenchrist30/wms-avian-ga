"""
GA EXTREME COMPARISON: OLD vs NEW Operators
============================================
Compare literature-based operators (AEX, OX, RSM, RSM+PSM)
vs old operators (Uniform, Swap) in 100 SKU extreme scenario

HYPOTHESIS:
New operators (AEX/OX + RSM/RSM+PSM) should outperform
old operators (Uniform + Swap) based on literature
"""

import random
import numpy as np
import matplotlib.pyplot as plt
from dataclasses import dataclass
from typing import List, Tuple, Dict
from datetime import datetime

# Import dari file utama
import sys
sys.path.insert(0, '.')
from ga_warehouse_optimization import (
    SKU, Cell,
    calculate_gene_fitness, calculate_chromosome_fitness,
    create_random_chromosome, initialize_population,
    selection_roulette, selection_tournament,
    crossover_uniform, crossover_aex, crossover_ox,
    mutation_swap, mutation_rsm, mutation_rsm_psm_hybrid
)

# Seed untuk reproducibility
random.seed(42)
np.random.seed(42)

def create_100_skus() -> List[SKU]:
    """Generate 100 SKUs dengan distribusi realistic"""
    skus = []

    # Paint: 40 items
    for i in range(1, 41):
        skus.append(SKU(
            id=f"03SP-PAINT{i:03d}",
            name=f"Paint Product {i}",
            category="Paint",
            quantity=random.randint(3, 12)
        ))

    # Thinner: 30 items
    for i in range(1, 31):
        skus.append(SKU(
            id=f"03SP-THIN{i:03d}",
            name=f"Thinner Product {i}",
            category="Thinner",
            quantity=random.randint(4, 10)
        ))

    # Accessories: 30 items
    for i in range(1, 31):
        skus.append(SKU(
            id=f"03SP-ACC{i:03d}",
            name=f"Accessory Product {i}",
            category="Accessories",
            quantity=random.randint(2, 15)
        ))

    return skus

def create_50_cells() -> List[Cell]:
    """Generate 50 cells dengan capacity tight"""
    cells = []

    # Paint Zone: 18 cells
    for i in range(1, 7):
        for col in ['A', 'B', 'C']:
            cap = random.randint(15, 30)
            usage = random.randint(5, 15)
            cells.append(Cell(
                id=f"{i}{col}",
                location=f"{i}-{col}",
                zone_category="Paint",
                capacity_max=cap,
                remaining_capacity=cap - usage
            ))

    # Thinner Zone: 15 cells
    for i in range(1, 6):
        for col in ['D', 'E', 'F']:
            cap = random.randint(12, 25)
            usage = random.randint(4, 10)
            cells.append(Cell(
                id=f"{i}{col}",
                location=f"{i}-{col}",
                zone_category="Thinner",
                capacity_max=cap,
                remaining_capacity=cap - usage
            ))

    # Accessories Zone: 12 cells
    for i in range(1, 5):
        for col in ['G', 'H', 'I']:
            cap = random.randint(18, 35)
            usage = random.randint(6, 18)
            cells.append(Cell(
                id=f"{i}{col}",
                location=f"{i}-{col}",
                zone_category="Accessories",
                capacity_max=cap,
                remaining_capacity=cap - usage
            ))

    # Mixed Zone: 5 cells
    for i in range(1, 6):
        cap = random.randint(20, 40)
        usage = random.randint(8, 20)
        cells.append(Cell(
            id=f"{i}M",
            location=f"{i}-Mixed",
            zone_category="Mixed",
            capacity_max=cap,
            remaining_capacity=cap - usage
        ))

    return cells

def run_ga_with_logging(
    skus: List[SKU],
    cells: List[Cell],
    selection_method: str,
    crossover_method: str,
    mutation_method: str,
    config_name: str
) -> Dict:
    """Run GA with detailed logging"""

    print(f"\n{'='*70}")
    print(f"⚙️  CONFIGURATION: {config_name}")
    print(f"{'='*70}")
    print(f"   Selection:  {selection_method}")
    print(f"   Crossover:  {crossover_method}")
    print(f"   Mutation:   {mutation_method}")
    print(f"\n   Running 200 generations...")

    pop_size = 150
    num_generations = 200
    mutation_rate = 0.15
    elitism_count = 5

    # Initialize
    population = initialize_population(pop_size, skus, cells)
    fitness_history = []
    best_solution = None
    best_fitness = -float('inf')

    start_time = datetime.now()

    for generation in range(num_generations):
        # Evaluate fitness
        fitness_scores = [calculate_chromosome_fitness(chrom, skus, cells) for chrom in population]

        # Track best
        gen_best_fitness = max(fitness_scores)
        gen_best_idx = fitness_scores.index(gen_best_fitness)

        if gen_best_fitness > best_fitness:
            best_fitness = gen_best_fitness
            best_solution = population[gen_best_idx].copy()

        fitness_history.append(gen_best_fitness)

        # Log every 20 generations
        if (generation + 1) % 20 == 0 or generation == 0:
            print(f"   Gen {generation+1:3d}: Best Fitness = {gen_best_fitness:7.2f} / 10000.00")

        # Selection
        num_parents = pop_size - elitism_count
        if selection_method == "roulette":
            parents = selection_roulette(population, fitness_scores, num_parents)
        else:
            parents = selection_tournament(population, fitness_scores, num_parents)

        # Crossover
        offspring = []
        for i in range(0, len(parents) - 1, 2):
            parent1 = parents[i]
            parent2 = parents[i + 1]

            if crossover_method == "uniform":
                child1, child2 = crossover_uniform(parent1, parent2)
            elif crossover_method == "aex":
                child1, child2 = crossover_aex(parent1, parent2, skus, cells)
            else:  # ox
                child1, child2 = crossover_ox(parent1, parent2)

            offspring.extend([child1, child2])

        # Mutation
        for i in range(len(offspring)):
            if mutation_method == "swap":
                offspring[i] = mutation_swap(offspring[i], mutation_rate, cells)
            elif mutation_method == "rsm":
                offspring[i] = mutation_rsm(offspring[i], mutation_rate, cells, skus)
            else:  # rsm_psm
                offspring[i] = mutation_rsm_psm_hybrid(offspring[i], mutation_rate, cells, skus)

        # Elitism
        elite_indices = sorted(range(len(fitness_scores)), key=lambda i: fitness_scores[i], reverse=True)[:elitism_count]
        elites = [population[i].copy() for i in elite_indices]

        # New population
        population = elites + offspring[:pop_size - elitism_count]

    elapsed = (datetime.now() - start_time).total_seconds()

    print(f"\n   ✅ FINAL: Best Fitness = {best_fitness:.2f} / 10000.00")
    print(f"   ⏱️  Time: {elapsed:.2f} seconds")
    print(f"   📊 Achievement: {(best_fitness / 10000) * 100:.2f}%")

    return {
        "config": config_name,
        "best_solution": best_solution,
        "best_fitness": best_fitness,
        "fitness_history": fitness_history,
        "time": elapsed
    }

def plot_comparison(results: List[Dict], skus: List[SKU]):
    """Create visualization comparing all configurations"""

    fig, axes = plt.subplots(2, 2, figsize=(16, 12))
    fig.suptitle('GA COMPARISON: OLD vs NEW Operators (100 SKUs Extreme)',
                 fontsize=16, fontweight='bold')

    # Define colors
    colors = {
        'OLD_Roulette': '#FF6B6B',
        'OLD_Tournament': '#4ECDC4',
        'NEW_Roulette_AEX': '#95E1D3',
        'NEW_Roulette_OX': '#A8E6CF',
        'NEW_Tournament_AEX': '#FFD93D',
        'NEW_Tournament_OX': '#FFA07A'
    }

    # Plot 1: Convergence curves
    ax1 = axes[0, 0]
    for result in results:
        config = result['config']
        color = colors.get(config, '#999999')
        ax1.plot(result['fitness_history'], label=config, linewidth=2, color=color, alpha=0.8)

    ax1.set_xlabel('Generation', fontsize=11)
    ax1.set_ylabel('Best Fitness', fontsize=11)
    ax1.set_title('Convergence Comparison', fontsize=12, fontweight='bold')
    ax1.legend(fontsize=9, loc='lower right')
    ax1.grid(True, alpha=0.3)
    ax1.axhline(y=10000, color='red', linestyle='--', alpha=0.3, label='Max Possible')

    # Plot 2: Final fitness comparison
    ax2 = axes[0, 1]
    configs = [r['config'] for r in results]
    final_fitness = [r['best_fitness'] for r in results]
    bars_colors = [colors.get(c, '#999999') for c in configs]

    bars = ax2.barh(configs, final_fitness, color=bars_colors, alpha=0.8)
    ax2.set_xlabel('Final Best Fitness', fontsize=11)
    ax2.set_title('Final Performance Comparison', fontsize=12, fontweight='bold')
    ax2.axvline(x=10000, color='red', linestyle='--', alpha=0.3)

    # Add value labels
    for i, (bar, val) in enumerate(zip(bars, final_fitness)):
        ax2.text(val + 100, i, f'{val:.0f}', va='center', fontsize=9)

    ax2.grid(axis='x', alpha=0.3)

    # Plot 3: Convergence speed (generations to 95% of final)
    ax3 = axes[1, 0]
    conv_speeds = []
    for result in results:
        final_fit = result['best_fitness']
        target = final_fit * 0.95
        history = result['fitness_history']

        # Find generation where target reached
        gen_to_target = next((i for i, f in enumerate(history) if f >= target), len(history))
        conv_speeds.append(gen_to_target)

    bars = ax3.barh(configs, conv_speeds, color=bars_colors, alpha=0.8)
    ax3.set_xlabel('Generations to 95% Final Fitness', fontsize=11)
    ax3.set_title('Convergence Speed', fontsize=12, fontweight='bold')
    ax3.invert_xaxis()  # Lower is better

    # Add value labels
    for i, (bar, val) in enumerate(zip(bars, conv_speeds)):
        ax3.text(val - 5, i, f'{val}', va='center', ha='right', fontsize=9)

    ax3.grid(axis='x', alpha=0.3)

    # Plot 4: Summary table
    ax4 = axes[1, 1]
    ax4.axis('off')

    # Prepare data
    table_data = []
    table_data.append(['Configuration', 'Final Fitness', 'Achievement', 'Conv. Speed', 'Time (s)'])

    for result in results:
        config = result['config']
        fitness = result['best_fitness']
        achievement = f"{(fitness / 10000) * 100:.2f}%"

        # Convergence speed
        final_fit = result['best_fitness']
        target = final_fit * 0.95
        history = result['fitness_history']
        conv_gen = next((i for i, f in enumerate(history) if f >= target), len(history))

        time_taken = f"{result['time']:.1f}"

        table_data.append([
            config.replace('_', '\n'),
            f"{fitness:.0f}",
            achievement,
            f"Gen {conv_gen}",
            time_taken
        ])

    table = ax4.table(cellText=table_data, cellLoc='center', loc='center',
                     colWidths=[0.3, 0.15, 0.15, 0.15, 0.1])

    table.auto_set_font_size(False)
    table.set_fontsize(9)
    table.scale(1, 2.5)

    # Style header row
    for i in range(5):
        table[(0, i)].set_facecolor('#0D8564')
        table[(0, i)].set_text_props(weight='bold', color='white')

    # Alternate row colors
    for i in range(1, len(table_data)):
        for j in range(5):
            if i % 2 == 0:
                table[(i, j)].set_facecolor('#F0F0F0')

    plt.tight_layout()
    plt.savefig('ga_new_operators_comparison.png', dpi=300, bbox_inches='tight')
    print(f"\n📊 Visualization saved as 'ga_new_operators_comparison.png'")

def main():
    """Main execution"""

    print("\n" + "="*80)
    print("GA EXTREME COMPARISON: OLD vs NEW Operators")
    print("100 SKUs, 50 Cells, Tight Constraints")
    print("="*80)

    # Generate data
    skus = create_100_skus()
    cells = create_50_cells()

    total_qty = sum(s.quantity for s in skus)
    total_capacity = sum(c.capacity_max for c in cells)
    total_current = sum(c.capacity_max - c.remaining_capacity for c in cells)
    remaining = sum(c.remaining_capacity for c in cells)

    print(f"\n📦 Incoming SKUs: {len(skus)} items")
    print(f"   Total quantity: {total_qty} units")
    print(f"   Paint: {len([s for s in skus if s.category == 'Paint'])}")
    print(f"   Thinner: {len([s for s in skus if s.category == 'Thinner'])}")
    print(f"   Accessories: {len([s for s in skus if s.category == 'Accessories'])}")

    print(f"\n🏭 Warehouse: {len(cells)} cells")
    print(f"   Total capacity: {total_capacity} units")
    print(f"   Current usage: {total_current} units ({(total_current/total_capacity)*100:.1f}%)")
    print(f"   Remaining: {remaining} units")
    print(f"   Space needed: {total_qty} units")
    print(f"   Constraint: {'TIGHT' if total_qty > remaining * 0.8 else 'LOOSE'}")

    # Test configurations
    configs = [
        # OLD Methods
        ("roulette", "uniform", "swap", "OLD_Roulette"),
        ("tournament", "uniform", "swap", "OLD_Tournament"),

        # NEW Methods - Roulette
        ("roulette", "aex", "rsm", "NEW_Roulette_AEX"),
        ("roulette", "ox", "rsm_psm", "NEW_Roulette_OX"),

        # NEW Methods - Tournament
        ("tournament", "aex", "rsm", "NEW_Tournament_AEX"),
        ("tournament", "ox", "rsm_psm", "NEW_Tournament_OX"),
    ]

    results = []

    for selection, crossover, mutation, name in configs:
        result = run_ga_with_logging(skus, cells, selection, crossover, mutation, name)
        results.append(result)

    # Summary
    print("\n" + "="*80)
    print("📊 FINAL SUMMARY")
    print("="*80)

    # Sort by fitness
    sorted_results = sorted(results, key=lambda r: r['best_fitness'], reverse=True)

    print("\n🏆 Ranking:")
    for i, result in enumerate(sorted_results, 1):
        fitness_pct = (result['best_fitness'] / 10000) * 100
        print(f"{i}. {result['config']:25s} → {result['best_fitness']:7.2f} ({fitness_pct:5.2f}%) in {result['time']:.1f}s")

    # Winner analysis
    winner = sorted_results[0]
    print(f"\n🥇 WINNER: {winner['config']}")
    print(f"   Final Fitness: {winner['best_fitness']:.2f} / 10000.00")
    print(f"   Achievement: {(winner['best_fitness'] / 10000) * 100:.2f}%")
    print(f"   Time: {winner['time']:.2f} seconds")

    # Compare OLD vs NEW
    old_best = max([r for r in results if 'OLD' in r['config']], key=lambda r: r['best_fitness'])
    new_best = max([r for r in results if 'NEW' in r['config']], key=lambda r: r['best_fitness'])

    improvement = new_best['best_fitness'] - old_best['best_fitness']
    improvement_pct = (improvement / old_best['best_fitness']) * 100

    print(f"\n📈 OLD vs NEW Comparison:")
    print(f"   Best OLD method: {old_best['config']:20s} → {old_best['best_fitness']:.2f}")
    print(f"   Best NEW method: {new_best['config']:20s} → {new_best['best_fitness']:.2f}")
    print(f"   Improvement: {improvement:+.2f} points ({improvement_pct:+.2f}%)")

    if improvement > 0:
        print(f"\n   ✅ NEW operators are BETTER by {improvement:.0f} points!")
    elif improvement < 0:
        print(f"\n   ❌ OLD operators are BETTER by {abs(improvement):.0f} points!")
    else:
        print(f"\n   🤝 Both perform EQUALLY")

    # Create visualization
    plot_comparison(results, skus)

    print(f"\n✅ Analysis complete!")

if __name__ == "__main__":
    main()
