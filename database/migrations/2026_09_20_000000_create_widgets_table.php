<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('bot_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->char('accent_color', 7);
            $table->enum('position', ['bottom_left', 'bottom_right'])->default('bottom_right');
            $table->string('welcome_message', 500);
            $table->timestamps();

            $table->index(['user_id', 'is_enabled']);
            $table->index(['user_id', 'bot_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
