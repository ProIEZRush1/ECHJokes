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
            // joke_source: 'ai' (default), 'premade', 'custom'
            $table->string('joke_source')->default('ai')->after('joke_category');
            // custom_joke_prompt: user's custom joke instructions or selected premade joke ID
            $table->text('custom_joke_prompt')->nullable()->after('joke_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->dropColumn(['joke_source', 'custom_joke_prompt']);
        });
    }
};
