<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('display_name', 100);
            $table->string('slug', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('bot_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bot_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('welcome_message');
            $table->boolean('prechat_enabled')->default(false);
            $table->string('tone', 24);
            $table->string('primary_language', 35)->default('en');
            $table->text('persona');
            $table->text('fallback_message');
            $table->boolean('offer_human_handoff')->default(true);
            $table->boolean('answer_only_from_knowledge_base')->default(true);
            $table->string('model_override', 100)->nullable();
            $table->decimal('temperature', 3, 2)->unsigned()->default(0.30);
            $table->unsignedInteger('max_output_tokens')->default(600);
            $table->decimal('kb_confidence', 4, 3)->unsigned()->default(0.650);
            $table->timestamps();
        });

        Schema::create('bot_starter_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('question', 255);
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['bot_id', 'position']);
        });

        Schema::create('bot_prechat_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label', 100);
            $table->string('type', 20);
            $table->string('placeholder', 255)->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('position');
            $table->json('options')->nullable();
            $table->timestamps();

            $table->unique(['bot_id', 'key']);
            $table->unique(['bot_id', 'position']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE bot_settings ADD CONSTRAINT bot_settings_temperature_range CHECK (temperature BETWEEN 0 AND 2)');
            DB::statement('ALTER TABLE bot_settings ADD CONSTRAINT bot_settings_kb_confidence_range CHECK (kb_confidence BETWEEN 0 AND 1)');
            DB::statement('ALTER TABLE bot_settings ADD CONSTRAINT bot_settings_max_output_tokens_positive CHECK (max_output_tokens > 0)');
            DB::statement('ALTER TABLE bot_starter_questions ADD CONSTRAINT bot_starter_questions_position_range CHECK (position BETWEEN 1 AND 6)');
            DB::statement('ALTER TABLE bot_prechat_fields ADD CONSTRAINT bot_prechat_fields_position_range CHECK (position BETWEEN 1 AND 6)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_prechat_fields');
        Schema::dropIfExists('bot_starter_questions');
        Schema::dropIfExists('bot_settings');
        Schema::dropIfExists('bots');
    }
};
