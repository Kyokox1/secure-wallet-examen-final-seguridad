<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Services\TotpService;
use Illuminate\Database\Seeder;

// E2: usuarios semilla (1 ADMIN y 2 USER, uno con MFA de prueba).
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $totp = new TotpService();

        $admin = User::create([
            'nombre_completo' => 'Administrador SecureWallet',
            'ci' => '1000001',
            'email' => 'admin@securewallet.test',
            'telefono' => '70000001',
            'password' => 'Admin#2026Seguro',
            'role' => 'ADMIN',
        ]);
        Wallet::create(['user_id' => $admin->id, 'saldo' => 0]);

        $user1 = User::create([
            'nombre_completo' => 'Kevin Demo Usuario',
            'ci' => '1000002',
            'email' => 'usuario1@securewallet.test',
            'telefono' => '70000002',
            'password' => 'Usuario1#2026',
            'role' => 'USER',
        ]);
        Wallet::create(['user_id' => $user1->id, 'saldo' => 500]);

        // Usuario 2 con MFA activo de prueba (secreto fijo para pruebas con Google Authenticator).
        $mfaSecret = $totp->generateSecret();
        $user2 = User::create([
            'nombre_completo' => 'Ana Demo Usuario',
            'ci' => '1000003',
            'email' => 'usuario2@securewallet.test',
            'telefono' => '70000003',
            'password' => 'Usuario2#2026',
            'role' => 'USER',
            'mfa_enabled' => true,
            'mfa_secret' => $mfaSecret,
        ]);
        Wallet::create(['user_id' => $user2->id, 'saldo' => 1000]);

        $this->command->warn('--------------------------------------------------');
        $this->command->info('Usuarios semilla creados:');
        $this->command->info('ADMIN:   admin@securewallet.test / Admin#2026Seguro');
        $this->command->info('USER1:   usuario1@securewallet.test / Usuario1#2026 (sin MFA)');
        $this->command->info('USER2:   usuario2@securewallet.test / Usuario2#2026 (MFA activo)');
        $this->command->info('Secreto TOTP de usuario2 (agréguelo manualmente en su app authenticator): ' . $mfaSecret);
        $this->command->warn('--------------------------------------------------');
    }
}
