<?php

use App\Models\Review;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('token');
        });

        Review::query()->whereNull('share_token')->each(function (Review $review): void {
            $review->forceFill(['share_token' => Str::random(40)])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
