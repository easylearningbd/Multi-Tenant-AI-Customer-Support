<?php

namespace Database\Factories;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<KnowledgeChunk> */
final class KnowledgeChunkFactory extends Factory
{
    protected $model = KnowledgeChunk::class;

    public function definition(): array
    {
        $vector = [1.0, 0.0, 0.0];

        return [
            'uuid' => (string) Str::uuid(),
            'knowledge_source_id' => KnowledgeSource::factory(),
            'user_id' => fn (array $attributes) => KnowledgeSource::find($attributes['knowledge_source_id'])->user_id,
            'bot_id' => fn (array $attributes) => KnowledgeSource::find($attributes['knowledge_source_id'])->bot_id,
            'generation_uuid' => (string) Str::uuid(),
            'chunk_index' => fake()->unique()->numberBetween(0, 100000),
            'content' => fake()->paragraph(),
            'token_count' => 20,
            'content_checksum' => hash('sha256', fake()->uuid()),
            'embedding' => $vector,
            'embedding_model' => 'test-embedding-model',
            'embedding_dimensions' => 3,
            'embedding_norm' => 1.0,
            'is_active' => true,
            'embedded_at' => now(),
        ];
    }
}
