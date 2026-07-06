<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('tipo', ['RECARGA', 'ENVIO', 'RECEPCION']);
            $table->enum('estado', ['PENDIENTE_CONFIRMACION', 'COMPLETADA', 'RECHAZADA', 'EXPIRADA'])->default('COMPLETADA');
            $table->foreignId('wallet_origen_id')->nullable()->constrained('wallets')->onDelete('cascade');
            $table->foreignId('wallet_destino_id')->nullable()->constrained('wallets')->onDelete('cascade');
            $table->decimal('monto', 12, 2);
            $table->decimal('saldo_resultante_origen', 12, 2)->nullable();
            $table->decimal('saldo_resultante_destino', 12, 2)->nullable();
            $table->string('descripcion')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('requiere_totp')->default(false);
            $table->timestamp('expira_en')->nullable();
            $table->timestamps();

            $table->index(['wallet_origen_id', 'created_at']);
            $table->index(['wallet_destino_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
