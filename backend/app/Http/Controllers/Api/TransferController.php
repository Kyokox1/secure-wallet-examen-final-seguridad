<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class TransferController extends Controller
{
    public function __construct(protected TotpService $totpService) {}

    // POST /transfers (RF-06, RF-07, RS-01, RS-05)
    public function store(TransferRequest $request)
    {
        $user = $request->attributes->get('auth_user');
        $data = $request->validated();

        // RS-05: idempotencia obligatoria mediante header.
        $idemKey = $request->header('Idempotency-Key');
        if (!$idemKey) {
            return response()->json(['message' => 'Header Idempotency-Key es obligatorio.'], 422);
        }

        $existing = Transaction::where('idempotency_key', $idemKey)->first();
        if ($existing) {
            return response()->json([
                'uuid' => $existing->uuid,
                'estado' => $existing->estado,
                'requiere_totp' => $existing->requiere_totp,
                'expira_en' => 120,
            ]);
        }

        // Buscar destinatario por correo o teléfono (nunca por ID incremental, RS-01).
        $destinatario = User::where('email', $data['destinatario'])
            ->orWhere('telefono', $data['destinatario'])
            ->first();

        if (!$destinatario) {
            return response()->json(['message' => 'Destinatario no encontrado.'], 404);
        }

        if ($destinatario->id === $user->id) {
            throw ValidationException::withMessages(['destinatario' => ['No puede transferirse a sí mismo.']]);
        }

        $requiereTotp = $user->mfa_enabled && $data['monto'] > 500;

        $transaction = DB::transaction(function () use ($user, $destinatario, $data, $idemKey, $requiereTotp) {
            // Bloqueo de fila del wallet origen para evitar condiciones de carrera (RS-05).
            $walletOrigen = $user->wallet()->lockForUpdate()->first();

            if ((float) $walletOrigen->saldo < (float) $data['monto']) {
                throw ValidationException::withMessages(['monto' => ['Saldo insuficiente.']]);
            }

            return Transaction::create([
                'tipo' => 'ENVIO',
                'estado' => 'PENDIENTE_CONFIRMACION',
                'wallet_origen_id' => $walletOrigen->id,
                'wallet_destino_id' => $destinatario->wallet->id,
                'monto' => $data['monto'],
                'descripcion' => $data['descripcion'] ?? null,
                'idempotency_key' => $idemKey,
                'requiere_totp' => $requiereTotp,
                'expira_en' => Carbon::now()->addMinutes(2),
            ]);
        });

        return response()->json([
            'uuid' => $transaction->uuid,
            'estado' => $transaction->estado,
            'destinatario_nombre' => $destinatario->nombre_completo, // RF-07: confirmar nombre antes de ejecutar
            'requiere_totp' => $requiereTotp,
            'expira_en' => 120,
        ], 201);
    }

    // POST /transfers/{uuid}/confirm (RF-07, RS-05)
    public function confirm(Request $request, string $uuid)
    {
        $user = $request->attributes->get('auth_user');

        $request->validate([
            'confirmar' => ['required', 'boolean'],
            'codigo_totp' => ['nullable', 'string', 'size:6'],
        ]);

        $transaction = Transaction::where('uuid', $uuid)->first();

        // RS-01: solo el dueño de la billetera de origen puede confirmar.
        if (!$transaction || $transaction->walletOrigen->user_id !== $user->id) {
            return response()->json(['message' => 'No encontrado.'], 404);
        }

        if ($transaction->estado !== 'PENDIENTE_CONFIRMACION') {
            return response()->json(['message' => 'La transferencia ya fue procesada.'], 409);
        }

        if ($transaction->expira_en->isPast()) {
            $transaction->update(['estado' => 'EXPIRADA']);
            return response()->json(['message' => 'La confirmación expiró, inicie una nueva transferencia.'], 410);
        }

        if (!$request->boolean('confirmar')) {
            $transaction->update(['estado' => 'RECHAZADA']);
            return response()->json(['message' => 'Transferencia cancelada.']);
        }

        if ($transaction->requiere_totp) {
            if (!$request->filled('codigo_totp') || !$this->totpService->verify($user->mfa_secret, $request->input('codigo_totp'))) {
                AuditLogService::log($user->id, 'TRANSFER_TOTP_FAIL', $request, ['tx' => $transaction->uuid]);
                return response()->json(['message' => 'Código TOTP requerido/incorrecto.'], 401);
            }
        }

        DB::transaction(function () use ($transaction) {
            // Bloqueo de ambas billeteras en orden determinístico (por id) para evitar deadlocks.
            $ids = collect([$transaction->wallet_origen_id, $transaction->wallet_destino_id])->sort()->values();
            $walletA = \App\Models\Wallet::where('id', $ids[0])->lockForUpdate()->first();
            $walletB = \App\Models\Wallet::where('id', $ids[1])->lockForUpdate()->first();

            $walletOrigen = $walletA->id === $transaction->wallet_origen_id ? $walletA : $walletB;
            $walletDestino = $walletA->id === $transaction->wallet_destino_id ? $walletA : $walletB;

            if ((float) $walletOrigen->saldo < (float) $transaction->monto) {
                throw ValidationException::withMessages(['monto' => ['Saldo insuficiente.']]);
            }

            $walletOrigen->decrement('saldo', $transaction->monto);
            $walletDestino->increment('saldo', $transaction->monto);

            $transaction->update([
                'estado' => 'COMPLETADA',
                'saldo_resultante_origen' => $walletOrigen->fresh()->saldo,
                'saldo_resultante_destino' => $walletDestino->fresh()->saldo,
            ]);
        });

        AuditLogService::log($user->id, 'TRANSFER', $request, [
            'tx' => $transaction->uuid, 'monto' => (float) $transaction->monto,
        ]);

        return response()->json(['message' => 'Transferencia completada.', 'uuid' => $transaction->uuid]);
    }
}
