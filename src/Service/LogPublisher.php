<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LogEntry;
use App\Message\LogMessage;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class LogPublisher
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * Publish each log entry as an individual message to the bus.
     *
     * @param LogEntry[] $entries
     * @throws ExceptionInterface
     */
    public function publishBatch(array $entries, string $batchId): void
    {
        $publishedAt = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        foreach ($entries as $entry) {
            $message = new LogMessage(
                log:         $entry->toArray(),
                batchId:     $batchId,
                publishedAt: $publishedAt,
            );

            $this->messageBus->dispatch($message);
        }
    }
}
