<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Familia de refresh tokens: permite revocar toda la familia si se detecta reuso.
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('family_id'); // uuid compartido por toda la cadena de rotación
            $table->string('token_hash'); // sha256 del token en texto plano (nunca se guarda el token)
            $table->boolean('revoked')->default(false);
            $table->boolean('used')->default(false); // true en cuanto se usa para rotar
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('family_id');
            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
