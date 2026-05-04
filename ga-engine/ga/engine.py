"""
ga/engine.py — Main GA Loop untuk Warehouse Slotting Optimization.

Alur Genetic Algorithm (Holland, 1975):
    1. Inisialisasi populasi  → Random 50% + Greedy 50%  (Whitley, 1994)
    2. Evaluasi fitness       → FC_CAP + FC_CAT + FC_AFF + FC_SPLIT  (Goldberg, 1989)
    3. [LOOP] Seleksi parent  → Tournament Selection (size=3)  (Miller & Goldberg, 1995)
    4. [LOOP] Crossover       → Uniform Crossover  (Syswerda, 1989)
    5. [LOOP] Mutasi          → Random Reset Mutation  (Michalewicz, 1996)
    6. [LOOP] Elitisme        → pertahankan top-k individu  (De Jong, 1975)
    7. [LOOP] Early stopping  → berhenti jika tidak ada perbaikan k generasi
    8. Kembalikan kromosom terbaik

Referensi:
    Holland, J.H. (1975). Adaptation in Natural and Artificial Systems.
      University of Michigan Press, Ann Arbor.
    Goldberg, D.E. (1989). Genetic Algorithms in Search, Optimization,
      and Machine Learning. Addison-Wesley, Boston.
    Michalewicz, Z. (1996). Genetic Algorithms + Data Structures = Evolution
      Programs. 3rd ed. Springer-Verlag, Berlin.
    De Jong, K.A. (1975). An Analysis of the Behavior of a Class of Genetic
      Adaptive Systems. PhD Thesis, University of Michigan.
    Whitley, D. (1994). A genetic algorithm tutorial.
      Statistics and Computing, 4(2), 65-85.
    Miller, B.L. & Goldberg, D.E. (1995). Genetic algorithms, tournament
      selection, and the effects of noise. Complex Systems, 9(3), 193-212.
    Syswerda, G. (1989). Uniform crossover in genetic algorithms.
      Proceedings of the 3rd ICGA, pp. 2-9.
"""

from __future__ import annotations
import logging
import random
import time
from typing import Dict, List, Tuple

from schemas import CellInput, GARequest, GAResponse, GeneResult

from ga.fitness import build_affinity_map, evaluate_chromosome, AffinityMap
from ga.operators import (
    apply_elitism,
    initialize_population,
    tournament_selection,
    uniform_crossover,
    random_reset_mutation,
)

logger = logging.getLogger(__name__)


class GeneticAlgorithmEngine:
    """
    Engine utama GA untuk warehouse slotting optimization.

    Parameter:
        request  : GARequest dari Laravel (items, cells, affinities, parameters)

    Output:
        GAResponse dengan kromosom terbaik + breakdown fitness per gen.
    """

    def __init__(self, request: GARequest) -> None:
        self.items      = request.items
        self.cells      = request.cells
        self.params     = request.parameters
        self.aff_map: AffinityMap = build_affinity_map(request.affinities)

        self.cells_dict: Dict[int, CellInput] = {
            c.cell_id: c for c in request.cells
        }
        self.cell_ids: List[int] = [c.cell_id for c in request.cells]

        # Seed opsional: jika diisi, hasil GA reproducible untuk eksperimen skripsi.
        # Default None → tiap run menghasilkan variasi berbeda (perilaku operasional normal).
        if self.params.seed is not None:
            random.seed(self.params.seed)

    # ─────────────────────────────────────────────────────────────────────────
    # Public: run()
    # ─────────────────────────────────────────────────────────────────────────

    def run(self) -> GAResponse:
        """
        Jalankan GA dan kembalikan solusi terbaik.
        """
        start_ms = int(time.time() * 1000)
        p        = self.params

        logger.info(
            "[GA] START | inbound items=%d | cells=%d | pop=%d | max_gen=%d",
            len(self.items), len(self.cells), p.population, p.max_generations,
        )

        # ── 1. Inisialisasi Populasi ──────────────────────────────────────────
        population = initialize_population(p.population, self.items, self.cells)

        # ── 2. Evaluasi Fitness Awal ──────────────────────────────────────────
        fitnesses = self._evaluate_all(population)

        best_idx     = self._best_index(fitnesses)
        best_chrom   = population[best_idx].copy()
        best_fitness = fitnesses[best_idx]

        no_improve      = 0
        generations_run = 0

        # ── 3–7. Main Loop ────────────────────────────────────────────────────
        for gen in range(p.max_generations):
            generations_run = gen + 1
            new_pop:  List[List[int]] = []
            new_fits: List[float]     = []

            # Hasilkan individu baru hingga populasi terpenuhi
            while len(new_pop) < p.population:
                # Seleksi: Tournament Selection size=3 (Miller & Goldberg, 1995)
                parent1 = tournament_selection(population, fitnesses, tournament_size=3)
                parent2 = tournament_selection(population, fitnesses, tournament_size=3)

                # Crossover: Uniform Crossover (Syswerda, 1989)
                child1, child2 = uniform_crossover(parent1, parent2, p.crossover_rate)

                # Mutasi: Random Reset Mutation — capacity-aware (Michalewicz, 1996)
                child1 = random_reset_mutation(child1, self.cell_ids, p.mutation_rate, self.items, self.cells_dict)
                child2 = random_reset_mutation(child2, self.cell_ids, p.mutation_rate, self.items, self.cells_dict)

                fit1, _ = evaluate_chromosome(child1, self.items, self.cells_dict, self.aff_map)
                fit2, _ = evaluate_chromosome(child2, self.items, self.cells_dict, self.aff_map)

                new_pop.extend([child1, child2])
                new_fits.extend([fit1, fit2])

            # Pastikan ukuran populasi tidak membengkak
            new_pop  = new_pop[:p.population]
            new_fits = new_fits[:p.population]

            # Elitisme: pertahankan individu terbaik (De Jong, 1975)
            new_pop, new_fits = apply_elitism(
                population, fitnesses,
                new_pop, new_fits,
                p.elitism,
            )

            population = new_pop
            fitnesses  = new_fits

            # Update solusi terbaik
            gen_best_idx = self._best_index(fitnesses)
            gen_best_fit = fitnesses[gen_best_idx]

            if gen_best_fit > best_fitness:
                best_fitness = gen_best_fit
                best_chrom   = population[gen_best_idx].copy()
                no_improve   = 0
                logger.debug("[GA] Gen %d: fitness ↑ %.4f", generations_run, best_fitness)
            else:
                no_improve += 1

            # Early stopping: berhenti jika tidak ada perbaikan dalam p.early_stopping gen
            if no_improve >= p.early_stopping:
                logger.info(
                    "[GA] Early stop at gen %d (no improvement for %d gens)",
                    generations_run, p.early_stopping,
                )
                break

        # ── 8. Build Response ─────────────────────────────────────────────────
        exec_ms = int(time.time() * 1000) - start_ms
        _, gene_details = evaluate_chromosome(
            best_chrom, self.items, self.cells_dict, self.aff_map
        )

        chromosome_result: List[GeneResult] = [
            GeneResult(
                inbound_detail_id=item.inbound_detail_id,
                cell_id          =best_chrom[i],
                quantity         =item.quantity,
                gene_fitness     =gd["gene_fitness"],
                fc_cap           =gd["fc_cap"],
                fc_cat           =gd["fc_cat"],
                fc_aff           =gd["fc_aff"],
                fc_split         =gd["fc_split"],
            )
            for i, (item, gd) in enumerate(zip(self.items, gene_details))
        ]

        logger.info(
            "[GA] DONE | fitness=%.4f | gen=%d | time=%dms",
            best_fitness, generations_run, exec_ms,
        )

        return GAResponse(
            fitness_score     =round(best_fitness, 4),
            generations_run   =generations_run,
            execution_time_ms =exec_ms,
            chromosome        =chromosome_result,
        )

    # ─────────────────────────────────────────────────────────────────────────
    # Private helpers
    # ─────────────────────────────────────────────────────────────────────────

    def _evaluate_all(self, population: List[List[int]]) -> List[float]:
        return [
            evaluate_chromosome(chrom, self.items, self.cells_dict, self.aff_map)[0]
            for chrom in population
        ]

    @staticmethod
    def _best_index(fitnesses: List[float]) -> int:
        return max(range(len(fitnesses)), key=lambda i: fitnesses[i])
