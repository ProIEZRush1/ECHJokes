<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presets', function (Blueprint $table) {
            $table->string('style')->nullable()->after('voice');
        });
    }

    public function down(): void
    {
        Schema::table('presets', function (Blueprint $table) {
            $table->dropColumn('style');
        });
    }
};
