<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->string('device_hash', 64)->nullable()->after('ip_address')->index();
        });
    }

    public function down(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->dropColumn('device_hash');
        });
    }
};
