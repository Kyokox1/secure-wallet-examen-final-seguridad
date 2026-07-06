<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AuditLogService;
use App\Services\RefreshTokenService;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function __construct(
        protected RefreshTokenService $refreshTokenService,
        protected TotpService $totpService,
    ) {}

    // POST /auth/register  (RF-01, RS-04, RS-08)
    public function register(RegisterRequest $request)
    {
        if (!$this->verifyCaptcha($request->input('captcha_token'))) {
            return response()->json(['message' => 'Captcha inválido.'], 422);
        }

        $data = $request->validated();
        unset($data['captcha_token']);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'nombre_completo' => $data['nombre_completo'],
                'ci' => $data['ci'],
                'email' => $data['email'],
                'telefono' => $data['telefono'],
                'password' => $data['password'], // cast 'hashed' aplica bcrypt automáticamente
            ]);

            Wallet::create(['user_id' => $user->id, 'saldo' => 0]);

            return $user;
        });

        AuditLogService::log($user->id, 'REGISTER', request(), ['email' => $user->email]);

        return response()->json([
            'message' => 'Usuario registrado correctamente.',
            'user' => ['uuid' => $user->uuid, 'nombre_completo' => $user->nombre_completo, 'email' => $user->email],
        ], 201);
    }

    // POST /auth/login (RF-02, RF-03, RS-07, RS-08)
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->input('email'))->first();

        // Respuesta genérica para no filtrar si el email existe (evita user enumeration).
        $genericError = response()->json(['message' => 'Credenciales inválidas.'], 401);

        if (!$user) {
            return $genericError;
        }

        if ($user->is_blocked) {
            AuditLogService::log($user->id, 'LOGIN_BLOCKED', $request);
            return response()->json(['message' => 'Cuenta bloqueada. Contacte al administrador.'], 403);
        }

        if ($user->isLocked()) {
            AuditLogService::log($user->id, 'LOGIN_LOCKED', $request);
            return response()->json(['message' => 'Cuenta bloqueada temporalmente. Intente más tarde.'], 429);
        }

        if (!Hash::check($request->input('password'), $user->password)) {
            $user->increment('failed_login_attempts');

            // RF-02: 5 intentos fallidos -> bloqueo temporal 15 minutos.
            if ($user->failed_login_attempts >= 5) {
                $user->update(['locked_until' => Carbon::now()->addMinutes(15), 'failed_login_attempts' => 0]);
                AuditLogService::log($user->id, 'ACCOUNT_LOCKED', $request);
            }

            AuditLogService::log($user->id, 'LOGIN_FAIL', $request);
            return $genericError;
        }

        $user->update(['failed_login_attempts' => 0]);

        if ($user->mfa_enabled) {
            // Emite un "ticket MFA" de corta duración en vez de los tokens finales.
            $ticket = Str::random(48);
            cache()->put('mfa_ticket:' . $ticket, $user->id, now()->addMinutes(3));

            AuditLogService::log($user->id, 'LOGIN_MFA_PENDING', $request);

            return response()->json([
                'mfa_required' => true,
                'mfa_ticket' => $ticket,
                'expira_en' => 180,
            ]);
        }

        AuditLogService::log($user->id, 'LOGIN_OK', $request);

        return $this->issueTokens($user);
    }

    // POST /auth/mfa/verify (RF-03)
    public function mfaVerify(Request $request)
    {
        $request->validate([
            'mfa_ticket' => ['required', 'string'],
            'codigo' => ['required', 'string', 'size:6'],
        ]);

        $userId = cache()->get('mfa_ticket:' . $request->input('mfa_ticket'));

        if (!$userId) {
            return response()->json(['message' => 'Ticket inválido o expirado.'], 401);
        }

        $user = User::find($userId);

        if (!$user || !$this->totpService->verify($user->mfa_secret, $request->input('codigo'))) {
            AuditLogService::log($userId, 'MFA_FAIL', $request);
            return response()->json(['message' => 'Código TOTP inválido.'], 401);
        }

        cache()->forget('mfa_ticket:' . $request->input('mfa_ticket'));
        AuditLogService::log($user->id, 'LOGIN_OK', $request);

        return $this->issueTokens($user);
    }

    // POST /auth/mfa/enable (RF-03)
    public function mfaEnable(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $secret = $this->totpService->generateSecret();
        $user->update(['mfa_secret' => $secret]);

        return response()->json([
            'qr_code_url' => $this->totpService->qrCodeUrl($user->email, $secret),
            'secret' => $secret, // se muestra una única vez para configurar el authenticator
        ]);
    }

    // POST /auth/mfa/confirm  -> confirma el primer código y activa el MFA
    public function mfaConfirm(Request $request)
    {
        $request->validate(['codigo' => ['required', 'string', 'size:6']]);
        $user = $request->attributes->get('auth_user');

        if (!$this->totpService->verify($user->mfa_secret, $request->input('codigo'))) {
            return response()->json(['message' => 'Código inválido.'], 422);
        }

        $user->update(['mfa_enabled' => true]);
        AuditLogService::log($user->id, 'MFA_ENABLED', $request);

        return response()->json(['message' => 'MFA activado correctamente.']);
    }

    // POST /auth/refresh (RS-07: rotación + detección de reúso)
    public function refresh(Request $request)
    {
        $request->validate(['refresh_token' => ['required', 'string']]);

        $result = $this->refreshTokenService->validateAndRotate($request->input('refresh_token'));

        if (!$result) {
            return response()->json(['message' => 'Refresh token inválido, expirado o reutilizado.'], 401);
        }

        return response()->json([
            'access_token' => $this->refreshTokenService->issueAccessToken($result['user']),
            'refresh_token' => $result['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]);
    }

    // POST /auth/logout (RF-10: invalida el access token Y el refresh token en el servidor)
    public function logout(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $payload = $request->attributes->get('jwt_payload');

        // 1. Revoca el access token ACTUAL agregándolo a la blacklist (RF-10).
        if ($payload && isset($payload->jti, $payload->exp)) {
            $this->refreshTokenService->blacklistAccessToken($payload->jti, $payload->exp);
        }

        // 2. Revoca TODOS los refresh tokens del usuario (todas las sesiones/dispositivos).
        $this->refreshTokenService->revokeAllForUser($user);

        AuditLogService::log($user->id, 'LOGOUT', $request);

        return response()->json(['message' => 'Sesión cerrada. Token y refresh token invalidados.']);
    }

    protected function issueTokens(User $user)
    {
        $access = $this->refreshTokenService->issueAccessToken($user);
        $refresh = $this->refreshTokenService->issueRefreshToken($user);

        return response()->json([
            'access_token' => $access,
            'refresh_token' => $refresh['token'],
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]);
    }

    protected function verifyCaptcha(?string $token): bool
    {
        if (config('app.env') === 'testing') {
            return true;
        }

        $secret = config('services.recaptcha.secret');
        if (!$secret) {
            return true; // permitir desarrollo local sin claves configuradas
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
        ]);

        return (bool) ($response->json('success') ?? false);
    }
}
