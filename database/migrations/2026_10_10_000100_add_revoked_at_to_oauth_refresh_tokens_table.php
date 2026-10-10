<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * When a refresh token was swapped for a new one, so it can be swapped once
 * more inside a short grace window (App\Support\GracefulRefreshTokenRepository).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            $table->dateTime('revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
