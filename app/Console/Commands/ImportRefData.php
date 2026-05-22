<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ImportRefData extends Command
{
    protected $signature = 'import:ref-data';
    protected $description = 'Isi items_ref & cells_ref dari tabel referensi ERP (Qspart & mspart). Tidak menyentuh items/cells yang sudah ada.';

    public function handle(): int
    {
        $this->info('Mulai import data referensi ERP ke staging tables...');
        $this->newLine();

        Artisan::call('db:seed', ['--class' => 'RefDataSeeder', '--force' => true], $this->output);

        $this->newLine();
        $this->info('Selesai. Cek isi tabel items_ref dan cells_ref di database.');
        return Command::SUCCESS;
    }
}
