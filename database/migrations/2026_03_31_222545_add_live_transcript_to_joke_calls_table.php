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
            $table->text('live_transcript')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->dropColumn('live_transcript');
        });
    }
};
