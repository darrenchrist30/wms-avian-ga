<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Artisan Command: erp:generate-token
 *
 * Membuat API token Sanctum untuk digunakan oleh sistem ERP
 * saat mengakses endpoint WMS (/api/v1/inbound/...).
 *
 * Cara pakai:
 *   php artisan erp:generate-token
 *   php artisan erp:generate-token --email=admin@avian.com
 *   php artisan erp:generate-token --email=admin@avian.com --name="SAP-Production"
 *
 * Token yang dihasilkan dipakai di header:
 *   Authorization: Bearer <token>
 */
class GenerateErpApiToken extends Command
{
    protected $signature = 'erp:generate-token
                            {--email= : Email user yang akan digunakan sebagai pemilik token}
                            {--name=  : Nama token (default: ERP-Integration)}';

    protected $description = 'Generate API token Sanctum untuk integrasi ERP → WMS';

    public function handle(): int
    {
        $this->info('=== Generate ERP API Token ===');
        $this->newLine();

        // Ambil email dari option atau tanya interaktif
        $email = $this->option('email') ?: $this->ask(
            'Email akun WMS yang akan dipakai sebagai pemilik token',
            'admin@avian.com'
        );

        $tokenName = $this->option('name') ?: $this->ask(
            'Nama token (gunakan nama deskriptif, misal: SAP-Production)',
            'ERP-Integration'
        );

        // Cari user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User dengan email '{$email}' tidak ditemukan.");
            $this->line('Gunakan perintah ini untuk melihat daftar user:');
            $this->line('  php artisan tinker --execute="App\Models\User::select(\'name\',\'email\')->get()->each(fn(\$u) => dump(\$u->email))"');
            return self::FAILURE;
        }

        // Revoke token lama dengan nama yang sama (hindari penumpukan token)
        $revokedCount = $user->tokens()->where('name', $tokenName)->count();
        if ($revokedCount > 0) {
            $user->tokens()->where('name', $tokenName)->delete();
            $this->warn("Token lama dengan nama '{$tokenName}' telah dihapus ({$revokedCount} token).");
        }

        // Buat token baru dengan semua ability ERP
        $abilities = [
            'erp:sync-master',      // sync item & supplier dari ERP
            'erp:receive-inbound',  // kirim Delivery Order ke WMS
        ];
        $token = $user->createToken($tokenName, $abilities);

        $this->newLine();
        $this->info('Token berhasil dibuat!');
        $this->newLine();

        $this->table(
            ['Field', 'Value'],
            [
                ['Pemilik', $user->name . ' <' . $user->email . '>'],
                ['Nama Token', $tokenName],
                ['Abilities', implode(', ', $abilities)],
            ]
        );

        $this->newLine();
        $this->line('<fg=yellow;options=bold>Bearer Token (simpan sekarang, tidak bisa dilihat lagi):</>');
        $this->line('');
        $this->line('  <fg=green>' . $token->plainTextToken . '</>');
        $this->newLine();

        $this->line('Cara pakai di header HTTP request:');
        $this->line('  Authorization: Bearer ' . $token->plainTextToken);
        $this->newLine();

        $this->warn('PENTING: Token ini hanya ditampilkan sekali. Simpan di tempat yang aman!');

        return self::SUCCESS;
    }
}
