<?php

namespace Database\Factories;

use App\Enums\KnowledgeSourceStatus;
use App\Enums\KnowledgeSourceType;
use App\Models\Bot;
use App\Models\KnowledgeSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<KnowledgeSource> */
final class KnowledgeSourceFactory extends Factory
{
    protected $model = KnowledgeSource::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory()->subscriber(),
            'bot_id' => fn (array $attributes) => Bot::factory()->create(['user_id' => $attributes['user_id']])->id,
            'created_by' => fn (array $attributes) => $attributes['user_id'],
            'type' => KnowledgeSourceType::TEXT,
            'name' => fake()->sentence(3),
            'raw_text' => fake()->paragraphs(3, true),
            'status' => KnowledgeSourceStatus::QUEUED,
            'chunk_count' => 0,
            'file_size_bytes' => 0,
            'processing_token' => (string) Str::uuid(),
        ];
    }
}
