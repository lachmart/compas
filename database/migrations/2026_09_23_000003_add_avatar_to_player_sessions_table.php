<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_sessions', function (Blueprint $table) {
            $table->string('avatar', 20)->nullable()->after('nickname');
        });
    }

    public function down(): void
    {
        Schema::table('player_sessions', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
