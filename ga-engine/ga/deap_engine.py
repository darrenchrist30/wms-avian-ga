"""
ga/deap_engine.py — Alternative GA engine using the DEAP library.

This engine is intentionally optional. The existing custom engine remains the
default, while this implementation lets the WMS compare a library-backed GA
against the custom implementation using the same payload and fitness function.
"""

from __future__ import annotations

import logging
import random
import time
from typing import Dict, List, Tuple

from deap import base, creator, tools

from schemas import CellInput, GARequest, GAResponse, GeneResult
from ga.fitness import AffinityMap, build_affinity_map, evaluate_chromosome
from ga.operators import feasible_cell_pool, repair_category_invalid_genes

logger = logging.getLogger(__name__)


class DeapGeneticAlgorithmEngine:
    """
    DEAP-backed GA engine.

    DEAP handles population containers, selection, crossover, mutation
    orchestration, and best-individual utilities. The domain-specific parts
    stay owned by this project:
      - candidate cell pools
      - capacity/category repair
      - warehouse fitness function
      - response shape
    """

    def __init__(self, request: GARequest) -> None:
        self.items = request.items
        self.cells = request.cells
        self.params = request.parameters
        self.aff_map: AffinityMap = build_affinity_map(request.affinities)

        self.cells_dict: Dict[int, CellInput] = {c.cell_id: c for c in request.cells}
        self.cell_ids: List[int] = [c.cell_id for c in request.cells]
        self.feasible_pools: List[List[int]] = [
            feasible_cell_pool(item, self.cells) for item in self.items
        ]

        if self.params.seed is not None:
            random.seed(self.params.seed)

        self._ensure_deap_types()

    def run(self) -> GAResponse:
        start_ms = int(time.time() * 1000)
        p = self.params
        elite_count = max(0, min(int(p.elitism), int(p.population)))

        logger.info(
            "[DEAP GA] START | inbound items=%d | cells=%d | pop=%d | max_gen=%d",
            len(self.items), len(self.cells), p.population, p.max_generations,
        )

        toolbox = self._build_toolbox()
        population = toolbox.population(n=p.population)

        self._evaluate_invalid(population, toolbox)
        best_individual = tools.selBest(population, 1)[0]
        best_chrom = list(best_individual)
        best_fitness = float(best_individual.fitness.values[0])
        no_improve = 0
        generations_run = 0

        for gen in range(p.max_generations):
            generations_run = gen + 1

            elites = [toolbox.clone(ind) for ind in tools.selBest(population, elite_count)]
            offspring_count = max(0, p.population - elite_count)
            offspring = toolbox.select(population, offspring_count)
            offspring = [toolbox.clone(ind) for ind in offspring]

            for child1, child2 in zip(offspring[::2], offspring[1::2]):
                if random.random() < p.crossover_rate:
                    toolbox.mate(child1, child2)
                    del child1.fitness.values
                    del child2.fitness.values

            for child in offspring:
                toolbox.mutate(child)
                del child.fitness.values

            population = elites + offspring
            self._evaluate_invalid(population, toolbox)

            gen_best = tools.selBest(population, 1)[0]
            gen_best_fit = float(gen_best.fitness.values[0])

            if gen_best_fit > best_fitness:
                best_fitness = gen_best_fit
                best_chrom = list(gen_best)
                no_improve = 0
                logger.debug("[DEAP GA] Gen %d: fitness naik %.4f", generations_run, best_fitness)
            else:
                no_improve += 1

            if no_improve >= p.early_stopping:
                logger.info(
                    "[DEAP GA] Early stop at gen %d (no improvement for %d gens)",
                    generations_run,
                    p.early_stopping,
                )
                break

        exec_ms = int(time.time() * 1000) - start_ms
        _, gene_details = evaluate_chromosome(
            best_chrom,
            self.items,
            self.cells_dict,
            self.aff_map,
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
            )
            for i, (item, gd) in enumerate(zip(self.items, gene_details))
        ]

        logger.info(
            "[DEAP GA] DONE | fitness=%.4f | gen=%d | time=%dms",
            best_fitness,
            generations_run,
            exec_ms,
        )

        return GAResponse(
            fitness_score=round(best_fitness, 4),
            generations_run=generations_run,
            execution_time_ms=exec_ms,
            chromosome=chromosome_result,
        )

    def _build_toolbox(self) -> base.Toolbox:
        toolbox = base.Toolbox()
        toolbox.register("individual", self._create_individual)
        toolbox.register("population", tools.initRepeat, list, toolbox.individual)
        toolbox.register("evaluate", self._evaluate)
        toolbox.register("select", tools.selTournament, tournsize=3)
        toolbox.register("mate", tools.cxUniform, indpb=0.5)
        toolbox.register("mutate", self._mutate)
        return toolbox

    def _create_individual(self):
        genes = []

        for idx, item in enumerate(self.items):
            if item.preferred_cell_id is not None:
                genes.append(item.preferred_cell_id)
                continue

            pool = self.feasible_pools[idx] if idx < len(self.feasible_pools) else self.cell_ids
            genes.append(random.choice(pool if pool else self.cell_ids))

        repaired = repair_category_invalid_genes(genes, self.items, self.cells_dict)
        return creator.WmsIndividual(repaired)

    def _evaluate(self, individual) -> Tuple[float]:
        chromosome = repair_category_invalid_genes(list(individual), self.items, self.cells_dict)
        individual[:] = chromosome
        score, _ = evaluate_chromosome(chromosome, self.items, self.cells_dict, self.aff_map)
        return (score,)

    def _mutate(self, individual):
        for idx, item in enumerate(self.items):
            if item.preferred_cell_id is not None:
                individual[idx] = item.preferred_cell_id
                continue

            if random.random() < self.params.mutation_rate:
                pool = self.feasible_pools[idx] if idx < len(self.feasible_pools) else self.cell_ids
                individual[idx] = random.choice(pool if pool else self.cell_ids)

        repaired = repair_category_invalid_genes(list(individual), self.items, self.cells_dict)
        individual[:] = repaired
        return (individual,)

    @staticmethod
    def _evaluate_invalid(population, toolbox: base.Toolbox) -> None:
        invalid = [ind for ind in population if not ind.fitness.valid]
        for individual, fitness in zip(invalid, map(toolbox.evaluate, invalid)):
            individual.fitness.values = fitness

    @staticmethod
    def _ensure_deap_types() -> None:
        if not hasattr(creator, "WmsFitnessMax"):
            creator.create("WmsFitnessMax", base.Fitness, weights=(1.0,))

        if not hasattr(creator, "WmsIndividual"):
            creator.create("WmsIndividual", list, fitness=creator.WmsFitnessMax)
