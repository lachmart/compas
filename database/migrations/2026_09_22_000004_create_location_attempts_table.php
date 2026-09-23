<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('submitted_photo_path')->nullable();
            $table->boolean('flagged_invalid')->default(false);
            $table->timestamps();
            $table->unique(['player_session_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_attempts');
    }
};
