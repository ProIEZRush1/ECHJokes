<?php

use App\Models\JokeCall;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->string('share_slug', 12)->nullable()->unique()->after('session_id');
            $table->boolean('is_public')->default(true)->after('share_slug');
            $table->unsignedInteger('share_views')->default(0)->after('is_public');
            $table->unsignedInteger('share_clicks')->default(0)->after('share_views');
        });

        foreach (JokeCall::whereNull('share_slug')->cursor() as $c) {
            do {
                $slug = strtolower(Str::random(8));
            } while (DB::table('joke_calls')->where('share_slug', $slug)->exists());
            $c->share_slug = $slug;
            $c->save();
        }
    }

    public function down(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->dropColumn(['share_slug', 'is_public', 'share_views', 'share_clicks']);
        });
    }
};
