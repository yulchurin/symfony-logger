<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\LogMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LogMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LogMessage $message): void
    {
        $this->logger->info('Log ingested', [
            'batch_id' => $message->batchId,
            'log' => $message->log,
            'published_at' => $message->publishedAt,
        ]);
    }
}