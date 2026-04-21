<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_credits', function (Blueprint $table) {
            $table->unsignedInteger('jokes_remaining')->default(5)->after('credits_remaining');
            $table->timestamp('jokes_reset_at')->nullable()->after('jokes_remaining');
        });

        DB::table('user_credits')->update(['jokes_remaining' => 5, 'jokes_reset_at' => now()->addMonth()]);
    }

    public function down(): void
    {
        Schema::table('user_credits', function (Blueprint $table) {
            $table->dropColumn(['jokes_remaining', 'jokes_reset_at']);
        });
    }
};
