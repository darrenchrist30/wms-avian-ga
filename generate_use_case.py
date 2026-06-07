import xml.etree.ElementTree as ET

UC_W, UC_H = 220, 60
AW, AH = 50, 85
BX, BY, BW, BH = 175, 80, 2560, 1150

COLS = [375, 775, 1175, 1565, 1955, 2445]
ROWS = [170, 295, 420, 545, 670, 795]

cells_list = []
_cid = [100]

def nid():
    _cid[0] += 1
    return str(_cid[0])

def vcell(cid, val, style, x, y, w, h):
    el = ET.Element("mxCell", {
        "id": str(cid), "value": val, "style": style,
        "vertex": "1", "parent": "1"
    })
    ET.SubElement(el, "mxGeometry", {
        "x": str(x), "y": str(y), "width": str(w), "height": str(h),
        "as": "geometry"
    })
    cells_list.append(el)
    return str(cid)

def ecell(src, tgt, val="", style=""):
    el = ET.Element("mxCell", {
        "id": nid(), "value": val, "style": style,
        "edge": "1", "source": str(src), "target": str(tgt), "parent": "1"
    })
    ET.SubElement(el, "mxGeometry", {"relative": "1", "as": "geometry"})
    cells_list.append(el)

# ── Title ──
vcell(nid(), "Diagram Use Case — Sistem WMS Avian GA",
      "text;html=1;align=center;fontStyle=1;fontSize=20;fillColor=none;strokeColor=none;",
      BX + BW//2 - 450, BY - 52, 900, 38)

# ── System boundary ──
vcell(nid(), "",
      "rounded=0;html=1;dashed=1;dashPattern=8 4;"
      "fillColor=none;strokeColor=#444444;strokeWidth=2;",
      BX, BY, BW, BH)

# ── Section dividers (thin vertical bars) ──
DIV_S = "fillColor=#bbbbbb;strokeColor=none;html=1;"
for dx in [575, 975, 1375, 1755, 2155]:
    vcell(nid(), "", DIV_S, dx, BY + 2, 2, BH - 4)

# ── Section headers ──
HDR = ("text;html=1;align=center;verticalAlign=middle;fontStyle=1;"
       "fontSize=12;fillColor=none;strokeColor=none;")
for cx, lbl in [
    (375,  "Autentikasi &<br>Master Data"),
    (775,  "Penerimaan<br>(Inbound)"),
    (1175, "GA &<br>Put-Away"),
    (1565, "Manajemen Stok"),
    (1955, "Pengeluaran<br>(Outbound)"),
    (2445, "Laporan &<br>Visualisasi"),
]:
    vcell(nid(), lbl, HDR, cx - 110, BY + 4, 220, 50)

# ── Actors ──
ACT = ("shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;"
       "html=1;whiteSpace=wrap;fillColor=#f5f5f5;strokeColor=#333333;"
       "fontColor=#333333;fontSize=13;fontStyle=1;")

a_admin = vcell("actor_admin", "Admin",      ACT, 50, 250, AW, AH)
a_super = vcell("actor_super", "Supervisor", ACT, 50, 660, AH, AH)
a_oper  = vcell("actor_oper",  "Operator",   ACT, 50, 1000, AW, AH)

# ── Use cases ──
UC_S = "ellipse;whiteSpace=wrap;html=1;fontSize=12;fillColor=#dae8fc;strokeColor=#6c8ebf;"
UC_G = "ellipse;whiteSpace=wrap;html=1;fontSize=12;fillColor=#d5e8d4;strokeColor=#82b366;"

def uc(uid, text, ci, ri, ga=False):
    return vcell(uid, text, UC_G if ga else UC_S,
                 COLS[ci] - UC_W // 2, ROWS[ri], UC_W, UC_H)

# Col 0 — Autentikasi & Master Data
uc('uc01', 'Login',                      0, 0)
uc('uc02', 'Kelola Data Pengguna',        0, 1)
uc('uc03', 'Kelola Data Gudang',          0, 2)
uc('uc04', 'Kelola Data Rak & Sel',       0, 3)
uc('uc05', 'Kelola Kategori Barang',      0, 4)
uc('uc06', 'Kelola Data Barang',          0, 5)

# Col 1 — Inbound
uc('uc07', 'Input Surat Jalan',           1, 1)
uc('uc08', 'Sinkronisasi Data ERP',       1, 2)
uc('uc09', 'Proses Penerimaan Barang',    1, 3)

# Col 2 — GA & Put-Away
uc('uc10', 'Generate Rekomendasi GA',     2, 1, ga=True)
uc('uc11', 'Terima Rekomendasi GA',       2, 2, ga=True)
uc('uc12', 'Override Rekomendasi GA',     2, 3, ga=True)
uc('uc13', 'Eksekusi Put-Away (Scan QR)', 2, 4)

# Col 3 — Manajemen Stok
uc('uc14', 'Lihat Data Stok',             3, 1)
uc('uc15', 'Lihat Stok Kritis',           3, 2)
uc('uc16', 'Transfer Stok Antar Sel',     3, 3)

# Col 4 — Outbound
uc('uc17', 'Buat Permintaan Pengeluaran', 4, 1)
uc('uc18', 'Setujui Permintaan',          4, 2)
uc('uc19', 'Tolak Permintaan',            4, 3)
uc('uc20', 'Eksekusi Pengeluaran FIFO',   4, 4)

# Col 5 — Laporan & Visualisasi
uc('uc21', 'Lihat Dashboard',             5, 1)
uc('uc22', 'Lihat Laporan',               5, 2)
uc('uc23', 'Visualisasi Gudang 3D',       5, 3)
uc('uc24', 'Audit Log',                   5, 4)

# ── Associations ──
AS = "html=1;endArrow=none;startArrow=none;strokeColor=#777777;strokeWidth=1.1;"

for act, ucs in [
    ('actor_admin', ['uc01','uc02','uc03','uc04','uc05','uc06',
                     'uc14','uc15','uc16','uc18','uc19',
                     'uc21','uc22','uc23','uc24']),
    ('actor_super', ['uc01',
                     'uc11','uc12',
                     'uc14','uc15','uc16','uc18','uc19',
                     'uc21','uc22','uc23']),
    ('actor_oper',  ['uc01',
                     'uc07','uc08','uc09',
                     'uc10','uc13',
                     'uc14','uc16',
                     'uc17','uc20',
                     'uc21','uc22','uc23']),
]:
    for uid in ucs:
        ecell(act, uid, "", AS)

# ── Include / Extend ──
IE = ("edgeStyle=none;html=1;dashed=1;endArrow=open;endFill=0;"
      "strokeColor=#555555;strokeWidth=1.2;fontStyle=2;fontSize=11;"
      "labelBackgroundColor=#ffffff;")
ecell('uc09', 'uc10', '«include»', IE)
ecell('uc11', 'uc10', '«extend»',  IE)
ecell('uc12', 'uc10', '«extend»',  IE)

# ── Legend ──
vcell(nid(), "Keterangan:",
      "text;html=1;fontStyle=1;fontSize=11;fillColor=none;strokeColor=none;",
      BX + BW + 10, BY + 50, 120, 24)
vcell(nid(), "",
      "ellipse;fillColor=#dae8fc;strokeColor=#6c8ebf;html=1;",
      BX + BW + 10, BY + 80, 40, 22)
vcell(nid(), "Use Case Reguler",
      "text;html=1;fontSize=11;fillColor=none;strokeColor=none;",
      BX + BW + 56, BY + 80, 140, 22)
vcell(nid(), "",
      "ellipse;fillColor=#d5e8d4;strokeColor=#82b366;html=1;",
      BX + BW + 10, BY + 112, 40, 22)
vcell(nid(), "Use Case GA",
      "text;html=1;fontSize=11;fillColor=none;strokeColor=none;",
      BX + BW + 56, BY + 112, 120, 22)

# ── Output ──
root_el = ET.Element("mxGraphModel", {
    "dx": "1800", "dy": "1200", "grid": "1", "gridSize": "10",
    "guides": "1", "tooltips": "1", "connect": "1", "arrows": "1",
    "fold": "1", "page": "1", "pageScale": "1",
    "pageWidth": "2756", "pageHeight": "1654",
    "math": "0", "shadow": "0"
})
root_inner = ET.SubElement(root_el, "root")
ET.SubElement(root_inner, "mxCell", {"id": "0"})
ET.SubElement(root_inner, "mxCell", {"id": "1", "parent": "0"})
for c in cells_list:
    root_inner.append(c)

out = r"c:\laragon\www\wms-avian-ga\use_case_wms_avian.drawio"
with open(out, "w", encoding="utf-8") as f:
    f.write('<?xml version="1.0" encoding="UTF-8"?>\n')
    f.write('<mxfile host="app.diagrams.net">\n  <diagram name="Use Case WMS Avian GA">\n    ')
    ET.ElementTree(root_el).write(f, encoding="unicode", xml_declaration=False)
    f.write('\n  </diagram>\n</mxfile>')

print("Done:", out)
print(f"Cells generated: {len(cells_list)}")
