import xml.etree.ElementTree as ET

TABLE_W = 300
ROW_H   = 40
HDR     = 28
GAP_X   = 110   # horizontal gap between columns
GAP_Y   = 50    # vertical gap between tables in same column

STYLE_TBL = (
    "swimlane;fontStyle=1;childLayout=stackLayout;horizontal=1;startSize=28;"
    "fillColor=none;horizontalStack=0;resizeParent=1;resizeParentMax=0;resizeLast=0;"
    "collapsible=1;marginBottom=0;whiteSpace=wrap;html=1;fontSize=13;"
)
STYLE_FLD = (
    "text;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;"
    "spacingLeft=8;spacingRight=4;overflow=hidden;rotatable=0;"
    "points=[[0,0.5],[1,0.5]];portConstraint=eastwest;whiteSpace=wrap;html=1;fontSize=12;"
)
STYLE_EDGE = (
    "edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"
    "endArrow=ERmany;startArrow=ERmandOne;strokeWidth=1.5;fontSize=11;"
    "labelBackgroundColor=#ffffff;fillColor=default;strokeColor=#555555;fontColor=#333333;"
)

# (id, label, col, row)
# Columns: 0=Auth, 1=Location, 2=Inventory, 3=Inbound+Stock, 4=Outbound, 5=GA
# Rows within each column are stacked automatically

COLS = {
    0: 10,
    1: 420,
    2: 830,
    3: 1240,
    4: 1650,
    5: 2060,
}

# (id, label, col, [(name, type, key)])
TABLES = [
    # ── COL 0: AUTH ──────────────────────────────────
    ('roles', 'roles', 0, [
        ('id',    'bigint',  'PK'),
        ('name',  'varchar', ''),
        ('slug',  'varchar', 'UNI'),
    ]),
    ('users', 'users', 0, [
        ('id',           'bigint',  'PK'),
        ('name',         'varchar', ''),
        ('email',        'varchar', 'UNI'),
        ('employee_id',  'varchar', 'UNI'),
        ('role_id',      'bigint',  'FK'),
        ('warehouse_id', 'bigint',  'FK'),
        ('is_active',    'tinyint', ''),
    ]),
    # ── COL 1: LOCATION ──────────────────────────────
    ('warehouses', 'warehouses', 1, [
        ('id',        'bigint',  'PK'),
        ('code',      'varchar', 'UNI'),
        ('name',      'varchar', ''),
        ('address',   'text',    ''),
        ('pic',       'varchar', ''),
        ('is_active', 'tinyint', ''),
    ]),
    ('racks', 'racks', 1, [
        ('id',                   'bigint',  'PK'),
        ('warehouse_id',         'bigint',  'FK'),
        ('dominant_category_id', 'bigint',  'FK'),
        ('code',                 'varchar', 'UNI'),
        ('name',                 'varchar', ''),
        ('total_levels',         'tinyint', ''),
        ('total_columns',        'tinyint', ''),
        ('is_active',            'tinyint', ''),
    ]),
    ('cells', 'cells', 1, [
        ('id',                   'bigint',  'PK'),
        ('rack_id',              'bigint',  'FK'),
        ('dominant_category_id', 'bigint',  'FK'),
        ('code',                 'varchar', ''),
        ('blok',                 'tinyint', ''),
        ('grup',                 'char',    ''),
        ('kolom',                'tinyint', ''),
        ('baris',                'tinyint', ''),
        ('capacity_max',         'int',     ''),
        ('capacity_used',        'int',     ''),
        ('status',               'enum',    ''),
        ('is_active',            'tinyint', ''),
    ]),
    # ── COL 2: INVENTORY ─────────────────────────────
    ('item_categories', 'item_categories', 2, [
        ('id',         'bigint',  'PK'),
        ('code',       'varchar', 'UNI'),
        ('name',       'varchar', ''),
        ('color_code', 'varchar', ''),
        ('is_active',  'tinyint', ''),
    ]),
    ('items', 'items', 2, [
        ('id',            'bigint',  'PK'),
        ('category_id',   'bigint',  'FK'),
        ('sku',           'varchar', 'UNI'),
        ('name',          'varchar', ''),
        ('movement_type', 'enum',    ''),
        ('min_stock',     'int',     ''),
        ('max_stock',     'int',     ''),
        ('reorder_point', 'int',     ''),
        ('home_cell_id',  'bigint',  'FK'),
        ('is_active',     'tinyint', ''),
    ]),
    # ── COL 3: INBOUND + STOCK ───────────────────────
    ('inbound_transactions', 'inbound_transactions', 3, [
        ('id',           'bigint',   'PK'),
        ('warehouse_id', 'bigint',   'FK'),
        ('received_by',  'bigint',   'FK'),
        ('do_number',    'varchar',  'UNI'),
        ('do_date',      'date',     ''),
        ('status',       'enum',     ''),
        ('received_at',  'datetime', ''),
    ]),
    ('inbound_details', 'inbound_details', 3, [
        ('id',                'bigint', 'PK'),
        ('inbound_order_id',  'bigint', 'FK'),
        ('item_id',           'bigint', 'FK'),
        ('quantity_ordered',  'int',    ''),
        ('quantity_received', 'int',    ''),
        ('status',            'enum',   ''),
    ]),
    ('stock_records', 'stock_records', 3, [
        ('id',                    'bigint',    'PK'),
        ('item_id',               'bigint',    'FK'),
        ('cell_id',               'bigint',    'FK'),
        ('warehouse_id',          'bigint',    'FK'),
        ('inbound_order_item_id', 'bigint',    'FK'),
        ('quantity',              'int',       ''),
        ('inbound_date',          'date',      ''),
        ('last_moved_at',         'timestamp', ''),
        ('status',                'enum',      ''),
    ]),
    # ── COL 4: OUTBOUND ──────────────────────────────
    ('outbound_requests', 'outbound_requests', 4, [
        ('id',             'bigint',    'PK'),
        ('request_number', 'varchar',   'UNI'),
        ('operator_id',    'bigint',    'FK'),
        ('warehouse_id',   'bigint',    'FK'),
        ('approved_by',    'bigint',    'FK'),
        ('rejected_by',    'bigint',    'FK'),
        ('status',         'enum',      ''),
        ('approved_at',    'timestamp', ''),
        ('executed_at',    'timestamp', ''),
    ]),
    ('outbound_request_items', 'outbound_request_items', 4, [
        ('id',                  'bigint', 'PK'),
        ('outbound_request_id', 'bigint', 'FK'),
        ('item_id',             'bigint', 'FK'),
        ('quantity_requested',  'int',    ''),
    ]),
    # ── COL 5: GA ────────────────────────────────────
    ('ga_recommendations', 'ga_recommendations', 5, [
        ('id',               'bigint',    'PK'),
        ('inbound_order_id', 'bigint',    'FK'),
        ('generated_by',     'bigint',    'FK'),
        ('accepted_by',      'bigint',    'FK'),
        ('rejected_by',      'bigint',    'FK'),
        ('fitness_score',    'decimal',   ''),
        ('generations_run',  'int',       ''),
        ('status',           'enum',      ''),
        ('generated_at',     'timestamp', ''),
    ]),
    ('ga_recommendation_details', 'ga_recommendation_details', 5, [
        ('id',                    'bigint',  'PK'),
        ('ga_recommendation_id',  'bigint',  'FK'),
        ('inbound_order_item_id', 'bigint',  'FK'),
        ('cell_id',               'bigint',  'FK'),
        ('quantity',              'int',     ''),
        ('gene_fitness',          'decimal', ''),
        ('fc_cap_score',          'decimal', ''),
        ('fc_cat_score',          'decimal', ''),
        ('fc_aff_score',          'decimal', ''),
        ('fc_split_score',        'decimal', ''),
        ('fc_mov_score',          'decimal', ''),
    ]),
]

# (source=ONE side, target=MANY/FK side, label)
RELATIONS = [
    # Auth
    ('roles',                'users',                    '1:N'),
    # Location
    ('warehouses',           'racks',                    '1:N'),
    ('racks',                'cells',                    '1:N'),
    ('item_categories',      'items',                    '1:N'),
    # Inbound flow
    ('warehouses',           'inbound_transactions',     '1:N'),
    ('users',                'inbound_transactions',     '1:N'),
    ('inbound_transactions', 'inbound_details',          '1:N'),
    ('items',                'inbound_details',          '1:N'),
    ('inbound_details',      'stock_records',            '1:N'),
    ('items',                'stock_records',            '1:N'),
    ('cells',                'stock_records',            '1:N'),
    # Outbound flow
    ('users',                'outbound_requests',        '1:N'),
    ('outbound_requests',    'outbound_request_items',   '1:N'),
    ('items',                'outbound_request_items',   '1:N'),
    # GA flow
    ('inbound_transactions', 'ga_recommendations',       '1:N'),
    ('ga_recommendations',   'ga_recommendation_details','1:N'),
    ('inbound_details',      'ga_recommendation_details','1:N'),
    ('cells',                'ga_recommendation_details','1:N'),
]

cells_xml = []
col_y = {c: 10 for c in COLS}   # current y per column

def field_label(fname, ftype, key):
    if key in ('PK', 'FK', 'UNI'):
        return f"+ {fname}: {ftype} ({key})"
    return f"+ {fname}: {ftype}"

def make_table(tbl_id, label, col, fields):
    x = COLS[col]
    y = col_y[col]
    h = HDR + len(fields) * ROW_H

    tbl = ET.Element("mxCell", {
        "id": tbl_id, "value": label,
        "style": STYLE_TBL,
        "vertex": "1", "parent": "1"
    })
    ET.SubElement(tbl, "mxGeometry", {
        "x": str(x), "y": str(y),
        "width": str(TABLE_W), "height": str(h),
        "as": "geometry"
    })
    cells_xml.append(tbl)

    for i, (fname, ftype, key) in enumerate(fields):
        fc = ET.Element("mxCell", {
            "id": f"{tbl_id}_f{i}",
            "value": field_label(fname, ftype, key),
            "style": STYLE_FLD,
            "vertex": "1", "parent": tbl_id
        })
        ET.SubElement(fc, "mxGeometry", {
            "y": str(HDR + i * ROW_H),
            "width": str(TABLE_W), "height": str(ROW_H),
            "as": "geometry"
        })
        cells_xml.append(fc)

    col_y[col] = y + h + GAP_Y

for (tbl_id, label, col, fields) in TABLES:
    make_table(tbl_id, label, col, fields)

for i, (src, tgt, lbl) in enumerate(RELATIONS):
    edge = ET.Element("mxCell", {
        "id": f"edge_{i}", "value": lbl,
        "style": STYLE_EDGE,
        "edge": "1", "source": src, "target": tgt, "parent": "1"
    })
    ET.SubElement(edge, "mxGeometry", {"relative": "1", "as": "geometry"})
    cells_xml.append(edge)

root_el = ET.Element("mxGraphModel", {
    "dx": "1800", "dy": "1200", "grid": "1", "gridSize": "10",
    "guides": "1", "tooltips": "1", "connect": "1", "arrows": "1",
    "fold": "1", "page": "1", "pageScale": "1",
    "pageWidth": "2756", "pageHeight": "1654",  # A1 landscape
    "math": "0", "shadow": "0"
})
root_inner = ET.SubElement(root_el, "root")
ET.SubElement(root_inner, "mxCell", {"id": "0"})
ET.SubElement(root_inner, "mxCell", {"id": "1", "parent": "0"})
for c in cells_xml:
    root_inner.append(c)

out_path = r"c:\laragon\www\wms-avian-ga\erd_wms_avian_swimlane.drawio"
with open(out_path, "w", encoding="utf-8") as f:
    f.write('<?xml version="1.0" encoding="UTF-8"?>\n')
    f.write('<mxfile host="app.diagrams.net">\n  <diagram name="ERD WMS Avian GA">\n    ')
    ET.ElementTree(root_el).write(f, encoding="unicode", xml_declaration=False)
    f.write('\n  </diagram>\n</mxfile>')

print("Done:", out_path)
print("Column heights:")
for c, y in col_y.items():
    print(f"  Col {c}: {y}px")
