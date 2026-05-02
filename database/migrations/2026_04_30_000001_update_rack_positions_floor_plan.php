<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reposisi rak agar tampilan 3D sesuai denah fisik gudang sparepart.
 *
 * Koordinat (satuan 3D unit ≈ 1 meter):
 *   X = kiri–kanan (positif = kanan)
 *   Z = kedalaman  (positif = depan/pintu masuk, negatif = belakang gudang)
 *
 * Layout fisik:
 *   – Rak 1–11  : rak utama, tersusun berpasangan sepanjang sumbu Z (tengah, X≈13)
 *   – Rak 12–14 : rak dinding kiri, sejajar sumbu Z (X≈2)
 *   – Rak 15    : rak kanan tengah
 *   – Rak 16    : rak kanan belakang (sejajar rak 11)
 *   – Rak 17–20 : area khusus di depan (Rak Ban, Lemari, V-Belt)
 *
 * Semua zone disetel ke pos_x=0, pos_z=0 sehingga koordinat rak menjadi absolut.
 */
class UpdateRackPositionsFloorPlan extends Migration
{
    private array $rackPositions = [
        // Rak utama – pasangan berdekatan, tersusun ke belakang sepanjang Z
        '1'  => ['pos_x' => 13.0, 'pos_z' =>   0.0],   // pasangan depan #1
        '2'  => ['pos_x' => 13.0, 'pos_z' =>  -3.5],
        '3'  => ['pos_x' => 13.0, 'pos_z' =>  -9.0],   // pasangan #2
        '4'  => ['pos_x' => 13.0, 'pos_z' => -12.5],
        '5'  => ['pos_x' => 13.0, 'pos_z' => -18.0],   // pasangan #3
        '6'  => ['pos_x' => 13.0, 'pos_z' => -21.5],
        '7'  => ['pos_x' => 13.0, 'pos_z' => -27.0],   // pasangan #4
        '8'  => ['pos_x' => 13.0, 'pos_z' => -30.5],
        '9'  => ['pos_x' => 13.0, 'pos_z' => -36.0],   // pasangan #5
        '10' => ['pos_x' => 13.0, 'pos_z' => -39.5],
        '11' => ['pos_x' => 13.0, 'pos_z' => -45.0],   // rak tunggal paling belakang

        // Rak dinding kiri – berjajar sepanjang Z
        '12' => ['pos_x' =>  2.0, 'pos_z' => -18.0],
        '13' => ['pos_x' =>  2.0, 'pos_z' => -24.0],
        '14' => ['pos_x' =>  2.0, 'pos_z' => -30.0],

        // Rak kanan tengah & belakang
        '15' => ['pos_x' => 23.0, 'pos_z' => -24.0],
        '16' => ['pos_x' => 20.0, 'pos_z' => -45.0],

        // Area khusus – depan (dekat pintu masuk)
        '17' => ['pos_x' => 21.0, 'pos_z' =>   5.0],   // Rak Ban kanan
        '18' => ['pos_x' => 16.0, 'pos_z' =>   5.0],
        '19' => ['pos_x' =>  8.0, 'pos_z' =>   5.0],   // Lemari
        '20' => ['pos_x' =>  2.0, 'pos_z' =>   5.0],   // Area Gantungan V-Belt
    ];

    public function up(): void
    {
        // Setel semua zone ke origin agar koordinat rak menjadi absolut
        DB::table('zones')
            ->whereIn('code', ['A', 'B', 'C'])
            ->update(['pos_x' => 0.0, 'pos_z' => 0.0]);

        foreach ($this->rackPositions as $code => $pos) {
            DB::table('racks')
                ->where('code', $code)
                ->update([
                    'pos_x' => $pos['pos_x'],
                    'pos_z' => $pos['pos_z'],
                ]);
        }
    }

    public function down(): void
    {
        // Kembalikan ke layout linear asli (seeder)
        DB::table('zones')->where('code', 'B')->update(['pos_x' => 40.0, 'pos_z' => 0.0]);
        DB::table('zones')->where('code', 'C')->update(['pos_x' => 80.0, 'pos_z' => 0.0]);

        // Zone A racks (1–8): pos_x = (i * 4), pos_z = 0
        foreach (range(1, 8) as $i => $no) {
            DB::table('racks')->where('code', (string) $no)
                ->update(['pos_x' => (float) ($i * 4), 'pos_z' => 0.0]);
        }
        // Zone B racks (9–16): pos_x = (i * 4), relative to zone B (which starts at 40)
        foreach (range(9, 16) as $i => $no) {
            DB::table('racks')->where('code', (string) $no)
                ->update(['pos_x' => (float) ($i * 4), 'pos_z' => 0.0]);
        }
        // Zone C racks (17–20)
        foreach (range(17, 20) as $i => $no) {
            DB::table('racks')->where('code', (string) $no)
                ->update(['pos_x' => (float) ($i * 4), 'pos_z' => 0.0]);
        }
    }
}
