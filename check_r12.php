<?php

use Illuminate\Support\Facades\DB;
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Sample RR-01 cells
$rrid = DB::table('racks')->where('code','RR-01')->value('id');
$cells = DB::table('cells')->where('rack_id',$rrid)->get();
echo "=== RR-01 (id=$rrid) cells ===\n";
foreach($cells as $c) {
    echo "code={$c->code} lv={$c->level} col={$c->column} cap={$c->capacity_max} status={$c->status}\n";
}

// Check R12-R15
echo "\n=== R12-R15 ===\n";
$racks = DB::table('racks')->whereIn('code',['12','13','14','15'])
    ->select('id','code','pos_x','pos_z','total_levels','total_columns')->get();
foreach($racks as $r) {
    echo "R{$r->code}: id={$r->id} x={$r->pos_x} z={$r->pos_z} lv={$r->total_levels} col={$r->total_columns}\n";
}
