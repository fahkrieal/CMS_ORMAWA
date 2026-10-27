<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetUserPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:reset-password {email : Email pengguna} {password=password123 : Kata sandi baru}';

    /**
     * The console command description.
     */
    protected $description = 'Mereset kata sandi akun pengguna untuk keperluan pengembangan lokal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("Pengguna dengan email '{$email}' tidak ditemukan.");
            return Command::FAILURE;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info("Berhasil! Kata sandi akun {$email} telah diubah menjadi: {$password}");
        return Command::SUCCESS;
    }
}
