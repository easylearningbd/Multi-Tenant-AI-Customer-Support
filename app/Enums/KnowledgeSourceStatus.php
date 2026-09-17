<?php

namespace App\Enums;

enum KnowledgeSourceStatus: string
{
    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case EXTRACTING = 'extracting';
    case CHUNKING = 'chunking';
    case EMBEDDING = 'embedding';
    case TRAINED = 'trained';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::QUEUED => __('Queued'),
            self::EXTRACTING => __('Extracting'),
            self::CHUNKING => __('Chunking'),
            self::EMBEDDING => __('Embedding'),
            self::TRAINED => __('Trained'),
            self::FAILED => __('Failed'),
        };
    }

    public function isProcessing(): bool
    {
        return in_array($this, [self::QUEUED, self::EXTRACTING, self::CHUNKING, self::EMBEDDING], true);
    }
}
