-- ============================================================
-- RESET SCRIPT: Hapus data test PB-2026 dan interferensi
-- SJ-BATCH-TEST / SJ-GA-REAL-TEST sebelum re-run GA batch
--
-- Urutan eksekusi (respek FK constraints):
--   1. put_away_confirmations
--   2. ga_recommendation_details
--   3. ga_recommendations
--   4. inbound_details
--   5. inbound_transactions
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Hapus PB-2026-051 s/d PB-2026-060 ────────────────────

DELETE pac FROM put_away_confirmations pac
  JOIN inbound_details id2 ON pac.inbound_order_item_id = id2.id
  JOIN inbound_transactions it ON id2.inbound_order_id = it.id
 WHERE it.do_number LIKE 'PB-2026-%';

DELETE grd FROM ga_recommendation_details grd
  JOIN ga_recommendations gr ON grd.ga_recommendation_id = gr.id
  JOIN inbound_transactions it ON gr.inbound_order_id = it.id
 WHERE it.do_number LIKE 'PB-2026-%';

DELETE gr FROM ga_recommendations gr
  JOIN inbound_transactions it ON gr.inbound_order_id = it.id
 WHERE it.do_number LIKE 'PB-2026-%';

DELETE id2 FROM inbound_details id2
  JOIN inbound_transactions it ON id2.inbound_order_id = it.id
 WHERE it.do_number LIKE 'PB-2026-%';

DELETE FROM inbound_transactions
 WHERE do_number LIKE 'PB-2026-%';


-- ── 2. Hapus SJ-BATCH-TEST-001~010 (reservasi interferensi) ──

DELETE pac FROM put_away_confirmations pac
  JOIN inbound_details id2 ON pac.inbound_order_item_id = id2.id
  JOIN inbound_transactions it ON id2.inbound_order_id = it.id
 WHERE it.do_number LIKE 'SJ-BATCH-TEST-%';

DELETE grd FROM ga_recommendation_details grd
  JOIN ga_recommendations gr ON grd.ga_recommendation_id = gr.id
  JOIN inbound_transactions it ON gr.inbound_order_id = it.id
 WHERE it.do_number LIKE 'SJ-BATCH-TEST-%';

DELETE gr FROM ga_recommendations gr
  JOIN inbound_transactions it ON gr.inbound_order_id = it.id
 WHERE it.do_number LIKE 'SJ-BATCH-TEST-%';

DELETE id2 FROM inbound_details id2
  JOIN inbound_transactions it ON id2.inbound_order_id = it.id
 WHERE it.do_number LIKE 'SJ-BATCH-TEST-%';

DELETE FROM inbound_transactions
 WHERE do_number LIKE 'SJ-BATCH-TEST-%';


-- ── 3. Hapus SJ-GA-REAL-TEST-001~003 (reservasi interferensi) ─

DELETE pac FROM put_away_confirmations pac
  JOIN inbound_details id2 ON pac.inbound_order_item_id = id2.id
  JOIN inbound_transactions it ON id2.inbound_order_id = it.id
 WHERE it.do_number LIKE 'SJ-GA-REAL-TEST-%';

DELETE grd FROM ga_recommendation_details grd
  JOIN ga_recommendations gr ON grd.ga_recommendation_id = gr.id
  JOIN inbound_transactions it ON gr.inbound_order_id = it.id
 WHERE it.do_number LIKE 'SJ-GA-REAL-TEST-%';

DELETE gr FROM ga_recommendations gr
  JOIN inbound_transactions it ON gr.inbound_order_id = it.id
 WHERE it.do_number LIKE 'SJ-GA-REAL-TEST-%';

DELETE id2 FROM inbound_details id2
  JOIN inbound_transactions it ON id2.inbound_order_id = it.id
 WHERE it.do_number LIKE 'SJ-GA-REAL-TEST-%';

DELETE FROM inbound_transactions
 WHERE do_number LIKE 'SJ-GA-REAL-TEST-%';


-- ── 4. Fix cell 1-A-1-5 yang overfull (cap_used=43 > cap_max=20) ──
-- Naikkan capacity_max agar mencerminkan realita data MSPART import.
UPDATE cells SET capacity_max = 50 WHERE code = '1-A-1-5';


SET FOREIGN_KEY_CHECKS = 1;

-- ── Verifikasi setelah reset ──────────────────────────────────
SELECT COUNT(*) AS 'sisa PB-2026' FROM inbound_transactions WHERE do_number LIKE 'PB-2026-%';
SELECT COUNT(*) AS 'sisa BATCH-TEST' FROM inbound_transactions WHERE do_number LIKE 'SJ-BATCH-TEST-%';
SELECT COUNT(*) AS 'sisa REAL-TEST' FROM inbound_transactions WHERE do_number LIKE 'SJ-GA-REAL-TEST-%';
SELECT COUNT(*) AS 'total ga_recs' FROM ga_recommendations;
SELECT code, capacity_used, capacity_max FROM cells WHERE code = '1-A-1-5';
