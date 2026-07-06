<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nombre_completo');
            $table->string('ci')->unique();
            $table->string('email')->unique();
            $table->string('telefono')->unique();
            $table->string('password');
            $table->enum('role', ['USER', 'ADMIN'])->default('USER');
            $table->boolean('mfa_enabled')->default(false);
            $table->text('mfa_secret')->nullable(); // encriptado (cast)
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->boolean('is_blocked')->default(false); // bloqueo administrativo
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
