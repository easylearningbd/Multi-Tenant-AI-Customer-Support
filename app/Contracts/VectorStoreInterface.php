<?php

namespace App\Contracts;

use App\DTOs\VectorMatch;
use App\DTOs\VectorSearchQuery;

interface VectorStoreInterface
{
    /** @param array<string, mixed> $record */
    public function upsertChunkEmbedding(array $record): void;

    /** @param list<array<string, mixed>> $records */
    public function upsertMany(array $records): void;

    /** @return list<VectorMatch> */
    public function search(VectorSearchQuery $query): array;

    public function deleteBySource(int $tenantId, int $botId, int $sourceId): void;

    public function deleteGeneration(int $tenantId, int $botId, int $sourceId, string $generationUuid): void;

    public function deleteByBot(int $tenantId, int $botId): void;

    public function deleteByTenant(int $tenantId): void;

    public function healthCheck(): bool;
}
