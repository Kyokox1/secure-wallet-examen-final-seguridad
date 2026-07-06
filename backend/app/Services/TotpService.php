<?php

namespace App\Services;

use PragmaRX\Google2FA\Google2FA;

// RS-... MFA/TOTP con Google Authenticator (RF-03).
class TotpService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function qrCodeUrl(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl('SecureWallet', $email, $secret);
    }

    public function verify(string $secret, string $code): bool
    {
        // window=1 tolera pequeño desfase de reloj (30s antes/después)
        return $this->google2fa->verifyKey($secret, $code, 1);
    }
}
