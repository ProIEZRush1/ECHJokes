<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            // Post-call recap of an assistant call (what happened / outcome).
            $table->text('assistant_summary')->nullable()->after('pending_question');
        });
    }

    public function down(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->dropColumn('assistant_summary');
        });
    }
};
