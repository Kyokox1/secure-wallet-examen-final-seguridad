<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TopUpRequest;
use App\Models\Transaction;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    // GET /me (RF-04)
    public function me(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        return response()->json([
            'uuid' => $user->uuid,
            'nombre_completo' => $user->nombre_completo,
            'email' => $user->email,
            'telefono' => $user->telefono,
            'role' => $user->role,
            'mfa_enabled' => $user->mfa_enabled,
        ]);
    }

    // GET /wallet (RF-04) - RS-01: SIEMPRE la billetera del usuario autenticado, jamás por parámetro.
    public function show(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        return response()->json([
            'uuid' => $user->wallet->uuid,
            'saldo' => $user->wallet->saldo,
        ]);
    }

    // POST /wallet/topup (RF-05)
    public function topup(TopUpRequest $request)
    {
        $user = $request->attributes->get('auth_user');
        $monto = $request->validated()['monto'];

        $transaction = DB::transaction(function () use ($user, $monto) {
            // Bloqueo de fila para evitar condiciones de carrera (RS-05)
            $wallet = $user->wallet()->lockForUpdate()->first();
            $wallet->increment('saldo', $monto);
            $wallet->refresh();

            return Transaction::create([
                'tipo' => 'RECARGA',
                'estado' => 'COMPLETADA',
                'wallet_destino_id' => $wallet->id,
                'monto' => $monto,
                'saldo_resultante_destino' => $wallet->saldo,
            ]);
        });

        AuditLogService::log($user->id, 'TOPUP', $request, ['monto' => (float) $monto, 'tx' => $transaction->uuid]);

        return response()->json([
            'message' => 'Recarga exitosa.',
            'saldo_actual' => $user->wallet->fresh()->saldo,
            'transaccion' => $transaction->uuid,
        ], 201);
    }

    // GET /transactions (RF-08)
    public function history(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $walletId = $user->wallet->id;

        $query = Transaction::where(function ($q) use ($walletId) {
            $q->where('wallet_origen_id', $walletId)->orWhere('wallet_destino_id', $walletId);
        })->where('estado', 'COMPLETADA')->orderByDesc('created_at');

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        $perPage = min((int) $request->query('per_page', 15), 50);
        $page = $query->paginate($perPage);

        $items = collect($page->items())->map(function (Transaction $t) use ($walletId) {
            $esOrigen = $t->wallet_origen_id === $walletId;
            return [
                'uuid' => $t->uuid,
                'tipo' => $t->tipo,
                'monto' => $t->monto,
                'fecha' => $t->created_at,
                'descripcion' => $t->descripcion,
                'saldo_resultante' => $esOrigen ? $t->saldo_resultante_origen : $t->saldo_resultante_destino,
                'direccion' => $t->tipo === 'RECARGA' ? 'ENTRADA' : ($esOrigen ? 'SALIDA' : 'ENTRADA'),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
