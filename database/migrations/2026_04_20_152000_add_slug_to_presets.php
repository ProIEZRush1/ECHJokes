<?php

use App\Models\Preset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presets', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('label');
        });

        $used = [];
        foreach (Preset::cursor() as $preset) {
            $base = Str::slug($preset->label) ?: ('broma-' . $preset->id);
            $slug = $base;
            $i = 2;
            while (in_array($slug, $used) || Preset::where('slug', $slug)->whereKeyNot($preset->id)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $used[] = $slug;
            $preset->slug = $slug;
            $preset->save();
        }
    }

    public function down(): void
    {
        Schema::table('presets', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
