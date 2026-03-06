<?php

declare(strict_types=1);

namespace App\Message;

class LogMessage
{
    public function __construct(
        public readonly array  $log,
        public readonly string $batchId,
        public readonly string $publishedAt,
        public readonly int    $retryCount = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'log'          => $this->log,
            'batch_id'     => $this->batchId,
            'published_at' => $this->publishedAt,
            'retry_count'  => $this->retryCount,
        ];
    }
}
