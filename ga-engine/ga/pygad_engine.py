"""
ga/pygad_engine.py — GA engine using the PyGAD library.

Chromosome encoding:
    one inbound item line = one gene
    gene value            = selected cell_id  (Direct Value / Integer Encoding)
    gene_space[i]         = ranked feasible cells for item i

Key design decisions vs. vanilla PyGAD:
    1. Greedy + random initial population (Whitley, 1994) — same 50/50 split as
       the custom engine; PyGAD's internal random init is replaced entirely.
    2. Custom uniform crossover + inline category repair (Goldberg, 1989) —
       prevents category-incompatible placements from surviving into selection.
    3. Custom capacity-aware mutation + inline category repair (Michalewicz, 1996)
       — mutation draws only from self.gene_space[i] (pre-filtered feasible pool)
       rather than the full cell list.
    4. Both Python random and numpy random are seeded when a seed is given, so
       results are fully reproducible.

Referensi:
    Whitley, D. (1994). A genetic algorithm tutorial.
        Statistics and Computing, 4(2), 65-85.
    Goldberg, D.E. (1989). Genetic Algorithms in Search, Optimization,
        and Machine Learning. Addison-Wesley, Boston.
    Michalewicz, Z. (1996). Genetic Algorithms + Data Structures = Evolution
        Programs. 3rd ed. Springer-Verlag, Berlin.
    Syswerda, G. (1989). Uniform crossover in genetic algorithms.
        Proceedings of the 3rd ICGA, pp. 2-9.
"""

from __future__ import annotations

import logging
import random
import time
from typing import Dict, List

import numpy as np
import pygad

from schemas import CellInput, GARequest, GAResponse, GeneResult
from ga.fitness import AffinityMap, build_affinity_map, build_item_cell_map, build_item_rack_map, cell_distance, evaluate_chromosome
from ga.operators import category_compatible, feasible_cell_pool

logger = logging.getLogger(__name__)


class PyGadGeneticAlgorithmEngine:
    """
    PyGAD-backed GA engine for warehouse slotting optimisation.

    PyGAD owns the GA loop (tournament selection, elitism, stopping criterion).
    This class owns all domain logic:
      - ranked feasible cell pools per gene (gene_space)
      - greedy + random initial population
      - uniform crossover with category repair
      - capacity-aware random reset mutation with category repair
      - warehouse fitness function (FC_CAP + FC_CAT + FC_AFF + FC_SPLIT + FC_MOV)
      - response shape consumed by Laravel
    """

    def __init__(self, request: GARequest) -> None:
        self.items = request.items
        self.cells = request.cells
        self.params = request.parameters
        self.aff_map: AffinityMap = build_affinity_map(request.affinities)

        self.cells_dict: Dict[int, CellInput] = {c.cell_id: c for c in request.cells}
        self.cell_ids: List[int] = [c.cell_id for c in request.cells]
        self.gene_space: List[List[int]] = []

        # Build once — cells_dict tidak berubah selama GA berjalan
        self._item_rack_map = build_item_rack_map(self.cells_dict)
        self._item_cell_map = build_item_cell_map(self.cells_dict)
        self._candidate_cache = self._build_candidate_cache()
        self.gene_space = self._build_gene_space()

        # Seed both Python stdlib random (used by our custom operators) and
        # numpy random (used internally by PyGAD) for full reproducibility.
        if self.params.seed is not None:
            random.seed(self.params.seed)
            np.random.seed(self.params.seed)

    # ─── Public: run ─────────────────────────────────────────────────────────

    def run(self) -> GAResponse:
        start_ms = int(time.time() * 1000)
        p = self.params

        logger.info(
            "[PyGAD GA] START | inbound items=%d | cells=%d | pop=%d | max_gen=%d",
            len(self.items), len(self.cells), p.population, p.max_generations,
        )

        # Build a greedy + random initial population before handing control to
        # PyGAD. PyGAD infers sol_per_pop and num_genes from the array shape,
        # so sol_per_pop and num_genes are not passed separately.
        initial_population = self._create_initial_population()

        ga_instance = pygad.GA(
            num_generations=p.max_generations,
            # num_parents_mating: half the population participates as parents
            # each generation. Simplification of the original min/min expression
            # which always evaluated to population // 2 for any pop >= 2.
            num_parents_mating=max(2, p.population // 2),
            fitness_func=self._fitness_func,
            initial_population=initial_population,
            gene_type=int,
            parent_selection_type="tournament",
            K_tournament=3,
            # Custom crossover applies uniform swap + repair_category_invalid_genes
            # inline so category-invalid genes never reach fitness evaluation.
            # crossover_probability is handled inside _custom_crossover and is
            # NOT passed as a separate PyGAD param (it would be ignored anyway
            # when crossover_type is a callable).
            crossover_type=self._custom_crossover,
            # Custom mutation draws from self.gene_space[i] (capacity-aware pool)
            # and applies category repair inline. mutation_probability is handled
            # inside _custom_mutation for the same reason.
            mutation_type=self._custom_mutation,
            keep_elitism=max(0, min(p.elitism, p.population)),
            random_seed=p.seed,
            stop_criteria=[f"saturate_{p.early_stopping}"],
            suppress_warnings=True,
        )
        ga_instance.run()

        best_solution, best_fitness, _ = ga_instance.best_solution()
        best_chrom = self._normalize_solution(best_solution)
        exec_ms = int(time.time() * 1000) - start_ms
        _, gene_details = evaluate_chromosome(
            best_chrom,
            self.items,
            self.cells_dict,
            self.aff_map,
            self._item_rack_map,
            self._item_cell_map,
        )

        chromosome_result: List[GeneResult] = [
            GeneResult(
                inbound_detail_id=item.inbound_detail_id,
                cell_id=best_chrom[i],
                quantity=item.quantity,
                gene_fitness=gd["gene_fitness"],
                fc_cap=gd["fc_cap"],
                fc_cat=gd["fc_cat"],
                fc_aff=gd["fc_aff"],
                fc_split=gd["fc_split"],
                fc_mov=gd["fc_mov"],
            )
            for i, (item, gd) in enumerate(zip(self.items, gene_details))
        ]

        generations_run = int(getattr(ga_instance, "generations_completed", p.max_generations))

        logger.info(
            "[PyGAD GA] DONE | fitness=%.4f | gen=%d | time=%dms",
            float(best_fitness),
            generations_run,
            exec_ms,
        )

        return GAResponse(
            fitness_score=round(float(best_fitness), 4),
            generations_run=generations_run,
            execution_time_ms=exec_ms,
            chromosome=chromosome_result,
        )

    # ─── Initialisation ───────────────────────────────────────────────────────

    def _create_initial_population(self) -> np.ndarray:
        """
        Build the initial population: 50% random + 50% greedy (Whitley, 1994).

        All gene values are drawn from self.gene_space[i] so every individual
        is immediately within the feasible candidate pool.

        Random half  — each gene is a uniform draw from gene_space[i].
                       Provides diversity.
        Greedy half  — each gene picks from the top-quarter (by capacity) of
                       gene_space[i], with random tie-breaking for diversity.
                       Injects good initial solutions and accelerates convergence.

        Locked genes (preferred_cell_id != None) are always set to that cell
        regardless of which half they belong to.
        """
        pop_size = self.params.population
        half = pop_size // 2
        population: List[List[int]] = []

        # ── a) Random: uniform draw from each gene's feasible pool ───────────
        for _ in range(half):
            chromosome: List[int] = []
            for i, item in enumerate(self.items):
                if item.preferred_cell_id is not None:
                    chromosome.append(item.preferred_cell_id)
                else:
                    pool = self.gene_space[i]
                    chromosome.append(random.choice(pool) if pool else self.cell_ids[0])
            chromosome = self._repair_category_invalid_genes(chromosome)
            population.append(self._repair_capacity_overflow(chromosome))

        # ── b) Greedy: within each gene's pool, prefer highest capacity ───────
        cap_map: Dict[int, int] = {c.cell_id: c.capacity_remaining for c in self.cells}

        for _ in range(pop_size - half):
            chromosome: List[int] = []
            for i, item in enumerate(self.items):
                if item.preferred_cell_id is not None:
                    chromosome.append(item.preferred_cell_id)
                else:
                    pool = self.gene_space[i]
                    if not pool:
                        chromosome.append(self.cell_ids[0] if self.cell_ids else 0)
                    else:
                        sorted_pool = sorted(
                            pool, key=lambda cid: cap_map.get(cid, 0), reverse=True
                        )
                        # Pick from the top quarter for greedy bias with diversity.
                        top_n = max(1, len(sorted_pool) // 4)
                        chromosome.append(random.choice(sorted_pool[:top_n]))
            chromosome = self._repair_category_invalid_genes(chromosome)
            population.append(self._repair_capacity_overflow(chromosome))

        return np.array(population, dtype=int)

    # ─── Custom GA Operators ──────────────────────────────────────────────────

    def _custom_crossover(
        self,
        parents: np.ndarray,
        offspring_size: tuple,
        ga_instance,
    ) -> np.ndarray:
        """
        Uniform crossover (Syswerda, 1989) with inline category repair.

        For each offspring:
          1. With probability crossover_rate, perform uniform crossover between
             two parents (each gene independently swapped with p=0.5).
          2. Otherwise, copy parent1 directly.
          3. Apply repair_category_invalid_genes so any gene that landed on a
             category-incompatible cell is corrected before fitness evaluation.

        When crossover_type is a callable, PyGAD passes all selected parents
        and calls this function directly without applying crossover_probability
        externally — the rate is therefore handled here.
        """
        offspring = np.empty(offspring_size, dtype=int)
        n_parents = parents.shape[0]

        for k in range(offspring_size[0]):
            parent1 = parents[k % n_parents]
            parent2 = parents[(k + 1) % n_parents]

            if random.random() < self.params.crossover_rate:
                mask = np.random.random(offspring_size[1]) < 0.5
                child = np.where(mask, parent1, parent2)
            else:
                child = parent1.copy()

            chromosome = [int(v) for v in child]
            repaired = self._repair_category_invalid_genes(chromosome)
            repaired = self._repair_capacity_overflow(repaired)
            offspring[k] = np.array(repaired, dtype=int)

        return offspring

    def _custom_mutation(
        self,
        offspring: np.ndarray,
        ga_instance,
    ) -> np.ndarray:
        """
        Capacity-aware random reset mutation (Michalewicz, 1996) with inline
        category repair.

        For each gene in each offspring:
          - Locked gene (preferred_cell_id != None): always reset to that cell.
          - With probability mutation_rate: draw a replacement from
            self.gene_space[i], which is pre-filtered for capacity sufficiency
            and category/continuity affinity. This is the key improvement over
            PyGAD's built-in random mutation which would draw from the entire
            cell pool indiscriminately.
          - After all genes are mutated: apply repair_category_invalid_genes to
            catch any remaining category-invalid assignment.

        When mutation_type is a callable, PyGAD calls this function directly
        without applying mutation_probability externally — the per-gene rate is
        therefore handled here.
        """
        for i_child in range(offspring.shape[0]):
            for i_gene in range(offspring.shape[1]):
                item = self.items[i_gene]

                # Locked gene: enforce preferred_cell_id regardless of mutation roll.
                if item.preferred_cell_id is not None:
                    offspring[i_child, i_gene] = item.preferred_cell_id
                    continue

                if random.random() < self.params.mutation_rate:
                    pool = self.gene_space[i_gene]
                    offspring[i_child, i_gene] = (
                        random.choice(pool) if pool else self.cell_ids[0]
                    )

            # Apply category repair once per child after all genes are mutated.
            chromosome = [int(v) for v in offspring[i_child]]
            repaired = self._repair_category_invalid_genes(chromosome)
            repaired = self._repair_capacity_overflow(repaired)
            offspring[i_child] = np.array(repaired, dtype=int)

        return offspring

    # ─── Candidate Pool Builder ───────────────────────────────────────────────

    def _item_cache_key(self, item) -> tuple:
        return (
            int(item.inbound_detail_id),
            int(item.item_id),
            int(item.quantity),
            int(item.capacity_demand),
            int(item.preferred_cell_id or 0),
        )

    def _build_candidate_cache(self) -> Dict[tuple, Dict[str, List[CellInput]]]:
        """
        Build reusable candidate pools per inbound line.

        Repair and gene-space generation call the same category/capacity checks
        many times during GA. Caching these pools once keeps the GA search logic
        unchanged while avoiding repeated scans over every candidate cell.
        """
        cache: Dict[tuple, Dict[str, List[CellInput]]] = {}

        for item in self.items:
            feasible = [
                cell for cell in self.cells
                if int(cell.capacity_remaining) >= int(item.capacity_demand)
            ]
            anchors = [
                cell for cell in self.cells
                if item.item_id in cell.existing_item_ids
            ]
            same_sku = [
                cell for cell in feasible
                if item.item_id in cell.existing_item_ids
            ]
            exact_category = [
                cell for cell in feasible
                if self._category_tier(item, cell) <= 1
            ]
            neutral = [
                cell for cell in feasible
                if cell.dominant_category_id is None
            ]

            cache[self._item_cache_key(item)] = {
                "feasible": feasible,
                "anchors": anchors,
                "same_sku": self._sort_by_capacity_then_position(same_sku),
                "exact_category": self._sort_by_category_then_capacity(exact_category, item),
                "neutral": self._sort_by_capacity_then_position(neutral),
                "nearby": (
                    self._sort_by_distance_then_capacity(feasible, anchors)
                    if anchors else self._sort_by_category_then_capacity(feasible, item)
                ),
            }

        return cache

    def _repair_category_invalid_genes(self, chromosome: List[int]) -> List[int]:
        """
        Cached category repair.

        This is equivalent to repair_category_invalid_genes in operators.py, but
        it reuses per-item pools instead of rebuilding exact/neutral pools from
        the full cell list for every chromosome.
        """
        repaired = [int(cell_id) for cell_id in chromosome]

        for idx, item in enumerate(self.items):
            if item.preferred_cell_id is not None:
                repaired[idx] = int(item.preferred_cell_id)
                continue

            cache = self._candidate_cache.get(self._item_cache_key(item), {})
            exact_pool = cache.get("exact_category", [])
            neutral_pool = cache.get("neutral", [])
            current_cell = self.cells_dict.get(repaired[idx])

            if (
                current_cell is not None
                and int(current_cell.capacity_remaining) >= int(item.capacity_demand)
                and (
                    self._category_tier(item, current_cell) <= 1
                    or (not exact_pool and current_cell.dominant_category_id is None)
                )
            ):
                continue

            if exact_pool:
                repaired[idx] = int(exact_pool[0].cell_id)
            elif neutral_pool:
                repaired[idx] = int(neutral_pool[0].cell_id)

        return repaired

    def _build_gene_space(self) -> List[List[int]]:
        spaces: List[List[int]] = []

        for item in self.items:
            if item.preferred_cell_id is not None:
                spaces.append([int(item.preferred_cell_id)])
                continue

            pool = self._ranked_cell_pool(item)
            spaces.append([int(cell_id) for cell_id in (pool if pool else self.cell_ids)])

        return spaces

    def _ranked_cell_pool(self, item) -> List[int]:
        """
        Build a compact, warehouse-aware candidate pool for one gene.

        PyGAD is general-purpose, so leaving thousands of feasible cells in
        gene_space makes it easy to pick a far-but-category-valid location.
        The custom GA has repair logic that pushes harder toward continuity.
        This pool gives PyGAD the same operational bias:

        1. exact same SKU cells with capacity,
        2. cells nearest to existing/home area for that SKU,
        3. category-compatible nearby cells,
        4. a small capacity backup set.
        """
        cache = self._candidate_cache.get(self._item_cache_key(item), {})
        feasible = list(cache.get("feasible", []))
        if not feasible:
            return feasible_cell_pool(item, self.cells)

        same_sku_feasible = list(cache.get("same_sku", []))
        if same_sku_feasible:
            return self._unique_cell_ids(
                same_sku_feasible
            )

        anchors = list(cache.get("anchors", []))
        exact_category = list(cache.get("exact_category", []))
        neutral = list(cache.get("neutral", []))

        if anchors:
            # P0: same blok + same grup + same kolom (expand within exact column)
            same_column_pool = self._same_column_expansion_pool(item, feasible, anchors)
            if same_column_pool:
                return self._unique_cell_ids(same_column_pool)

            # Collect anchor coordinates for explicit P2/P3 matching
            anchor_blok_grups = {
                (a.blok, str(a.grup).upper())
                for a in anchors
                if a.blok is not None and a.grup is not None
            }
            anchor_bloks = {
                a.blok for a in anchors if a.blok is not None
            }

            # P2: same blok + same grup — stays in the exact zone as existing stock
            p2 = [
                c for c in exact_category
                if c.blok is not None
                and c.grup is not None
                and (c.blok, str(c.grup).upper()) in anchor_blok_grups
            ]
            if p2:
                return self._unique_cell_ids(
                    self._sort_by_distance_then_capacity(p2, anchors)
                )

            # P3: same blok, any grup — stay in same rack block before crossing blocks
            p3 = [
                c for c in exact_category
                if c.blok is not None and c.blok in anchor_bloks
            ]
            if p3:
                return self._unique_cell_ids(
                    self._sort_by_distance_then_capacity(p3, anchors)
                )

            # P4: same category in other bloks. Category beats distance, but
            # candidates are still ordered by distance from the SKU's anchors.
            if exact_category:
                return self._unique_cell_ids(
                    self._sort_by_distance_then_capacity(exact_category, anchors)[:60]
                )

            # P5: neutral nearby cells. Use these only when no category/same-SKU
            # cell can receive the item, so empty cells become expansion space
            # instead of category-mismatched racks.
            neutral_same_blok = [
                c for c in neutral
                if c.blok is not None and c.blok in anchor_bloks
            ]
            if neutral_same_blok:
                return self._unique_cell_ids(
                    self._sort_by_distance_then_capacity(neutral_same_blok, anchors)[:40]
                )
            if neutral:
                return self._unique_cell_ids(
                    self._sort_by_distance_then_capacity(neutral, anchors)[:40]
                )

            # Last resort: any feasible cell, still nearest-first.
            return self._unique_cell_ids(
                self._sort_by_distance_then_capacity(feasible, anchors)[:30]
            )

        if exact_category:
            return self._unique_cell_ids(
                self._sort_by_category_then_capacity(exact_category, item)[:60]
            )

        if neutral:
            return self._unique_cell_ids(
                self._sort_by_capacity_then_position(neutral)[:40]
            )

        return self._unique_cell_ids(self._sort_by_capacity_then_position(feasible)[:30])

    # ─── Sorting Helpers ──────────────────────────────────────────────────────

    def _repair_capacity_overflow(self, chromosome: List[int]) -> List[int]:
        """
        Reassign non-locked genes when a chromosome overbooks a cell.

        Each gene_space entry is filtered by per-item capacity, but multiple
        genes can still choose the same cell and exceed its remaining capacity
        as a group. This repair keeps the search space closer to a valid
        warehouse assignment before fitness evaluation.
        """
        repaired = [int(cell_id) for cell_id in chromosome]
        remaining: Dict[int, int] = {
            cell.cell_id: int(cell.capacity_remaining)
            for cell in self.cells
        }

        # Preferred cells represent existing/home continuity chosen by Laravel,
        # so reserve them first and only move flexible genes when overflow occurs.
        ordered_gene_indexes = sorted(
            range(len(repaired)),
            key=lambda idx: 0 if self.items[idx].preferred_cell_id is not None else 1,
        )

        for idx in ordered_gene_indexes:
            item = self.items[idx]
            current_cell_id = int(repaired[idx])
            demand = int(item.capacity_demand)

            if remaining.get(current_cell_id, 0) >= demand:
                remaining[current_cell_id] -= demand
                continue

            replacement_id = self._best_capacity_replacement(idx, remaining)
            if replacement_id is not None:
                repaired[idx] = replacement_id
                remaining[replacement_id] -= demand
            else:
                remaining[current_cell_id] = remaining.get(current_cell_id, 0) - demand

        return repaired

    def _best_capacity_replacement(
        self,
        gene_idx: int,
        remaining: Dict[int, int],
    ) -> int | None:
        item = self.items[gene_idx]
        demand = int(item.capacity_demand)
        candidate_ids = list(self.gene_space[gene_idx])

        if item.preferred_cell_id is not None:
            candidate_ids.extend(self._ranked_cell_pool(item))

        seen = set()

        for cell_id in candidate_ids:
            if int(cell_id) in seen:
                continue
            seen.add(int(cell_id))
            cell = self.cells_dict.get(int(cell_id))
            if cell is None:
                continue
            if remaining.get(int(cell_id), 0) < demand:
                continue
            if not category_compatible(item, cell):
                continue

            return int(cell_id)

        return None

    def _sort_by_distance_then_capacity(
        self,
        cells: List[CellInput],
        anchors: List[CellInput],
    ) -> List[CellInput]:
        return sorted(
            cells,
            key=lambda cell: (
                self._nearest_distance(cell, anchors),
                -int(cell.capacity_remaining),
                int(cell.rack_index if cell.rack_index is not None else 9999),
                int(cell.cell_index if cell.cell_index is not None else 9999),
                int(cell.cell_id),
            ),
        )

    def _sort_by_category_then_capacity(
        self,
        cells: List[CellInput],
        item,
    ) -> List[CellInput]:
        return sorted(
            cells,
            key=lambda cell: (
                self._category_tier(item, cell),
                -int(cell.capacity_remaining),
                int(cell.rack_index if cell.rack_index is not None else 9999),
                int(cell.cell_index if cell.cell_index is not None else 9999),
                int(cell.cell_id),
            ),
        )

    def _same_column_expansion_pool(
        self,
        item,
        cells: List[CellInput],
        anchors: List[CellInput],
    ) -> List[CellInput]:
        """
        Prefer the nearest free baris in the same blok-grup-kolom when an SKU's
        exact existing cell is full. This models warehouse expansion more
        naturally than moving to a nearby-but-different column.
        """
        pool: List[CellInput] = []

        for cell in cells:
            if not self._is_category_safe_expansion(item, cell):
                continue

            for anchor in anchors:
                if (
                    cell.blok == anchor.blok
                    and str(cell.grup).upper() == str(anchor.grup).upper()
                    and cell.kolom == anchor.kolom
                    and cell.baris != anchor.baris
                ):
                    pool.append(cell)
                    break

        return sorted(
            pool,
            key=lambda cell: (
                min(
                    abs(int(cell.baris or 0) - int(anchor.baris or 0))
                    for anchor in anchors
                    if (
                        cell.blok == anchor.blok
                        and str(cell.grup).upper() == str(anchor.grup).upper()
                        and cell.kolom == anchor.kolom
                    )
                ),
                0 if cell.dominant_category_id == item.category_id else 1,
                -int(cell.capacity_remaining),
                int(cell.cell_id),
            ),
        )[:20]

    @staticmethod
    def _is_category_safe_expansion(item, cell: CellInput) -> bool:
        return (
            cell.dominant_category_id is None
            or item.category_id is None
            or cell.dominant_category_id == item.category_id
            or item.item_id in cell.existing_item_ids
        )

    @staticmethod
    def _category_tier(item, cell: CellInput) -> int:
        if item.item_id in cell.existing_item_ids:
            return 0
        if (
            item.category_id is not None
            and cell.dominant_category_id is not None
            and item.category_id == cell.dominant_category_id
        ):
            return 1
        if cell.dominant_category_id is None:
            return 2
        return 3

    @staticmethod
    def _sort_by_capacity_then_position(cells: List[CellInput]) -> List[CellInput]:
        return sorted(
            cells,
            key=lambda cell: (
                -int(cell.capacity_remaining),
                int(cell.rack_index if cell.rack_index is not None else 9999),
                int(cell.cell_index if cell.cell_index is not None else 9999),
                int(cell.cell_id),
            ),
        )

    @staticmethod
    def _nearest_distance(cell: CellInput, anchors: List[CellInput]) -> float:
        if not anchors:
            return 9999.0

        return min(cell_distance(cell, anchor) for anchor in anchors)

    @staticmethod
    def _unique_cell_ids(cells: List[CellInput]) -> List[int]:
        seen = set()
        ids: List[int] = []

        for cell in cells:
            if cell.cell_id in seen:
                continue

            seen.add(cell.cell_id)
            ids.append(int(cell.cell_id))

        return ids

    # ─── Fitness + Normalisation ──────────────────────────────────────────────

    def _fitness_func(self, ga_instance, solution, solution_idx) -> float:
        chromosome = self._normalize_solution(solution)
        score, _ = evaluate_chromosome(
            chromosome,
            self.items,
            self.cells_dict,
            self.aff_map,
            self._item_rack_map,
            self._item_cell_map,
        )
        return float(score)

    def _normalize_solution(self, solution) -> List[int]:
        """
        Safety net: snap any gene whose cell_id is not in cells_dict at all.

        Intentionally does NOT snap genes that are outside gene_space[i] —
        repair_category_invalid_genes may legitimately place a gene at a cell
        that is feasible + category-compatible but not in the ranked pool.
        Enforcing gene_space membership here would silently undo that repair.
        """
        chromosome: List[int] = []

        for idx, value in enumerate(solution):
            cell_id = int(value)

            if cell_id not in self.cells_dict:
                valid_pool = self.gene_space[idx] if idx < len(self.gene_space) else self.cell_ids
                cell_id = int(valid_pool[0]) if valid_pool else (self.cell_ids[0] if self.cell_ids else 0)

            chromosome.append(cell_id)

        chromosome = self._repair_category_invalid_genes(chromosome)
        return self._repair_capacity_overflow(chromosome)
