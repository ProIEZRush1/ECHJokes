<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            // Distinguishes a normal prank call from an "assistant" call where
            // the AI phones a company on the user's behalf and runs a process.
            $table->string('call_type')->default('prank')->index()->after('joke_category');
            // Assistant-call fields (null for prank calls).
            $table->text('assistant_objective')->nullable()->after('call_type');
            $table->text('assistant_context')->nullable()->after('assistant_objective');
            $table->string('assistant_identity')->nullable()->after('assistant_context');
            $table->string('assistant_company')->nullable()->after('assistant_identity');
            // Live question the AI is waiting on the operator to answer (null = none).
            $table->text('pending_question')->nullable()->after('live_transcript');
        });

        // `voice` is in JokeCall::$fillable and written by both launchCall and
        // launchAssistantCall, but no migration ever created the column (prod was
        // altered by hand). Add it here, guarded so it's a no-op where it already
        // exists.
        if (!Schema::hasColumn('joke_calls', 'voice')) {
            Schema::table('joke_calls', function (Blueprint $table) {
                $table->string('voice')->nullable()->after('joke_text');
            });
        }
    }

    public function down(): void
    {
        // Drop the index before the column (SQLite errors otherwise). Leave the
        // `voice` column in place — it may predate this migration and is used
        // elsewhere, so removing it would be unsafe.
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->dropIndex('joke_calls_call_type_index');
        });
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->dropColumn([
                'call_type',
                'assistant_objective',
                'assistant_context',
                'assistant_identity',
                'assistant_company',
                'pending_question',
            ]);
        });
    }
};
