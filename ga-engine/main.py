"""
main.py — FastAPI entry point untuk WMS GA Engine.

Cara menjalankan:
    pip install -r requirements.txt
    uvicorn main:app --host 0.0.0.0 --port 8001 --reload

Endpoint:
    GET  /           → health check
    POST /ga/run     → jalankan Genetic Algorithm, kembalikan rekomendasi
"""

import logging
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware

from schemas import GARequest, GAResponse
from ga.engine import GeneticAlgorithmEngine

# ─────────────────────────────────────────────────────────────────────────────
# Logging
# ─────────────────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s — %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger(__name__)

# ─────────────────────────────────────────────────────────────────────────────
# FastAPI App
# ─────────────────────────────────────────────────────────────────────────────
app = FastAPI(
    title       ="WMS Avian — GA Engine",
    description ="Genetic Algorithm Engine untuk optimasi warehouse slotting (put-away).",
    version     ="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins =["*"],
    allow_methods =["*"],
    allow_headers =["*"],
)

# ─────────────────────────────────────────────────────────────────────────────
# Endpoints
# ─────────────────────────────────────────────────────────────────────────────

@app.get("/", tags=["Health"])
def health_check():
    """Health check — pastikan GA Engine berjalan."""
    return {"status": "ok", "service": "WMS GA Engine", "version": "1.0.0"}


@app.post("/ga/run", response_model=GAResponse, tags=["GA"])
def run_ga(request: GARequest):
    """
    Jalankan Genetic Algorithm untuk menghasilkan rekomendasi penempatan barang.

    Input:
        - items     : daftar item yang akan ditempatkan (dari inbound order)
        - cells     : daftar sel yang tersedia di warehouse
        - affinities: hubungan afinitas antar item (opsional)
        - parameters: parameter GA (populasi, generasi, mutation rate, dll.)

    Output:
        - fitness_score     : rata-rata fitness kromosom terbaik (0–100)
        - generations_run   : berapa generasi GA berjalan
        - execution_time_ms : waktu eksekusi dalam milidetik
        - chromosome        : rekomendasi penempatan per item (cell_id + breakdown fitness)
    """
    if not request.items:
        raise HTTPException(status_code=400, detail="'items' tidak boleh kosong.")
    if not request.cells:
        raise HTTPException(status_code=400, detail="'cells' tidak boleh kosong.")

    logger.info(
        "[API] POST /ga/run | order_id=%d | items=%d | cells=%d",
        request.inbound_order_id,
        len(request.items),
        len(request.cells),
    )

    try:
        engine = GeneticAlgorithmEngine(request)
        result = engine.run()
        return result
    except Exception as e:
        logger.exception("[API] GA run failed: %s", str(e))
        raise HTTPException(status_code=500, detail=f"GA Engine error: {str(e)}")
