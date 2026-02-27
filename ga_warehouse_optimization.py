"""
Genetic Algorithm for Warehouse Storage Location Assignment Problem (SLAP)
Berdasarkan penelitian Storage Location Assignment dengan pendekatan GA

Tujuan: Membandingkan kombinasi operator GA terbaik untuk optimasi penempatan barang di gudang
- Seleksi: Roulette Wheel vs Tournament
- Crossover: One-Point vs Two-Point vs Uniform
- Mutasi: Swap vs Random
"""

import random
import numpy as np
import matplotlib.pyplot as plt
from typing import List, Tuple, Dict
from dataclasses import dataclass, field
from copy import deepcopy

# ============================================================================
# DATA STRUCTURES
# ============================================================================

@dataclass
class SKU:
    """Representasi Stock Keeping Unit (Barang)"""
    id: str
    name: str
    quantity: int
    category: str  # Paint, Thinner, Accessories, dll

    def __repr__(self):
        return f"{self.id}({self.name}, {self.quantity}pcs, {self.category})"

@dataclass
class Cell:
    """Representasi Cell/Lokasi Rak di Gudang"""
    id: str
    location: str  # misal: "1-B", "3-F", dll
    capacity_max: int
    remaining_capacity: int
    zone_category: str  # Paint, Thinner, Mixed, dll
    stored_skus: List[str] = field(default_factory=list)  # List SKU ID yang ada di cell ini

    def can_fit(self, quantity: int) -> bool:
        """Cek apakah cell bisa menampung quantity"""
        return self.remaining_capacity >= quantity

    def add_sku(self, sku_id: str, quantity: int):
        """Tambah SKU ke cell"""
        if self.can_fit(quantity):
            self.remaining_capacity -= quantity
            if sku_id not in self.stored_skus:
                self.stored_skus.append(sku_id)
            return True
        return False

    def __repr__(self):
        return f"Cell({self.id}, {self.location}, {self.remaining_capacity}/{self.capacity_max}, {self.zone_category})"

# ============================================================================
# SKENARIO DATA: 10 Barang Incoming + Gudang Existing
# ============================================================================

def create_initial_warehouse() -> List[Cell]:
    """
    Buat gudang existing dengan beberapa cell yang sudah ada isinya
    Layout: 3 Baris (1-3), 7 Kolom (A-G) = 21 cells

    CHALLENGING SCENARIO:
    - Capacity lebih kecil dan terbatas
    - Banyak cell sudah hampir penuh (70-90% used)
    - Tidak semua zona punya space cukup
    """
    cells = []
    zones = {
        'A': 'Paint', 'B': 'Paint', 'C': 'Thinner',
        'D': 'Thinner', 'E': 'Accessories',
        'F': 'Mixed', 'G': 'Mixed'
    }

    for row in range(1, 4):  # Row 1-3
        for col in ['A', 'B', 'C', 'D', 'E', 'F', 'G']:
            cell_id = f"{row}{col}"
            location = f"{row}-{col}"

            # Capacity lebih kecil dan bervariasi
            capacity = random.randint(15, 35)

            # Simulasi gudang HAMPIR PENUH (70-95% used)
            # Ini bikin constraint lebih ketat
            usage_percent = random.uniform(0.70, 0.95)
            used = int(capacity * usage_percent)
            remaining = capacity - used

            cells.append(Cell(
                id=cell_id,
                location=location,
                capacity_max=capacity,
                remaining_capacity=remaining,
                zone_category=zones[col],
                stored_skus=[]  # Untuk simplifikasi, existing SKU tidak detail
            ))

    return cells

def create_incoming_skus() -> List[SKU]:
    """
    Buat 25 SKU incoming (lebih banyak untuk challenging scenario)
    Mix dari berbagai kategori dengan quantity bervariasi
    """
    incoming = [
        # Paint items (10 items)
        SKU("03SP-AIRCOM400", "AIR COMBINATION 400", 5, "Paint"),
        SKU("03SP-PAINT001", "AVIAN GOLD PRIMER", 8, "Paint"),
        SKU("03SP-PAINT002", "AVIAN SILVER TOPCOAT", 6, "Paint"),
        SKU("03SP-PAINT003", "CLEAR COAT", 5, "Paint"),
        SKU("03SP-PAINT004", "RED OXIDE PRIMER", 7, "Paint"),
        SKU("03SP-PAINT005", "WHITE ENAMEL", 9, "Paint"),
        SKU("03SP-PAINT006", "BLACK GLOSS", 4, "Paint"),
        SKU("03SP-PAINT007", "BLUE METALLIC", 6, "Paint"),
        SKU("03SP-PAINT008", "GREEN MATT", 5, "Paint"),
        SKU("03SP-PAINT009", "YELLOW SAFETY", 8, "Paint"),

        # Thinner items (8 items)
        SKU("03SP-THIN001", "THINNER A", 10, "Thinner"),
        SKU("03SP-THIN002", "THINNER B", 7, "Thinner"),
        SKU("03SP-THIN003", "SPECIAL THINNER", 9, "Thinner"),
        SKU("03SP-THIN004", "LACQUER THINNER", 6, "Thinner"),
        SKU("03SP-THIN005", "EPOXY THINNER", 8, "Thinner"),
        SKU("03SP-THIN006", "NC THINNER", 5, "Thinner"),
        SKU("03SP-THIN007", "PU THINNER", 7, "Thinner"),
        SKU("03SP-THIN008", "ACRYLIC THINNER", 6, "Thinner"),

        # Accessories items (7 items)
        SKU("03SP-TLEMF", "LEM FOX", 4, "Accessories"),
        SKU("03SP-BALLVA2", "BALL VALVE DIA 2", 3, "Accessories"),
        SKU("03SP-ACC001", "BRUSH SET", 12, "Accessories"),
        SKU("03SP-ACC002", "ROLLER KIT", 8, "Accessories"),
        SKU("03SP-ACC003", "SPRAY GUN", 5, "Accessories"),
        SKU("03SP-ACC004", "MASKING TAPE", 15, "Accessories"),
        SKU("03SP-ACC005", "SANDPAPER PACK", 10, "Accessories"),
    ]
    return incoming

# ============================================================================
# FITNESS FUNCTION COMPONENTS
# ============================================================================

def calculate_fc_capacity(cell: Cell, sku: SKU) -> float:
    """
    FC_CAP: Fitness Cost Kapasitas (Prioritas 1)
    - 40 poin jika kapasitas mencukupi
    - 0 poin jika tidak mencukupi
    """
    if cell.can_fit(sku.quantity):
        return 40.0
    return 0.0

def calculate_fc_category(cell: Cell, sku: SKU) -> float:
    """
    FC_CAT: Fitness Cost Kategori (Prioritas 2)
    - 30 poin jika kategori sesuai zona
    - 15 poin jika zona Mixed
    - 0 poin jika tidak sesuai
    """
    if cell.zone_category == sku.category:
        return 30.0
    elif cell.zone_category == "Mixed":
        return 15.0
    return 0.0

def calculate_fc_affinity(cell: Cell, sku: SKU, cells: List[Cell]) -> float:
    """
    FC_AFF: Fitness Cost Affinity (Prioritas 3)
    - 20 poin jika ada barang se-kategori di cell yang berdekatan
    - 10 poin jika ada barang se-kategori di row yang sama
    - 0 poin jika tidak ada

    Definisi "berdekatan": cell dengan row dan kolom yang adjacent
    """
    # Parse cell location (format: "1-A")
    cell_row = int(cell.id[0])
    cell_col = cell.id[1]

    # Cek cell tetangga (atas, bawah, kiri, kanan)
    adjacent_score = 0
    same_row_score = 0

    for other_cell in cells:
        if other_cell.id == cell.id:
            continue

        other_row = int(other_cell.id[0])
        other_col = other_cell.id[1]

        # Cek apakah ada SKU dengan kategori sama
        has_same_category = other_cell.zone_category == sku.category

        if has_same_category:
            # Adjacent: row atau col beda 1
            if abs(cell_row - other_row) <= 1 and cell_col == other_col:
                adjacent_score = 20.0
                break
            elif cell_row == other_row and abs(ord(cell_col) - ord(other_col)) <= 1:
                adjacent_score = 20.0
                break
            elif cell_row == other_row:
                same_row_score = 10.0

    return max(adjacent_score, same_row_score)

def calculate_fc_split(sku_id: str, cells: List[Cell], chromosome: List[str]) -> float:
    """
    FC_SPLIT: Fitness Cost Split Location (Prioritas 4)
    - 10 poin jika SKU hanya di satu lokasi
    - 0 poin jika SKU tersebar di beberapa lokasi

    Dalam konteks satu generasi, kita cek apakah SKU yang sama
    muncul di lebih dari satu cell dalam kromosom ini
    """
    # Hitung berapa kali SKU ini muncul di chromosome
    count = chromosome.count(sku_id)

    if count <= 1:
        return 10.0
    return 0.0

def calculate_gene_fitness(sku: SKU, cell: Cell, cells: List[Cell], chromosome: List[str]) -> float:
    """
    Calculate total fitness untuk satu gene (SKU-Cell assignment)
    Max: 100 poin (40 + 30 + 20 + 10)
    """
    fc_cap = calculate_fc_capacity(cell, sku)
    fc_cat = calculate_fc_category(cell, sku)
    fc_aff = calculate_fc_affinity(cell, sku, cells)
    fc_split = calculate_fc_split(sku.id, cells, chromosome)

    return fc_cap + fc_cat + fc_aff + fc_split

def calculate_chromosome_fitness(chromosome: List[str], skus: List[SKU], cells: List[Cell]) -> float:
    """
    Calculate total fitness untuk satu kromosom (solusi lengkap)

    chromosome: array berisi cell_id untuk setiap SKU
    chromosome[i] = cell_id yang dipilih untuk skus[i]
    """
    total_fitness = 0.0

    for i, cell_id in enumerate(chromosome):
        sku = skus[i]
        cell = next((c for c in cells if c.id == cell_id), None)

        if cell:
            gene_fitness = calculate_gene_fitness(sku, cell, cells, chromosome)
            total_fitness += gene_fitness

    return total_fitness

# ============================================================================
# GENETIC ALGORITHM: REPRESENTASI KROMOSOM
# ============================================================================

def create_random_chromosome(skus: List[SKU], cells: List[Cell]) -> List[str]:
    """
    Buat kromosom random: X = [c1, c2, ..., cn]
    Dimana ci adalah cell_id yang dipilih untuk SKU ke-i
    """
    chromosome = []
    for sku in skus:
        # Filter cells yang bisa menampung (kapasitas cukup)
        valid_cells = [c for c in cells if c.can_fit(sku.quantity)]

        if valid_cells:
            # Pilih random dari valid cells
            selected_cell = random.choice(valid_cells)
            chromosome.append(selected_cell.id)
        else:
            # Jika tidak ada cell yang cukup, pilih random dari semua
            chromosome.append(random.choice(cells).id)

    return chromosome

def initialize_population(pop_size: int, skus: List[SKU], cells: List[Cell]) -> List[List[str]]:
    """Inisialisasi populasi dengan kromosom random"""
    return [create_random_chromosome(skus, cells) for _ in range(pop_size)]

# ============================================================================
# GENETIC OPERATORS: SELECTION
# ============================================================================

def selection_roulette(population: List[List[str]], fitness_scores: List[float], num_parents: int) -> List[List[str]]:
    """
    Roulette Wheel Selection
    Probabilitas terpilih sebanding dengan fitness
    """
    total_fitness = sum(fitness_scores)

    if total_fitness == 0:
        # Jika semua fitness 0, pilih random
        return random.sample(population, num_parents)

    probabilities = [f / total_fitness for f in fitness_scores]

    parents = random.choices(population, weights=probabilities, k=num_parents)
    return parents

def selection_tournament(population: List[List[str]], fitness_scores: List[float], num_parents: int, tournament_size: int = 3) -> List[List[str]]:
    """
    Tournament Selection
    Pilih random beberapa individu, ambil yang terbaik
    """
    parents = []

    for _ in range(num_parents):
        # Pilih random tournament_size individu
        tournament_indices = random.sample(range(len(population)), tournament_size)
        tournament_fitness = [fitness_scores[i] for i in tournament_indices]

        # Pilih yang fitness tertinggi
        winner_idx = tournament_indices[tournament_fitness.index(max(tournament_fitness))]
        parents.append(population[winner_idx].copy())

    return parents

# ============================================================================
# GENETIC OPERATORS: CROSSOVER
# ============================================================================

def crossover_one_point(parent1: List[str], parent2: List[str]) -> Tuple[List[str], List[str]]:
    """
    One-Point Crossover
    Pilih satu titik potong, tukar bagian setelah titik potong
    """
    if len(parent1) <= 1:
        return parent1.copy(), parent2.copy()

    point = random.randint(1, len(parent1) - 1)

    child1 = parent1[:point] + parent2[point:]
    child2 = parent2[:point] + parent1[point:]

    return child1, child2

def crossover_two_point(parent1: List[str], parent2: List[str]) -> Tuple[List[str], List[str]]:
    """
    Two-Point Crossover
    Pilih dua titik potong, tukar bagian tengah
    """
    if len(parent1) <= 2:
        return parent1.copy(), parent2.copy()

    point1 = random.randint(1, len(parent1) - 1)
    point2 = random.randint(1, len(parent1) - 1)

    if point1 > point2:
        point1, point2 = point2, point1

    child1 = parent1[:point1] + parent2[point1:point2] + parent1[point2:]
    child2 = parent2[:point1] + parent1[point1:point2] + parent2[point2:]

    return child1, child2

def crossover_uniform(parent1: List[str], parent2: List[str], prob: float = 0.5) -> Tuple[List[str], List[str]]:
    """
    Uniform Crossover
    Setiap gene punya probabilitas prob untuk swap
    """
    child1 = []
    child2 = []

    for i in range(len(parent1)):
        if random.random() < prob:
            child1.append(parent2[i])
            child2.append(parent1[i])
        else:
            child1.append(parent1[i])
            child2.append(parent2[i])

    return child1, child2

def crossover_aex(parent1: List[str], parent2: List[str], skus: List[SKU], cells: List[Cell]) -> Tuple[List[str], List[str]]:
    """
    AEX - Alternating Edges Crossover (Kordos et al. 2020)
    Implementation dari Kordos et al. (2020), Section 4.3, page 6-7
    
    "AEX creates the child from two parents by starting from the value,
    which is at the first position in the first parent. Then it adds
    this value, from the second parent, which in the second parent
    follows the value just taken from the first parent. Then again
    a value from the first parent that follows the value just selected
    from the second parent and so on."
    
    Args:
        parent1, parent2: Chromosomes (list of cell IDs)
        skus, cells: Not used, kept for API compatibility
    
    Returns:
        child1, child2: Two new chromosomes
    """
    n = len(parent1)
    
    # Validate
    if n == 0 or len(parent2) != n:
        return parent1.copy(), parent2.copy()
    
    # Create child 1
    child1 = []
    used1 = set()
    
    # Step 1: Start from first position of parent1
    current_value = parent1[0]
    child1.append(current_value)
    used1.add(current_value)
    
    # Alternating flag
    use_parent2 = True  # Next we look in parent2
    
    # Step 2: Build child alternating between parents
    while len(child1) < n:
        
        # Select which parent to look in
        if use_parent2:
            parent = parent2
        else:
            parent = parent1
        
        # Validate parent length
        if len(parent) != n:
            unused = [v for v in parent1 if v not in used1]
            if unused:
                child1.append(random.choice(unused))
                used1.add(child1[-1])
            use_parent2 = not use_parent2
            continue
        
        # Find current value in parent
        try:
            idx = parent.index(current_value)
            
            # Get next value (circular)
            next_idx = (idx + 1) % n
            next_value = parent[next_idx]
            
            # If already used, find next unused value in this parent
            attempts = 0
            while next_value in used1 and attempts < n:
                next_idx = (next_idx + 1) % n
                next_value = parent[next_idx]
                attempts += 1
            
            if next_value not in used1:
                child1.append(next_value)
                used1.add(next_value)
                current_value = next_value
            else:
                # Conflict: select random unused value
                unused = [v for v in parent1 if v not in used1]
                if unused:
                    random_value = random.choice(unused)
                    child1.append(random_value)
                    used1.add(random_value)
                    current_value = random_value
                else:
                    break
                    
        except ValueError:
            # Current value not in parent (shouldn't happen)
            # Select random unused
            unused = [v for v in parent1 if v not in used1]
            if unused:
                random_value = random.choice(unused)
                child1.append(random_value)
                used1.add(random_value)
                current_value = random_value
            else:
                break
        
        # Alternate to other parent
        use_parent2 = not use_parent2
    
    # Create child 2 (swap parents)
    child2 = []
    used2 = set()
    
    current_value = parent2[0]
    child2.append(current_value)
    used2.add(current_value)
    
    use_parent1 = True
    
    while len(child2) < n:
        
        if use_parent1:
            parent = parent1
        else:
            parent = parent2
        
        if len(parent) != n:
            unused = [v for v in parent2 if v not in used2]
            if unused:
                child2.append(random.choice(unused))
                used2.add(child2[-1])
            use_parent1 = not use_parent1
            continue
        
        try:
            idx = parent.index(current_value)
            
            next_idx = (idx + 1) % n
            next_value = parent[next_idx]
            
            attempts = 0
            while next_value in used2 and attempts < n:
                next_idx = (next_idx + 1) % n
                next_value = parent[next_idx]
                attempts += 1
            
            if next_value not in used2:
                child2.append(next_value)
                used2.add(next_value)
                current_value = next_value
            else:
                unused = [v for v in parent2 if v not in used2]
                if unused:
                    random_value = random.choice(unused)
                    child2.append(random_value)
                    used2.add(random_value)
                    current_value = random_value
                else:
                    break
                    
        except ValueError:
            unused = [v for v in parent2 if v not in used2]
            if unused:
                random_value = random.choice(unused)
                child2.append(random_value)
                used2.add(random_value)
                current_value = random_value
            else:
                break
        
        use_parent1 = not use_parent1
    
    return child1, child2

def crossover_ox(parent1: List[str], parent2: List[str]) -> Tuple[List[str], List[str]]:
    """
    OX - Order Crossover (Hwang et al. 2002)
    Standard untuk permutation-based problems

    Good for SLAP karena menjaga struktur assignment
    """
    size = len(parent1)
    if size <= 2:
        return parent1.copy(), parent2.copy()

    # Select two random cut points
    point1 = random.randint(0, size - 2)
    point2 = random.randint(point1 + 1, size)

    # Initialize children
    child1 = [None] * size
    child2 = [None] * size

    # Copy substring from parents
    child1[point1:point2] = parent1[point1:point2]
    child2[point1:point2] = parent2[point1:point2]

    # Fill remaining positions (allow duplicates for SLAP)
    # For SLAP, duplicate cell assignments are valid
    for i in range(size):
        if child1[i] is None:
            child1[i] = parent2[i]
        if child2[i] is None:
            child2[i] = parent1[i]

    return child1, child2

# ============================================================================
# GENETIC OPERATORS: MUTATION
# ============================================================================

def mutation_swap(chromosome: List[str], mutation_rate: float, cells: List[Cell]) -> List[str]:
    """
    Swap Mutation
    Pilih dua gene random dan tukar nilainya
    """
    mutated = chromosome.copy()

    if random.random() < mutation_rate and len(mutated) > 1:
        idx1, idx2 = random.sample(range(len(mutated)), 2)
        mutated[idx1], mutated[idx2] = mutated[idx2], mutated[idx1]

    return mutated

def mutation_random(chromosome: List[str], mutation_rate: float, cells: List[Cell]) -> List[str]:
    """
    Random Mutation
    Pilih gene random dan ganti dengan cell_id random
    """
    mutated = chromosome.copy()

    for i in range(len(mutated)):
        if random.random() < mutation_rate:
            mutated[i] = random.choice(cells).id

    return mutated

def mutation_rsm(chromosome: List[str], mutation_rate: float, cells: List[Cell], skus: List[SKU]) -> List[str]:
    """
    RSM - Reverse Sequence Mutation (Kordos et al. 2020, Otman et al. 2012)
    CORRECT implementation: Reverse a random segment
    
    "Select random segment and reverse it"
    - Kordos et al. 2020, Section 5.2
    
    Better than swap - preserves adjacent relationships
    """
    mutated = chromosome.copy()
    
    if random.random() < mutation_rate:
        n = len(mutated)
        if n < 2:
            return mutated
        
        # Select two cut points
        point1, point2 = sorted(random.sample(range(n), 2))
        
        # Reverse segment between points
        mutated[point1:point2+1] = list(reversed(mutated[point1:point2+1]))
    
    return mutated

def mutation_rsm_psm_hybrid(chromosome: List[str], mutation_rate: float, cells: List[Cell], skus: List[SKU]) -> List[str]:
    """
    RSM + PSM Hybrid (Kordos et al. 2020)
    
    "We use two different mutation operators—Reverse Sequence Mutation (RSM)
    and Partial Shuffle Mutation (PSM) with the probability of applying RSM
    being three times higher."
    - Kordos et al. 2020, Section 5.2
    
    Ratio: RSM 75% : PSM 25%
    """
    if random.random() > mutation_rate:
        return chromosome  # No mutation
    
    # Choose operator: RSM 3x more likely (75% vs 25%)
    if random.random() < 0.75:
        # RSM: Reverse Sequence Mutation
        return mutation_rsm(chromosome, 1.0, cells, skus)  # Force mutation
    else:
        # PSM: Partial Shuffle Mutation
        return mutation_psm(chromosome, 1.0, cells, skus)  # Force mutation

def mutation_psm(chromosome: List[str], mutation_rate: float, cells: List[Cell], skus: List[SKU]) -> List[str]:
    """
    PSM - Partial Shuffle Mutation (Kordos et al. 2020)
    
    "Select random segment and shuffle it"
    - Kordos et al. 2020, Section 5.2
    """
    mutated = chromosome.copy()
    
    if random.random() < mutation_rate:
        n = len(mutated)
        if n < 2:
            return mutated
        
        # Select two cut points
        point1, point2 = sorted(random.sample(range(n), 2))
        
        # Extract segment
        segment = mutated[point1:point2+1]
        
        # Shuffle segment
        random.shuffle(segment)
        
        # Put back
        mutated[point1:point2+1] = segment
    
    return mutated

def calculate_dynamic_mutation_rate(generation: int, iter_no_improvement: int, 
                                   fitness: float, max_fitness: float,
                                   c_i: float = 0.00001, c_n: float = 0.00001, 
                                   c_f: float = 0.3) -> float:
    """
    Dynamic Mutation Probability (Kordos et al. 2020, Equation 4)
    
    mutationProb(i) = (c_i * √iter + c_n * iter_NBI) * (F_max / F_i + c_f)
    
    "We use dynamic mutation probability... which increases gradually
    during the optimization. Also the probability of mutation is higher
    for the individuals with lower fitness."
    - Kordos et al. 2020, page 14
    """
    import math
    
    prob = (c_i * math.sqrt(generation + 1) + c_n * iter_no_improvement)
    
    if fitness > 0:
        prob *= (max_fitness / fitness + c_f)
    else:
        prob *= (1 + c_f)
    
    # Clamp to reasonable range [0.01, 0.5]
    return min(max(prob, 0.01), 0.5)# ============================================================================
# MAIN GENETIC ALGORITHM
# ============================================================================

def run_ga(
    skus: List[SKU],
    cells: List[Cell],
    selection_method: str = "roulette",  # "roulette" or "tournament"
    crossover_method: str = "one_point",  # "one_point", "two_point", "uniform", "aex", "ox"
    mutation_method: str = "swap",  # "swap", "random", "rsm", "rsm_psm"
    pop_size: int = 50,
    num_generations: int = 100,
    mutation_rate: float = 0.1,
    elitism_count: int = 2,
    early_stopping: int = 20,  # No Better Iteration (NBI_PP)
    use_dynamic_mutation: bool = False  # Use Kordos 2020 dynamic mutation
) -> Dict:
    """
    Main GA Loop

    Returns:
        Dict dengan best_solution, best_fitness, fitness_history
    """
    # Initialize
    population = initialize_population(pop_size, skus, cells)
    fitness_history = []
    best_solution = None
    best_fitness = -float('inf')
    iter_no_improvement = 0  # For early stopping & dynamic mutation

    for generation in range(num_generations):
        # Evaluate fitness
        fitness_scores = [calculate_chromosome_fitness(chrom, skus, cells) for chrom in population]

        # Track best
        gen_best_fitness = max(fitness_scores)
        gen_best_idx = fitness_scores.index(gen_best_fitness)

        if gen_best_fitness > best_fitness:
            best_fitness = gen_best_fitness
            best_solution = population[gen_best_idx].copy()
            iter_no_improvement = 0
        else:
            iter_no_improvement += 1
        
        # Early stopping (Kordos 2020)
        if iter_no_improvement >= early_stopping:
            print(f"Early stopping at generation {generation} (no improvement for {early_stopping} iterations)")
            break

        fitness_history.append(gen_best_fitness)

        # Selection
        num_parents = pop_size - elitism_count
        if selection_method == "roulette":
            parents = selection_roulette(population, fitness_scores, num_parents)
        else:  # tournament
            parents = selection_tournament(population, fitness_scores, num_parents)

        # Crossover
        offspring = []
        for i in range(0, len(parents) - 1, 2):
            parent1 = parents[i]
            parent2 = parents[i + 1]

            if crossover_method == "one_point":
                child1, child2 = crossover_one_point(parent1, parent2)
            elif crossover_method == "two_point":
                child1, child2 = crossover_two_point(parent1, parent2)
            elif crossover_method == "uniform":
                child1, child2 = crossover_uniform(parent1, parent2)
            elif crossover_method == "aex":
                child1, child2 = crossover_aex(parent1, parent2, skus, cells)
            else:  # ox
                child1, child2 = crossover_ox(parent1, parent2)

            offspring.extend([child1, child2])

        # Mutation
        max_fitness = max(fitness_scores)
        for i in range(len(offspring)):
            # Calculate dynamic mutation rate if enabled (Kordos 2020)
            if use_dynamic_mutation:
                chromo_fitness = calculate_chromosome_fitness(offspring[i], skus, cells)
                current_mut_rate = calculate_dynamic_mutation_rate(
                    generation, iter_no_improvement, chromo_fitness, max_fitness
                )
            else:
                current_mut_rate = mutation_rate
            
            # Apply mutation
            if mutation_method == "swap":
                offspring[i] = mutation_swap(offspring[i], current_mut_rate, cells)
            elif mutation_method == "random":
                offspring[i] = mutation_random(offspring[i], current_mut_rate, cells)
            elif mutation_method == "rsm":
                offspring[i] = mutation_rsm(offspring[i], current_mut_rate, cells, skus)
            else:  # rsm_psm
                offspring[i] = mutation_rsm_psm_hybrid(offspring[i], current_mut_rate, cells, skus)

        # Elitism: keep best individuals
        elite_indices = sorted(range(len(fitness_scores)), key=lambda i: fitness_scores[i], reverse=True)[:elitism_count]
        elite = [population[i].copy() for i in elite_indices]

        # New generation
        population = elite + offspring[:pop_size - elitism_count]

    return {
        'best_solution': best_solution,
        'best_fitness': best_fitness,
        'fitness_history': fitness_history,
        'config': f"{selection_method}+{crossover_method}+{mutation_method}"
    }

# ============================================================================
# COMPARISON & VISUALIZATION
# ============================================================================

def compare_ga_configurations():
    """
    Jalankan GA dengan berbagai kombinasi operator dan bandingkan hasilnya
    """
    print("=" * 80)
    print("GENETIC ALGORITHM - WAREHOUSE STORAGE OPTIMIZATION")
    print("Comparing Selection, Crossover, and Mutation Operators")
    print("CHALLENGING SCENARIO: Limited capacity, 25 SKUs, tight constraints")
    print("=" * 80)

    # Setup data
    cells = create_initial_warehouse()
    skus = create_incoming_skus()

    print(f"\n📦 Incoming SKUs: {len(skus)} items")
    total_qty = sum(sku.quantity for sku in skus)
    print(f"   Total quantity: {total_qty} units")
    print(f"   Categories: Paint({sum(1 for s in skus if s.category=='Paint')}), "
          f"Thinner({sum(1 for s in skus if s.category=='Thinner')}), "
          f"Accessories({sum(1 for s in skus if s.category=='Accessories')})")

    print(f"\n🏭 Warehouse Cells: {len(cells)} locations")
    total_capacity = sum(c.capacity_max for c in cells)
    total_remaining = sum(c.remaining_capacity for c in cells)
    usage_percent = ((total_capacity - total_remaining) / total_capacity) * 100
    print(f"   Total capacity: {total_capacity} units")
    print(f"   Remaining capacity: {total_remaining} units")
    print(f"   Current usage: {usage_percent:.1f}%")
    print(f"   Space needed for incoming: {total_qty} units")
    print(f"   Constraint tightness: {'TIGHT - Space limited!' if total_qty > total_remaining * 0.7 else 'Moderate'}")

    # Configurations to test
    configs = [
        # OLD Methods (for comparison)
        ("roulette", "uniform", "swap"),
        ("tournament", "uniform", "swap"),

        # NEW Methods (Literature-based)
        ("roulette", "aex", "rsm"),
        ("roulette", "aex", "rsm_psm"),
        ("roulette", "ox", "rsm"),
        ("roulette", "ox", "rsm_psm"),
        ("tournament", "aex", "rsm"),
        ("tournament", "aex", "rsm_psm"),
        ("tournament", "ox", "rsm"),
        ("tournament", "ox", "rsm_psm"),
    ]

    results = []

    print("\n🔬 Running GA with different configurations...")
    print("   (This may take a moment with 25 SKUs and tight constraints)\n")

    for selection, crossover, mutation in configs:
        config_name = f"{selection}+{crossover}+{mutation}"
        print(f"Testing: {config_name}...", end=" ")

        result = run_ga(
            skus=skus,
            cells=cells,
            selection_method=selection,
            crossover_method=crossover,
            mutation_method=mutation,
            pop_size=100,  # Increased for harder problem
            num_generations=150,  # More generations for convergence
            mutation_rate=0.15,  # Slightly higher mutation
            elitism_count=3
        )

        results.append(result)
        print(f"✓ Best Fitness: {result['best_fitness']:.2f}")

    # Find best configuration
    best_result = max(results, key=lambda r: r['best_fitness'])

    print("\n" + "=" * 80)
    print("📊 RESULTS SUMMARY")
    print("=" * 80)

    # Sort by fitness
    sorted_results = sorted(results, key=lambda r: r['best_fitness'], reverse=True)

    print("\n🏆 Ranking of Configurations:")
    for i, result in enumerate(sorted_results, 1):
        print(f"{i:2d}. {result['config']:30s} → Fitness: {result['best_fitness']:7.2f}")

    print(f"\n🥇 BEST CONFIGURATION: {best_result['config']}")
    print(f"   Best Fitness: {best_result['best_fitness']:.2f}")
    print(f"   Max Possible: {len(skus) * 100:.2f} (100 points per SKU)")
    print(f"   Achievement: {(best_result['best_fitness'] / (len(skus) * 100)) * 100:.1f}%")

    # Show best solution
    print(f"\n📍 Best Storage Assignment:")
    for i, cell_id in enumerate(best_result['best_solution']):
        sku = skus[i]
        cell = next(c for c in cells if c.id == cell_id)
        fitness = calculate_gene_fitness(sku, cell, cells, best_result['best_solution'])
        print(f"  {sku.id:20s} ({sku.category:12s}, {sku.quantity:2d}u) → "
              f"Cell {cell.id} ({cell.location}, {cell.zone_category:12s}, "
              f"{cell.remaining_capacity:2d}/{cell.capacity_max:2d}) [Fitness: {fitness:.0f}]")

    # Visualization
    plot_comparison(sorted_results, best_result)

    return sorted_results, best_result

def plot_comparison(results: List[Dict], best_result: Dict):
    """
    Visualisasi perbandingan hasil GA
    """
    fig, axes = plt.subplots(2, 2, figsize=(15, 10))

    # 1. Bar chart: Best fitness per configuration
    configs = [r['config'] for r in results]
    fitnesses = [r['best_fitness'] for r in results]

    ax1 = axes[0, 0]
    bars = ax1.barh(range(len(configs)), fitnesses, color='steelblue')
    bars[0].set_color('gold')  # Highlight best
    ax1.set_yticks(range(len(configs)))
    ax1.set_yticklabels(configs, fontsize=8)
    ax1.set_xlabel('Best Fitness')
    ax1.set_title('GA Configuration Comparison')
    ax1.grid(axis='x', alpha=0.3)

    # 2. Convergence plot: Best configuration
    ax2 = axes[0, 1]
    ax2.plot(best_result['fitness_history'], linewidth=2, color='darkgreen')
    ax2.set_xlabel('Generation')
    ax2.set_ylabel('Best Fitness')
    ax2.set_title(f'Convergence: {best_result["config"]}')
    ax2.grid(alpha=0.3)

    # 3. Comparison by selection method
    ax3 = axes[1, 0]
    roulette_avg = np.mean([r['best_fitness'] for r in results if 'roulette' in r['config']])
    tournament_avg = np.mean([r['best_fitness'] for r in results if 'tournament' in r['config']])
    ax3.bar(['Roulette', 'Tournament'], [roulette_avg, tournament_avg], color=['skyblue', 'salmon'])
    ax3.set_ylabel('Average Best Fitness')
    ax3.set_title('Selection Method Comparison')
    ax3.grid(axis='y', alpha=0.3)

    # 4. Comparison by crossover method
    ax4 = axes[1, 1]
    one_point_avg = np.mean([r['best_fitness'] for r in results if 'one_point' in r['config']])
    two_point_avg = np.mean([r['best_fitness'] for r in results if 'two_point' in r['config']])
    uniform_avg = np.mean([r['best_fitness'] for r in results if 'uniform' in r['config']])
    ax4.bar(['One-Point', 'Two-Point', 'Uniform'], [one_point_avg, two_point_avg, uniform_avg],
            color=['lightcoral', 'lightgreen', 'lightyellow'])
    ax4.set_ylabel('Average Best Fitness')
    ax4.set_title('Crossover Method Comparison')
    ax4.grid(axis='y', alpha=0.3)

    plt.tight_layout()
    plt.savefig('ga_comparison_results.png', dpi=300, bbox_inches='tight')
    print("\n📈 Visualization saved as 'ga_comparison_results.png'")
    plt.show()

# ============================================================================
# MAIN EXECUTION
# ============================================================================

if __name__ == "__main__":
    random.seed(42)  # For reproducibility
    np.random.seed(42)

    results, best = compare_ga_configurations()

    print("\n✅ Analysis complete!")
    print(f"🎯 Recommendation: Use {best['config']} for optimal warehouse storage assignment")
