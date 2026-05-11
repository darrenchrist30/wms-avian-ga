"""
schemas.py — Pydantic request/response models untuk GA Engine API.
"""

from __future__ import annotations
from typing import List, Optional
from pydantic import BaseModel, Field


# ─────────────────────────────────────────────────────────────────────────────
# REQUEST
# ─────────────────────────────────────────────────────────────────────────────

class ItemInput(BaseModel):
    inbound_detail_id: int
    item_id:           int
    sku:               str
    category_id:       Optional[int]   = None
    quantity:          int             = Field(..., gt=0)
    item_size:         str             = "medium"
    movement_type:     str             = "slow_moving"

    # Jika terisi, gene ini dikunci ke cell tertentu.
    # Dipakai untuk partial allocation: isi existing cell terlebih dahulu.
    preferred_cell_id: Optional[int] = None


class CellInput(BaseModel):
    cell_id:              int
    zone_category:        Optional[str] = None   # kode zona: "A", "B", "C"
    rack_code:            Optional[str] = None   # kode rack fisik, contoh: "19"
    rack_index:           Optional[int] = None   # nomor urut rack (integer dari rack code)
    cell_code:            Optional[str] = None   # kode cell lengkap, contoh: "1-F"
    cell_index:           Optional[int] = None   # nomor urut posisi cell dalam rack (A=1, B=2, ...)
    dominant_category_id: Optional[int] = None   # FK ke item_categories
    capacity_remaining:   int
    capacity_max:         int
    status:               str
    existing_item_ids:    List[int]      = []    # item_id yang sudah ada di cell ini


class AffinityInput(BaseModel):
    item_id:         int
    related_item_id: int
    affinity_score:  float = Field(..., ge=0.0, le=1.0)


class GAParameters(BaseModel):
    population:      int   = 100
    max_generations: int   = 150
    mutation_rate:   float = 0.15
    crossover_rate:  float = 0.80
    elitism:         int   = 3
    early_stopping:  int   = 20
    seed:            Optional[int] = None  # None = random tiap run; set integer untuk reproducibility


class GARequest(BaseModel):
    inbound_order_id: int
    items:            List[ItemInput]
    cells:            List[CellInput]
    affinities:       List[AffinityInput] = []
    parameters:       GAParameters        = GAParameters()


# ─────────────────────────────────────────────────────────────────────────────
# RESPONSE
# ─────────────────────────────────────────────────────────────────────────────

class GeneResult(BaseModel):
    inbound_detail_id: int
    cell_id:           int
    quantity:          int
    gene_fitness:      float   # total per-gen (maks 100)
    fc_cap:            float   # komponen kapasitas (maks 40)
    fc_cat:            float   # komponen kategori/zona (maks 30)
    fc_aff:            float   # komponen afinitas (maks 20)
    fc_split:          float   # komponen anti-split (maks 10)


class GAResponse(BaseModel):
    fitness_score:     float          # rata-rata fitness kromosom terbaik (0-100)
    generations_run:   int
    execution_time_ms: int
    chromosome:        List[GeneResult]
