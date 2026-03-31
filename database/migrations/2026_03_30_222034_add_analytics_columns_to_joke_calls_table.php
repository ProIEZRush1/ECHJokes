<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->string('reaction_sentiment')->nullable()->after('ai_transcript');
            $table->decimal('estimated_cost_usd', 8, 4)->nullable()->after('call_duration_seconds');
            $table->unsignedBigInteger('user_id')->nullable()->after('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->dropColumn(['reaction_sentiment', 'estimated_cost_usd', 'user_id']);
        });
    }
};
