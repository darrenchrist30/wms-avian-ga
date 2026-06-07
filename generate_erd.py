import xml.etree.ElementTree as ET

ROW_H = 25
HDR_H = 30
TBL_W = 240
COL1_W = 32
COL2_W = 138
COL3_W = 70

GREEN_HDR = "#0d8564"
WHITE     = "#ffffff"

TABLES = [
    ("roles", 10, 10, [
        ("PK","id","bigint"), ("","name","varchar"), ("UNI","slug","varchar"),
    ]),
    ("users", 10, 135, [
        ("PK","id","bigint"), ("","name","varchar"), ("UNI","email","varchar"),
        ("UNI","employee_id","varchar"), ("FK","role_id","bigint"),
        ("FK","warehouse_id","bigint"), ("","is_active","tinyint"),
    ]),
    ("warehouses", 270, 10, [
        ("PK","id","bigint"), ("UNI","code","varchar"), ("","name","varchar"),
        ("","address","text"), ("","pic","varchar"), ("","is_active","tinyint"),
    ]),
    ("racks", 270, 210, [
        ("PK","id","bigint"), ("FK","warehouse_id","bigint"),
        ("FK","dominant_category_id","bigint"), ("UNI","code","varchar"),
        ("","name","varchar"), ("","total_levels","tinyint"),
        ("","total_columns","tinyint"), ("","is_active","tinyint"),
    ]),
    ("cells", 270, 450, [
        ("PK","id","bigint"), ("FK","rack_id","bigint"),
        ("FK","dominant_category_id","bigint"), ("","code","varchar"),
        ("","blok","tinyint"), ("","grup","char"),
        ("","kolom","tinyint"), ("","baris","tinyint"),
        ("","capacity_max","int"), ("","capacity_used","int"),
        ("","status","enum"), ("","is_active","tinyint"),
    ]),
    ("item_categories", 530, 10, [
        ("PK","id","bigint"), ("UNI","code","varchar"), ("","name","varchar"),
        ("","color_code","varchar"), ("","is_active","tinyint"),
    ]),
    ("items", 530, 165, [
        ("PK","id","bigint"), ("FK","category_id","bigint"), ("UNI","sku","varchar"),
        ("","name","varchar"), ("","movement_type","enum"),
        ("","min_stock","int"), ("","max_stock","int"),
        ("","reorder_point","int"), ("FK","home_cell_id","bigint"),
        ("","is_active","tinyint"),
    ]),
    ("stock_records", 530, 445, [
        ("PK","id","bigint"), ("FK","item_id","bigint"), ("FK","cell_id","bigint"),
        ("FK","warehouse_id","bigint"), ("FK","inbound_order_item_id","bigint"),
        ("","quantity","int"), ("","inbound_date","date"),
        ("","last_moved_at","timestamp"), ("","status","enum"),
    ]),
    ("inbound_transactions", 790, 10, [
        ("PK","id","bigint"), ("FK","warehouse_id","bigint"),
        ("FK","received_by","bigint"), ("UNI","do_number","varchar"),
        ("","do_date","date"), ("","status","enum"), ("","received_at","datetime"),
    ]),
    ("inbound_details", 790, 215, [
        ("PK","id","bigint"), ("FK","inbound_order_id","bigint"),
        ("FK","item_id","bigint"), ("","quantity_ordered","int"),
        ("","quantity_received","int"), ("","status","enum"),
    ]),
    ("outbound_requests", 1050, 10, [
        ("PK","id","bigint"), ("UNI","request_number","varchar"),
        ("FK","operator_id","bigint"), ("FK","warehouse_id","bigint"),
        ("FK","approved_by","bigint"), ("FK","rejected_by","bigint"),
        ("","status","enum"), ("","approved_at","timestamp"),
        ("","executed_at","timestamp"),
    ]),
    ("outbound_request_items", 1050, 265, [
        ("PK","id","bigint"), ("FK","outbound_request_id","bigint"),
        ("FK","item_id","bigint"), ("","quantity_requested","int"),
    ]),
    ("ga_recommendations", 1310, 10, [
        ("PK","id","bigint"), ("FK","inbound_order_id","bigint"),
        ("FK","generated_by","bigint"), ("FK","accepted_by","bigint"),
        ("FK","rejected_by","bigint"), ("","fitness_score","decimal"),
        ("","generations_run","int"), ("","status","enum"),
        ("","generated_at","timestamp"),
    ]),
    ("ga_recommendation_details", 1310, 265, [
        ("PK","id","bigint"), ("FK","ga_recommendation_id","bigint"),
        ("FK","inbound_order_item_id","bigint"), ("FK","cell_id","bigint"),
        ("","quantity","int"), ("","gene_fitness","decimal"),
        ("","fc_cap_score","decimal"), ("","fc_cat_score","decimal"),
        ("","fc_aff_score","decimal"), ("","fc_split_score","decimal"),
        ("","fc_mov_score","decimal"),
    ]),
]

RELATIONS = [
    ("users","role_id","roles","id"),
    ("users","warehouse_id","warehouses","id"),
    ("racks","warehouse_id","warehouses","id"),
    ("racks","dominant_category_id","item_categories","id"),
    ("cells","rack_id","racks","id"),
    ("cells","dominant_category_id","item_categories","id"),
    ("items","category_id","item_categories","id"),
    ("items","home_cell_id","cells","id"),
    ("stock_records","item_id","items","id"),
    ("stock_records","cell_id","cells","id"),
    ("stock_records","warehouse_id","warehouses","id"),
    ("stock_records","inbound_order_item_id","inbound_details","id"),
    ("inbound_transactions","warehouse_id","warehouses","id"),
    ("inbound_transactions","received_by","users","id"),
    ("inbound_details","inbound_order_id","inbound_transactions","id"),
    ("inbound_details","item_id","items","id"),
    ("outbound_requests","operator_id","users","id"),
    ("outbound_requests","warehouse_id","warehouses","id"),
    ("outbound_requests","approved_by","users","id"),
    ("outbound_requests","rejected_by","users","id"),
    ("outbound_request_items","outbound_request_id","outbound_requests","id"),
    ("outbound_request_items","item_id","items","id"),
    ("ga_recommendations","inbound_order_id","inbound_transactions","id"),
    ("ga_recommendations","generated_by","users","id"),
    ("ga_recommendations","accepted_by","users","id"),
    ("ga_recommendations","rejected_by","users","id"),
    ("ga_recommendation_details","ga_recommendation_id","ga_recommendations","id"),
    ("ga_recommendation_details","inbound_order_item_id","inbound_details","id"),
    ("ga_recommendation_details","cell_id","cells","id"),
]

cells_xml = []
row_ids = {}
edge_counter = [5000]


def make_table_xml(tbl_name, x, y, columns):
    h = HDR_H + len(columns) * ROW_H
    tbl_id = f"tbl_{tbl_name}"

    tbl_cell = ET.Element("mxCell", {
        "id": tbl_id,
        "value": tbl_name,
        "style": (
            "shape=table;startSize=30;container=1;collapsible=0;"
            "childLayout=tableLayout;fixedRows=1;rowLines=0;"
            "fontStyle=1;align=center;resizeLast=1;"
            f"fillColor={GREEN_HDR};fontColor={WHITE};strokeColor={GREEN_HDR};"
            "fontSize=12;"
        ),
        "vertex": "1",
        "parent": "1"
    })
    ET.SubElement(tbl_cell, "mxGeometry", {
        "x": str(x), "y": str(y),
        "width": str(TBL_W), "height": str(h),
        "as": "geometry"
    })
    cells_xml.append(tbl_cell)

    for i, (key, col, dtype) in enumerate(columns):
        row_id = f"{tbl_id}_r{i}"
        row_ids[(tbl_name, col)] = row_id

        row_y = HDR_H + i * ROW_H
        fill = WHITE if i % 2 == 0 else "#f5f5f5"

        row_cell = ET.Element("mxCell", {
            "id": row_id,
            "value": "",
            "style": (
                "shape=tableRow;horizontal=0;startSize=0;"
                "swimlaneHead=0;swimlaneBody=0;"
                f"fillColor={fill};"
                "collapsible=0;dropTarget=0;"
                "points=[[0,0.5],[1,0.5]];portConstraint=eastwest;"
                "fontSize=11;top=0;left=0;right=0;bottom=1;"
            ),
            "vertex": "1",
            "parent": tbl_id
        })
        ET.SubElement(row_cell, "mxGeometry", {
            "y": str(row_y), "width": str(TBL_W), "height": str(ROW_H),
            "as": "geometry"
        })
        cells_xml.append(row_cell)

        key_color = "#c0392b" if key == "FK" else ("#1a3c2e" if key == "PK" else "#aaaaaa")
        c1 = ET.Element("mxCell", {
            "id": f"{row_id}c1", "value": key,
            "style": (
                f"shape=partialRectangle;connectable=0;fillColor={fill};"
                "top=0;left=0;bottom=0;right=0;fontStyle=1;overflow=hidden;"
                f"fontSize=9;fontColor={key_color};"
            ),
            "vertex": "1", "parent": row_id
        })
        g1 = ET.SubElement(c1, "mxGeometry", {
            "width": str(COL1_W), "height": str(ROW_H), "as": "geometry"
        })
        ET.SubElement(g1, "mxRectangle", {
            "width": str(COL1_W), "height": str(ROW_H), "as": "alternateBounds"
        })
        cells_xml.append(c1)

        c2 = ET.Element("mxCell", {
            "id": f"{row_id}c2", "value": col,
            "style": (
                f"shape=partialRectangle;connectable=0;fillColor={fill};"
                "top=0;left=0;bottom=0;right=0;overflow=hidden;fontSize=11;"
            ),
            "vertex": "1", "parent": row_id
        })
        g2 = ET.SubElement(c2, "mxGeometry", {
            "x": str(COL1_W), "width": str(COL2_W), "height": str(ROW_H),
            "as": "geometry"
        })
        ET.SubElement(g2, "mxRectangle", {
            "width": str(COL2_W), "height": str(ROW_H), "as": "alternateBounds"
        })
        cells_xml.append(c2)

        c3 = ET.Element("mxCell", {
            "id": f"{row_id}c3", "value": dtype,
            "style": (
                f"shape=partialRectangle;connectable=0;fillColor={fill};"
                "top=0;left=0;bottom=0;right=0;overflow=hidden;fontSize=9;"
                "align=right;fontColor=#888888;"
            ),
            "vertex": "1", "parent": row_id
        })
        g3 = ET.SubElement(c3, "mxGeometry", {
            "x": str(COL1_W + COL2_W), "width": str(COL3_W),
            "height": str(ROW_H), "as": "geometry"
        })
        ET.SubElement(g3, "mxRectangle", {
            "width": str(COL3_W), "height": str(ROW_H), "as": "alternateBounds"
        })
        cells_xml.append(c3)


for (tname, x, y, cols) in TABLES:
    make_table_xml(tname, x, y, cols)

for (src_tbl, src_col, tgt_tbl, tgt_col) in RELATIONS:
    src_id = row_ids.get((src_tbl, src_col))
    tgt_id = row_ids.get((tgt_tbl, tgt_col))
    if not src_id or not tgt_id:
        continue
    eid = f"edge_{edge_counter[0]}"
    edge_counter[0] += 1
    edge = ET.Element("mxCell", {
        "id": eid,
        "value": "",
        "style": (
            "edgeStyle=entityRelationEdgeStyle;"
            "endArrow=ERzeroToMany;startArrow=ERmandOne;"
            "strokeColor=#0d8564;strokeWidth=1.5;exitX=1;exitY=0.5;"
            "exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;"
        ),
        "edge": "1",
        "source": src_id,
        "target": tgt_id,
        "parent": "1"
    })
    ET.SubElement(edge, "mxGeometry", {"relative": "1", "as": "geometry"})
    cells_xml.append(edge)

root_el = ET.Element("mxGraphModel", {
    "dx": "1422", "dy": "762", "grid": "1", "gridSize": "10",
    "guides": "1", "tooltips": "1", "connect": "1", "arrows": "1",
    "fold": "1", "page": "1", "pageScale": "1",
    "pageWidth": "1654", "pageHeight": "1169",
    "math": "0", "shadow": "0"
})
root_inner = ET.SubElement(root_el, "root")
ET.SubElement(root_inner, "mxCell", {"id": "0"})
ET.SubElement(root_inner, "mxCell", {"id": "1", "parent": "0"})
for c in cells_xml:
    root_inner.append(c)

out_path = r"c:\laragon\www\wms-avian-ga\erd_wms_avian.drawio"
with open(out_path, "w", encoding="utf-8") as f:
    f.write('<?xml version="1.0" encoding="UTF-8"?>\n')
    f.write('<mxfile host="app.diagrams.net">\n  <diagram name="ERD WMS Avian GA">\n    ')
    ET.ElementTree(root_el).write(f, encoding="unicode", xml_declaration=False)
    f.write('\n  </diagram>\n</mxfile>')

print("Done:", out_path)
