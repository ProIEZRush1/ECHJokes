<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 16)->nullable()->unique()->after('phone');
            $table->unsignedBigInteger('referred_by_user_id')->nullable()->after('referral_code');
            $table->timestamp('referral_credited_at')->nullable()->after('referred_by_user_id');
            $table->foreign('referred_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Backfill referral codes for existing users
        foreach (User::whereNull('referral_code')->cursor() as $user) {
            $user->referral_code = static::generateUniqueCode();
            $user->save();
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropColumn(['referral_code', 'referred_by_user_id', 'referral_credited_at']);
        });
    }

    protected static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(7));
        } while (DB::table('users')->where('referral_code', $code)->exists());
        return $code;
    }
};
