<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Console\Command;

class ShowMfaCodeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mfa:code {email? : Email pengguna yang ingin dilihat kode OTP-nya}';

    /**
     * The console command description.
     */
    protected $description = 'Menampilkan kode OTP 6-digit TOTP saat ini untuk testing MFA/2FA';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $users = User::whereNotNull('two_factor_secret')->get();
        if ($users->isEmpty()) {
            $this->error('Tidak ada user dengan 2FA yang terdaftar.');
            return Command::FAILURE;
        }

        foreach ($users as $user) {
            $secret = $user->two_factor_secret;
            $currentTime = time();
            $secondsRemaining = 30 - ($currentTime % 30);
            $currentCode = TotpService::generateCode($secret);

            $this->info("==========================================");
            $this->info("User ID: {$user->id} | {$user->name} ({$user->email})");
            $this->info("2FA Secret: {$secret}");
            $this->info("Status 2FA: " . ($user->two_factor_confirmed_at ? 'Aktif (Confirmed)' : 'Menunggu Konfirmasi'));
            $this->line("Jam Sistem Laptop: " . date('Y-m-d H:i:s'));
            $this->line("Unix Timestamp: " . $currentTime);
            $this->line("Recovery codes count in DB: " . (is_array($user->two_factor_recovery_codes) ? count($user->two_factor_recovery_codes) : '0'));
            $this->alert("KODE OTP SAAT INI (LAPTOP CLOCK): {$currentCode}");
            $this->comment("Berlaku selama: {$secondsRemaining} detik lagi");
            $this->info("==========================================");
        }

        return Command::SUCCESS;

        return Command::SUCCESS;
    }
}
