<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Cek cell dan rack
$racks = DB::table('racks')->select('id','code','name','warehouse_id')->orderBy('id')->get();
echo "=== RACKS ===\n";
foreach ($racks as $r) {
    echo "ID={$r->id} code={$r->code} name={$r->name} warehouse_id={$r->warehouse_id}\n";
}

echo "\n=== CELLS (sample 20) ===\n";
$cells = DB::table('cells')
    ->join('racks','racks.id','=','cells.rack_id')
    ->select('cells.id','cells.rack_id','racks.code as rack_code','cells.code','cells.label','cells.level','cells.column','cells.capacity_max','cells.capacity_used','cells.status')
    ->orderBy('cells.id')
    ->limit(30)
    ->get();
foreach ($cells as $c) {
    echo "cell_id={$c->id} rack={$c->rack_code} code={$c->code} label={$c->label} level={$c->level} col={$c->column} cap={$c->capacity_max} used={$c->capacity_used} status={$c->status}\n";
}

echo "\nTotal cells: " . DB::table('cells')->count() . "\n";
echo "Total racks: " . DB::table('racks')->count() . "\n";

// Cek stock_records structure
echo "\n=== STOCK_RECORDS sample (3) ===\n";
$stocks = DB::table('stock_records')
    ->join('items','items.id','=','stock_records.item_id')
    ->join('cells','cells.id','=','stock_records.cell_id')
    ->select('stock_records.id','items.name as item_name','items.sku','cells.label as cell_label','cells.code as cell_code','stock_records.quantity')
    ->limit(3)->get();
foreach ($stocks as $s) {
    echo "sr_id={$s->id} item={$s->item_name} sku={$s->sku} cell={$s->cell_code}({$s->cell_label}) qty={$s->quantity}\n";
}
