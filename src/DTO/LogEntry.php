<?php

declare(strict_types=1);

namespace App\DTO;

class LogEntry
{
    public function __construct(
        public readonly string $timestamp,
        public readonly string $level,
        public readonly string $service,
        public readonly string $message,
        public readonly array $context = [],
        public readonly ?string $traceId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            timestamp: $data['timestamp'],
            level:     $data['level'],
            service:   $data['service'],
            message:   $data['message'],
            context:   $data['context'] ?? [],
            traceId:   $data['trace_id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'level'     => $this->level,
            'service'   => $this->service,
            'message'   => $this->message,
            'context'   => $this->context,
            'trace_id'  => $this->traceId,
        ];
    }
}
