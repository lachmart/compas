<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Optional text with pictures shown to the player on arrival (a nature
// trail board), independent of the station's task type.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('info_title', 150)->nullable()->after('task_payload');
            $table->text('info_text')->nullable()->after('info_title');
            $table->json('info_images')->nullable()->after('info_text');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['info_title', 'info_text', 'info_images']);
        });
    }
};
